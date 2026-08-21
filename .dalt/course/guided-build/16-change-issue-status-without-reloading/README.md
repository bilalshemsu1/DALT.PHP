Our routed issue screen can now load itself, but changing its status still belongs to the
old PHP page. We will move that one mutation into the React detail. The browser will request
an explicit target state, DALT will enforce the nested resource boundary, and React will
replace its issue only with the confirmed response.

## Add a protected status endpoint

Open `routes/routes.php` and add:

```php
$router->get('/api/workspaces/{workspace}/projects/{project}/issues/{issue}', 'api/issues/show.php');
$router->post('/api/workspaces/{workspace}/projects/{project}/issues/{issue}/status', 'api/issues/status.php')->only('csrf');
```

We use a separate `/status` action because this request changes one deliberate aspect of
an issue. It remains POST and CSRF-protected.

Create `app/Http/controllers/api/issues/status.php`. Read and validate all three IDs, then
verify workspace → project → issue exactly as the detail endpoint does:

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

Now validate the requested transition:

```php
$status = $request->input('status');

if (!is_string($status) || !in_array($status, ['open', 'closed'], true)) {
    return Response::json(['message' => 'Status must be open or closed.'], 422);
}
```

The client sends the desired result rather than a vague `toggle`. Explicit state makes a
repeated request safe: asking an already closed issue to be closed leaves it closed.

Update only when needed and return the complete issue:

```php
if ($issue['status'] !== $status) {
    $database->query(
        'UPDATE issues SET status = :status WHERE id = :id',
        ['status' => $status, 'id' => $issue['id']],
    );
}

$verb = $status === 'closed' ? 'closed' : 'reopened';

return Response::json([
    'issue' => [
        'id' => (int) ($issue['id'] ?? 0),
        'title' => (string) ($issue['title'] ?? ''),
        'description' => (string) ($issue['description'] ?? ''),
        'status' => $status,
    ],
    'message' => $issue['status'] === $status
        ? "{$issue['title']} is already {$status}."
        : "{$issue['title']} was {$verb}.",
]);
```

Returning every field lets the client replace its snapshot from server truth. Repeating
the same request still returns 200 and explains that the state already matched.

## Add the status request to the frontend boundary

Open `resources/app/project-page-data.ts`. The creation and status responses share an
issue plus a message, so name that reusable result:

```ts
export type IssueMutationResult = CreateIssueResult
```

Then add:

```ts
export async function changeIssueStatus(
  data: ProjectPageData,
  issueId: number,
  status: IssueStatus,
): Promise<IssueMutationResult> {
  const body = new URLSearchParams({
    _token: data.form.csrfToken,
    status,
  })

  const response = await fetch(
    `/api/workspaces/${data.workspace.id}/projects/${data.project.id}/issues/${issueId}/status`,
    {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body,
    },
  )

  const value: unknown = await response.json()

  if (!response.ok || !isRecord(value)) {
    throw new Error('The issue status could not be changed.')
  }

  return {
    issue: parseIssue(value.issue),
    message: stringAt(value, 'message'),
  }
}
```

The CSRF token still comes from the DALT shell. An unexpected response never reaches the
screen as a trusted `Issue`.

## Give the detail a mutation state

Open `resources/app/IssuePage.tsx`. Import `changeIssueStatus`, then add state beside the
existing load state:

```tsx
const [changingStatus, setChangingStatus] = useState(false)
const [notice, setNotice] = useState<{
  tone: 'success' | 'error'
  message: string
} | null>(null)
```

Use different tones because a failed write must not look like a green confirmation. Add
the mutation function:

```tsx
async function toggleStatus(issue: Issue) {
  setChangingStatus(true)
  setNotice(null)

  try {
    const status = issue.status === 'open' ? 'closed' : 'open'
    const result = await changeIssueStatus(data, issue.id, status)
    setState({ status: 'ready', issue: result.issue })
    setNotice({ tone: 'success', message: result.message })
  } catch {
    setNotice({
      tone: 'error',
      message: 'The issue status could not be changed. Check the connection and try again.',
    })
  } finally {
    setChangingStatus(false)
  }
}
```

We do not flip the badge before the request completes. If the network or server fails, the
visible status remains the last confirmed state and the button becomes usable again.

Render the notice above the issue header:

```tsx
{notice !== null && (
  <p
    className={notice.tone === 'success'
      ? 'mb-6 rounded-[9px] bg-accent-soft px-3 py-2.5 text-sm font-semibold text-[#075b43]'
      : 'mb-6 rounded-[9px] border border-[#f1a9a3] bg-[#fff1f0] px-3 py-2.5 text-sm font-semibold text-[#7a271a]'}
    role={notice.tone === 'success' ? 'status' : 'alert'}
  >
    {notice.message}
  </p>
)}
```

Success is a polite status update. Failure is an alert with the problem and recovery.

After the Description section, add the action:

```tsx
<section className="mt-10 border-t border-line-strong pt-7" aria-labelledby="status-title">
  <h2 id="status-title" className="text-lg font-bold">Status</h2>
  <p className="mt-2 text-sm leading-6 text-muted">
    {state.issue.status === 'open'
      ? 'Close this issue when the work is complete.'
      : 'Reopen this issue when more work is needed.'}
  </p>
  <button
    className="mt-4 min-h-11 rounded-[9px] bg-accent px-5 py-2.5 text-sm font-bold text-white disabled:cursor-wait disabled:opacity-65"
    type="button"
    disabled={changingStatus}
    onClick={() => void toggleStatus(state.issue)}
  >
    {changingStatus
      ? 'Saving…'
      : state.issue.status === 'open' ? 'Close issue' : 'Reopen issue'}
  </button>
</section>
```

The label always names the next action. The disabled state prevents two competing writes.

## Prove the detail and list agree

Run:

```bash
npm run typecheck
npm run lint
npm run build
php artisan serve
```

Open an issue and close it. The URL must stay unchanged, the badge and button should switch,
and DALT's confirmation should appear. Return to the project through the client link: its
fresh collection request should show the same closed status. Reopen the issue and repeat.

Temporarily break the status endpoint and try once. The issue must retain its old status,
the failure alert must be red rather than green, and the button must recover. Direct checks
should also prove missing CSRF returns 419, an unsupported status returns 422, and a crossed
parent path returns 404.

If Git is available, save this checkpoint:

```bash
git add routes/routes.php app/Http/controllers/api/issues/status.php \
  resources/app/project-page-data.ts resources/app/IssuePage.tsx \
  resources/app/ProjectPage.tsx
git commit -m "Change issue status without reloading"
```

The routed issue can now be read and moved through its workflow. Next we will edit its
title and description in React while preserving the same accumulated server validation.
