Our React screen currently receives everything in the original HTML document. That was a
safe first migration because it preserved the backend behavior. Now we will move one piece
of data—the issue collection—behind a JSON endpoint.

The project name, form token, validation errors, and flash message will still arrive with
the document. Only the list changes. This gives us a small API we can inspect directly and
forces the interface to represent the moments a network request can actually have: loading,
ready, empty, and failed.

## Add a read-only API route

Open `routes/routes.php`. Place this GET route beside the project page route:

```php
$router->get('/workspaces/{workspace}/projects/{project}', 'projects/show.php');
$router->get('/api/workspaces/{workspace}/projects/{project}/issues', 'api/issues/index.php');
$router->post('/workspaces/{workspace}/projects/{project}/issues', 'issues/store.php')->only('csrf');
```

The path says exactly which collection it returns: issues belonging to one project inside
one workspace. GET does not change server state, so it does not need CSRF middleware.

Create `app/Http/controllers/api/issues/index.php`. Read and validate both route segments:

```php
<?php

declare(strict_types=1);

use Core\App;
use Core\Database;
use Core\Request;
use Core\Response;

$request = App::resolve(Request::class);
$workspaceId = $request->route('workspace');
$projectId = $request->route('project');

if (
    !is_string($workspaceId)
    || preg_match('/\A[1-9]\d*\z/', $workspaceId) !== 1
    || !is_string($projectId)
    || preg_match('/\A[1-9]\d*\z/', $projectId) !== 1
) {
    abort(404);
}
```

An API route is still an external input boundary. A malformed ID should stop before SQL,
just as it does on the HTML page.

Verify the complete parent chain before selecting issues:

```php
$database = App::resolve(Database::class);
$workspace = $database
    ->query(
        'SELECT id FROM workspaces WHERE id = :id',
        ['id' => (int) $workspaceId],
    )
    ->findOrFail();

$project = $database
    ->query(
        'SELECT id FROM projects WHERE id = :id AND workspace_id = :workspace_id',
        [
            'id' => (int) $projectId,
            'workspace_id' => $workspace['id'],
        ],
    )
    ->findOrFail();

$issues = $database
    ->query(
        'SELECT id, title, description, status
         FROM issues
         WHERE project_id = :project_id
         ORDER BY id DESC',
        ['project_id' => $project['id']],
    )
    ->get();
```

A real project ID under the wrong workspace must not reveal its issues. The second query
therefore checks `id` and `workspace_id` together. The final query uses only the verified
project ID and keeps newest issues first.

Return an explicit public shape:

```php
return Response::json([
    'issues' => array_map(
        static fn (array $issue): array => [
            'id' => (int) ($issue['id'] ?? 0),
            'title' => (string) ($issue['title'] ?? ''),
            'description' => (string) ($issue['description'] ?? ''),
            'status' => (string) ($issue['status'] ?? ''),
        ],
        $issues,
    ),
]);
```

`Response::json()` encodes the array and sets `Content-Type: application/json`. Mapping
the rows gives the API a deliberate contract instead of making every database column
public forever.

## Stop doing the same query for the HTML shell

Open `app/Http/controllers/projects/show.php`. Remove the query that selects `$issues`,
then remove `'issues' => $issues` from the values passed to the view. The end of the
controller should now be:

```php
view('projects/show.view.php', [
    'workspace' => $workspace,
    'project' => $project,
]);
```

Next open `resources/views/projects/show.view.php` and remove the entire `'issues' =>
array_map(...)` entry from `$projectPageData`. Keep workspace, project, form, and success
unchanged.

This matters for more than a smaller document. If the HTML controller kept querying issues,
the browser would wait for work whose result it never used, then make the API query again.
There should be one owner for the collection.

## Validate the API response

Open `resources/app/project-page-data.ts`. Remove `issues: Issue[]` from
`ProjectPageData`, because the document no longer promises that value. In
`parseProjectPageData()`, remove the local `issues`, its array check, and the returned
`issues` field.

Keep `Issue` and `parseIssue()`; the endpoint still needs both. Below
`parseProjectPageData()`, add a parser for the response envelope:

```ts
export function parseIssuesResponse(value: unknown): Issue[] {
  if (!isRecord(value) || !Array.isArray(value.issues)) {
    throw new Error('The issues response has an invalid issues list.')
  }

  return value.issues.map(parseIssue)
}
```

The HTTP status alone cannot prove that the body has the right shape. This reuses our
runtime issue parser, including its positive integer and `open | closed` checks.

Add the request function below it:

```ts
export async function fetchIssues(
  workspaceId: number,
  projectId: number,
  signal: AbortSignal,
): Promise<Issue[]> {
  const response = await fetch(
    `/api/workspaces/${workspaceId}/projects/${projectId}/issues`,
    {
      headers: { Accept: 'application/json' },
      signal,
    },
  )

  if (!response.ok) {
    throw new Error(`The issues request failed with status ${response.status}.`)
  }

  const value: unknown = await response.json()

  return parseIssuesResponse(value)
}
```

Fetch resolves normally for HTTP errors such as 404 and 500, so we must check
`response.ok`. The abort signal will let the component cancel a request that is no longer
relevant. The parsed result—not unchecked JSON—is what the rest of React receives.

