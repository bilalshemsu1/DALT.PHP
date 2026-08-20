Issue rows still use document navigation to reach PHP detail pages. We now have two views
that belong to the same interactive frontend, so client routing has a concrete job. We will
give the project and each issue distinct React locations while DALT continues to serve the
initial shell and the JSON behind those screens.

## Install only the router runtime we need

Install the current React Router 7 release compatible with our pinned React version:

```bash
npm install react-router@7.18.2
```

We are using React Router's data-mode runtime with our existing Vite build, not replacing
DALT with React Router's full-stack framework. The install may report dependency audit
advisories; do not force unrelated major upgrades into this routing checkpoint.

## Add one issue JSON resource

Open `routes/routes.php`. Add a detail endpoint after the collection routes:

```php
$router->get('/api/workspaces/{workspace}/projects/{project}/issues', 'api/issues/index.php');
$router->post('/api/workspaces/{workspace}/projects/{project}/issues', 'api/issues/store.php')->only('csrf');
$router->get('/api/workspaces/{workspace}/projects/{project}/issues/{issue}', 'api/issues/show.php');
```

Create `app/Http/controllers/api/issues/show.php`. Validate all three route values:

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
$issueId = $request->route('issue');

if (
    !is_string($workspaceId) || preg_match('/\A[1-9]\d*\z/', $workspaceId) !== 1
    || !is_string($projectId) || preg_match('/\A[1-9]\d*\z/', $projectId) !== 1
    || !is_string($issueId) || preg_match('/\A[1-9]\d*\z/', $issueId) !== 1
) {
    abort(404);
}
```

Walk the entire hierarchy before returning anything:

```php
$database = App::resolve(Database::class);
$workspace = $database
    ->query('SELECT id FROM workspaces WHERE id = :id', ['id' => (int) $workspaceId])
    ->findOrFail();

$project = $database
    ->query(
        'SELECT id FROM projects WHERE id = :id AND workspace_id = :workspace_id',
        ['id' => (int) $projectId, 'workspace_id' => $workspace['id']],
    )
    ->findOrFail();

$issue = $database
    ->query(
        'SELECT id, title, description, status FROM issues
         WHERE id = :id AND project_id = :project_id',
        ['id' => (int) $issueId, 'project_id' => $project['id']],
    )
    ->findOrFail();
```

An existing issue cannot be read beneath a different project. Finish with the same public
shape used by the list endpoint:

```php
return Response::json([
    'issue' => [
        'id' => (int) ($issue['id'] ?? 0),
        'title' => (string) ($issue['title'] ?? ''),
        'description' => (string) ($issue['description'] ?? ''),
        'status' => (string) ($issue['status'] ?? ''),
    ],
]);
```

## Let DALT serve the shell at deep URLs

React Router can interpret a URL only after JavaScript loads. A direct browser request
reaches DALT first. Change the existing issue GET route to use the project shell controller:

```php
$router->get('/workspaces/{workspace}/projects/{project}/issues/{issue}', 'projects/show.php');
```

Keep the edit, delete, status, and mutation routes as they are. The shell controller proves
the workspace/project pair and sends the same bootstrap and Vite entry. React then reads
the issue segment and asks the stricter detail API to prove the third relationship.

This server fallback is what makes refresh, bookmarks, and pasted issue URLs work. Client
routing alone would make link clicks appear correct while direct visits returned 404.

## Fetch one issue through the existing parser

Open `resources/app/project-page-data.ts`. Add:

```ts
export async function fetchIssue(
  workspaceId: number,
  projectId: number,
  issueId: number,
  signal: AbortSignal,
): Promise<Issue> {
  const response = await fetch(
    `/api/workspaces/${workspaceId}/projects/${projectId}/issues/${issueId}`,
    { headers: { Accept: 'application/json' }, signal },
  )

  if (!response.ok) {
    throw new Error(`The issue request failed with status ${response.status}.`)
  }

  const value: unknown = await response.json()
  if (!isRecord(value)) throw new Error('The issue response is invalid.')

  return parseIssue(value.issue)
}
```

The list and detail endpoints now share `parseIssue`. Changing the API to return an invalid
status or ID fails at one runtime boundary instead of creating two subtly different rules.

## Extract the shared application frame

Create `resources/app/AppLayout.tsx`:

```tsx
import { Link, Outlet } from 'react-router'
import type { ProjectPageData } from './project-page-data'

export function AppLayout({ data }: { data: ProjectPageData }) {
  return (
    <>
      <header className="border-b border-line bg-surface">
        <div className="mx-auto flex min-h-16 w-[calc(100%_-_40px)] max-w-[960px] items-center justify-between gap-5 max-sm:w-[calc(100%_-_32px)]">
          <Link
            className="inline-flex items-center gap-2.5 text-[15px] font-bold text-ink no-underline"
            to={`/workspaces/${data.workspace.id}/projects/${data.project.id}`}
          >
            <span className="h-6 w-2.5 rounded-[3px] bg-accent" aria-hidden="true" />
            DALT Issues
          </Link>
          <span className="text-[13px] text-muted">Local development</span>
        </div>
      </header>
      <Outlet />
    </>
  )
}
```

Move the matching header out of `ProjectPage`. `Outlet` marks where the selected child
screen appears. The application frame stays mounted while navigation swaps the project
index for an issue detail.

In `ProjectPage.tsx`, import `Link` and change `IssueRow`'s anchor to:

```tsx
<Link
  className="block min-w-0 rounded-lg py-5 ps-1 pe-2 text-ink no-underline"
  to={`/workspaces/${workspaceId}/projects/${projectId}/issues/${issue.id}`}