## Model the request as visible states

Open `resources/app/ProjectPage.tsx`. Add the hooks and request import at the top:

```tsx
import { useEffect, useState } from 'react'
import { fetchIssues } from './project-page-data'
import type { Issue, ProjectPageData } from './project-page-data'
```

Below `EmptyIssues`, describe every state the collection may occupy:

```tsx
type IssuesState =
  | { status: 'loading' }
  | { status: 'ready'; issues: Issue[] }
  | { status: 'error' }
```

This union prevents impossible combinations such as “failed, but also has a ready issues
array.” Create the component that owns the request:

```tsx
function IssuesPanel({ workspaceId, projectId }: {
  workspaceId: number
  projectId: number
}) {
  const [attempt, setAttempt] = useState(0)
  const [state, setState] = useState<IssuesState>({ status: 'loading' })

  useEffect(() => {
    const controller = new AbortController()
    setState({ status: 'loading' })

    void fetchIssues(workspaceId, projectId, controller.signal)
      .then((issues) => setState({ status: 'ready', issues }))
      .catch((error: unknown) => {
        if (error instanceof DOMException && error.name === 'AbortError') return
        setState({ status: 'error' })
      })

    return () => controller.abort()
  }, [workspaceId, projectId, attempt])
```

The effect synchronizes this component with an external system: our API. Its cleanup aborts
the old request. That is especially important in development, where Strict Mode runs an
extra setup-and-cleanup cycle to expose effects that are unsafe to repeat.

Continue with loading and failure output:

```tsx
  if (state.status === 'loading') {
    return (
      <div className="mt-[18px] border-t border-line-strong py-8" role="status">
        <p className="text-sm font-semibold text-muted">Loading issues…</p>
      </div>
    )
  }

  if (state.status === 'error') {
    return (
      <div className="mt-[18px] rounded-[12px] border border-[#f1a9a3] bg-[#fff1f0] p-5" role="alert">
        <h3 className="text-[15px] font-bold text-[#7a271a]">Issues could not be loaded</h3>
        <p className="mt-2 text-[13px] leading-6 text-[#7a271a]">
          Check the connection, then try this request again.
        </p>
        <button
          className="mt-4 min-h-11 rounded-[9px] border border-[#d92d20] bg-surface px-4 py-2 text-sm font-bold text-[#a51d14] focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-[#fda29b]"
          type="button"
          onClick={() => setAttempt((current) => current + 1)}
        >
          Try again
        </button>
      </div>
    )
  }
```

Loading is announced as status, while failure is an alert with a real recovery action.
Incrementing `attempt` changes an effect dependency, so Try again starts a new request
without reloading the whole project page.

Finish `IssuesPanel` by reusing the components we already trust:

```tsx
  if (state.issues.length === 0) return <EmptyIssues />

  return (
    <ol className="mt-[18px] list-none border-t border-line-strong p-0">
      {state.issues.map((issue) => (
        <IssueRow
          key={issue.id}
          issue={issue}
          workspaceId={workspaceId}
          projectId={projectId}
        />
      ))}
    </ol>
  )
}
```

Ready-with-no-results is different from loading, so only a successful empty array reaches
`EmptyIssues`.

## Connect the panel to the screen

In `ProjectPage`, stop destructuring `issues` and remove `issueCount`:

```tsx
const { workspace, project, success } = data
```

Inside the section labelled `issues-title`, replace the old count/list conditional with:

```tsx
<h2 id="issues-title" className="text-[22px] font-bold tracking-[-0.025em]">
  Issues
</h2>
<IssuesPanel workspaceId={workspace.id} projectId={project.id} />
```

The form remains a traditional POST for now. After DALT redirects back, React fetches the
new collection and shows the created issue. We changed the read path without also changing
the write path.

## Exercise every boundary

Run the static checks and production build:

```bash
npm run typecheck
npm run lint
npm run build
```

Then start DALT:

```bash
php artisan serve
```

Open the endpoint directly, using IDs that exist in your database:

```text
http://localhost:8000/api/workspaces/1/projects/1/issues
```

You should receive an object with an `issues` array and an
`application/json; charset=UTF-8` content type. Also prove the hierarchy: place a valid
project ID beneath the wrong workspace ID and expect 404.

Now open the project page. Its issues should appear after the request completes. Create an
issue through the unchanged form, return through the redirect, and confirm the newest row
appears first. Test a project with no issues and confirm the empty state appears only after
the successful request.

Finally, temporarily make the API URL invalid in `fetchIssues`, rebuild, and reload. The
screen should retain its project context and creation form while showing **Issues could not
be loaded** and **Try again**. Restore the correct URL and rebuild when the check is done.

If Git is available, save the working boundary:

```bash
git add routes/routes.php app/Http/controllers/projects/show.php \
  app/Http/controllers/api/issues/index.php resources/views/projects/show.view.php \
  resources/app/project-page-data.ts resources/app/ProjectPage.tsx
git commit -m "Load project issues from JSON"
```

We now have a real DALT API and a React screen that tells the truth about network state.
The next step can change issue creation from a full-page form submission into a JSON write,
then update this same collection without reloading the document.