>
  {/* Keep the existing issue title, status, and description. */}
</Link>
```

Unlike an anchor, `Link` updates browser history and lets the router render the next child
without requesting a new HTML document. It remains a real accessible link with a real URL.

## Build the React issue detail

Create `resources/app/IssuePage.tsx`. Read the dynamic segment and model the request:

```tsx
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'
import { fetchIssue } from './project-page-data'
import type { Issue, ProjectPageData } from './project-page-data'

type IssueState =
  | { status: 'loading' }
  | { status: 'ready'; issue: Issue }
  | { status: 'error' }

export function IssuePage({ data }: { data: ProjectPageData }) {
  const { issueId } = useParams()
  const parsedIssueId = Number(issueId)
  const [state, setState] = useState<IssueState>({ status: 'loading' })
```

Fetch when the route identity changes and abort stale work:

```tsx
  useEffect(() => {
    const controller = new AbortController()

    if (!Number.isInteger(parsedIssueId) || parsedIssueId < 1) {
      setState({ status: 'error' })
      return () => controller.abort()
    }

    setState({ status: 'loading' })
    void fetchIssue(data.workspace.id, data.project.id, parsedIssueId, controller.signal)
      .then((issue) => setState({ status: 'ready', issue }))
      .catch((error: unknown) => {
        if (!(error instanceof DOMException && error.name === 'AbortError')) {
          setState({ status: 'error' })
        }
      })

    return () => controller.abort()
  }, [data.workspace.id, data.project.id, parsedIssueId])
```

Render a link back to the project, then honest loading, failure, and ready branches:

```tsx
  const projectUrl = `/workspaces/${data.workspace.id}/projects/${data.project.id}`

  return (
    <main className="mx-auto w-[calc(100%_-_40px)] max-w-[760px] py-14 max-sm:w-[calc(100%_-_32px)] sm:py-[72px]">
      <Link className="text-sm font-semibold text-muted no-underline hover:text-accent" to={projectUrl}>
        Back to {data.project.name}
      </Link>

      {state.status === 'loading' && <p className="mt-10 text-sm font-semibold text-muted" role="status">Loading issue…</p>}

      {state.status === 'error' && (
        <section className="mt-10 rounded-[12px] border border-[#f1a9a3] bg-[#fff1f0] p-5" role="alert">
          <h1 className="text-xl font-bold text-[#7a271a]">Issue could not be loaded</h1>
          <p className="mt-2 text-sm leading-6 text-[#7a271a]">It may not exist in this project. Return to the issue list and choose another issue.</p>
        </section>
      )}

      {state.status === 'ready' && (
        <article className="mt-10">
          <span className="inline-flex rounded-full bg-accent-soft px-2.5 py-1 text-xs font-extrabold text-[#075b43]">
            {state.issue.status === 'closed' ? 'Closed' : 'Open'}
          </span>
          <h1 className="mt-4 break-words text-[clamp(36px,6vw,52px)] leading-[1.04] font-bold tracking-[-0.04em]">{state.issue.title}</h1>
          <p className="mt-4 text-[13px] text-muted">{data.workspace.name} / {data.project.name} / Issue #{state.issue.id}</p>
          <section className="mt-10 border-t border-line-strong pt-7">
            <h2 className="text-lg font-bold">Description</h2>
            <p className="mt-3 whitespace-pre-wrap break-words text-[15px] leading-7 text-muted">{state.issue.description || 'No description was added.'}</p>
          </section>
        </article>
      )}
    </main>
  )
}
```

Use the neutral status classes for a closed issue as we did in the list. Missing issue data
does not erase the application frame or strand the learner without a way back.

## Create one router outside React state

Open `resources/app/main.tsx`. Import the router, provider, layout, and issue page:

```tsx
import { createBrowserRouter } from 'react-router'
import { RouterProvider } from 'react-router/dom'
import { AppLayout } from './AppLayout'
import { IssuePage } from './IssuePage'
```

Inside the existing bootstrap `try`, replace the direct project element with:

```tsx
const data = readProjectPageData()
const router = createBrowserRouter([
  {
    path: '/workspaces/:workspaceId/projects/:projectId',
    element: <AppLayout data={data} />,
    children: [
      { index: true, element: <ProjectPage data={data} /> },
      { path: 'issues/:issueId', element: <IssuePage data={data} /> },
    ],
  },
])

projectScreen = <RouterProvider router={router} />
```

The router is created once before render, not inside a component update. The index child
owns the project URL; the nested dynamic child adds `issues/:issueId` beneath it.

## Prove both navigation paths

Run:

```bash
npm run typecheck
npm run lint
npm run build
php artisan serve
```

Open a project and click an issue. The address bar should change while the application
header remains mounted and no document reload occurs. Use the Back to project link and the
browser Back/Forward buttons.

Then paste an issue URL into a new tab and refresh it. DALT should return 200 with the React
shell, React should request the issue JSON, and the same detail should render. Try a missing
issue ID: the shell remains usable and the detail shows **Issue could not be loaded**. A
real issue beneath the wrong workspace or project must return 404 from the API.

If Git is available, save the routing checkpoint:

```bash
git add package.json package-lock.json routes/routes.php \
  app/Http/controllers/api/issues/show.php resources/app/AppLayout.tsx \
  resources/app/IssuePage.tsx resources/app/ProjectPage.tsx \
  resources/app/project-page-data.ts resources/app/main.tsx
git commit -m "Add client routes for issues"
```

React now owns issue locations, but the detail is read-only. Next we will move its smallest
mutation—open and closed status—through JSON and synchronize both routed screens.
