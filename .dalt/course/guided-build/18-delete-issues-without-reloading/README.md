Deletion removes the resource represented by the current route, so it needs more care than
editing or status. We will keep a separate review URL, show exactly what will disappear,
send a protected DELETE through DALT, and navigate away only after the server confirms the
row is gone.

## Give review and destruction separate routes

Open `routes/routes.php`. Add a DELETE API route and send the existing review URL to the
React shell:

```php
$router->delete('/api/workspaces/{workspace}/projects/{project}/issues/{issue}', 'api/issues/destroy.php')->only('csrf');

$router->get('/workspaces/{workspace}/projects/{project}/issues/{issue}/delete', 'projects/show.php');
```

GET still only displays a decision. The API route is both destructive and CSRF-protected.

## Delete only through the verified hierarchy

Create `app/Http/controllers/api/issues/destroy.php`. Validate the three route segments and
perform the familiar ownership walk:

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
        'SELECT id, title FROM issues WHERE id = :id AND project_id = :project_id',
        ['id' => (int) $issueId, 'project_id' => $project['id']],
    )
    ->findOrFail();
```

The title is selected before deletion so the response can name the completed action. Now
remove that one verified row:

```php
$database->query('DELETE FROM issues WHERE id = :id', ['id' => $issue['id']]);

return Response::json([
    'message' => "{$issue['title']} was deleted.",
]);
```

A second request returns 404 because the issue no longer exists. That is accurate rather
than pretending repeated deletion changed something.

## Send DELETE through DALT's method override

Open `resources/app/project-page-data.ts` and add:

```ts
export async function deleteIssue(
  data: ProjectPageData,
  issueId: number,
): Promise<string> {
  const body = new URLSearchParams({
    _token: data.form.csrfToken,
    _method: 'DELETE',
  })

  const response = await fetch(
    `/api/workspaces/${data.workspace.id}/projects/${data.project.id}/issues/${issueId}`,
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
    throw new Error('The issue could not be deleted.')
  }

  return stringAt(value, 'message')
}
```

Why send HTTP POST with `_method=DELETE`? DALT's `Request` deliberately supports form method
override, and PHP populates URL-encoded form input for POST. The router sees DELETE while
the CSRF middleware can still read the token from ordinary request input.

## Build a routed review screen

Create `resources/app/DeleteIssuePage.tsx`. Import `deleteIssue`, `fetchIssue`, router
hooks, and the shared types. Model loading exactly as the edit page does:

```tsx
type LoadState =
  | { status: 'loading' }
  | { status: 'ready'; issue: Issue }
  | { status: 'error' }

const { issueId } = useParams()
const parsedIssueId = Number(issueId)
const navigate = useNavigate()
const [state, setState] = useState<LoadState>({ status: 'loading' })
const [deleting, setDeleting] = useState(false)
const [requestError, setRequestError] = useState<string | null>(null)
```

Fetch the issue with an `AbortController`. We need the live server record on this page, not
text copied from a previous route. Then add the irreversible action:

```tsx
async function destroy() {
  setDeleting(true)
  setRequestError(null)

  try {
    const message = await deleteIssue(data, parsedIssueId)
    await navigate(projectUrl, {
      replace: true,
      state: { notice: message },
    })
  } catch {
    setRequestError('The issue could not be deleted. Check the connection and try again.')
    setDeleting(false)
  }
}
```

`replace: true` removes the now-invalid review page from this navigation entry. Failure
keeps the learner on the decision screen and restores the button.

In the ready branch, present the consequence before the action:

```tsx
<header className="mt-10">
  <h1 className="text-[clamp(36px,6vw,52px)] leading-[1.04] font-bold tracking-[-0.04em]">
    Delete this issue?
  </h1>
  <p className="mt-3 max-w-[60ch] text-sm leading-6 text-muted">
    Review the record before permanently removing it from {data.project.name}.
  </p>
</header>

<section className="mt-8 border-y border-line-strong py-6">
  <span>{state.issue.status === 'closed' ? 'Closed' : 'Open'}</span>
  <h2 className="mt-3 break-words text-xl font-bold">{state.issue.title}</h2>
  <p className="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-muted">
    {state.issue.description || 'No description was added.'}
  </p>
</section>
```

Add a red warning surface and controls:

```tsx
<section className="mt-7 rounded-[12px] border border-[#f1a9a3] bg-[#fff1f0] p-4 text-[#7a271a]">
  <h2 className="text-[15px] font-bold">This cannot be undone</h2>
  <p className="mt-2 text-sm leading-6">
    The issue will disappear from the project and its current URL will no longer load.
  </p>
</section>

{requestError && <p className="mt-5 text-sm font-semibold text-[#7a271a]" role="alert">{requestError}</p>}

<div className="mt-7 flex flex-wrap items-center gap-4">
  <button
    className="min-h-11 rounded-[9px] bg-[#b42318] px-5 py-2.5 text-sm font-bold text-white disabled:opacity-65"
    type="button"
    disabled={deleting}
    onClick={() => void destroy()}
  >
    {deleting ? 'Deleting…' : 'Delete issue'}
  </button>
  <Link to={issueUrl}>Cancel</Link>
</div>
```

Red is reserved for the irreversible action. Cancel remains clearly separate and the
button label describes what will happen.

## Connect review, detail, and project feedback

Import `DeleteIssuePage` in `main.tsx` and register:

```tsx
{ path: 'issues/:issueId/delete', element: <DeleteIssuePage data={data} /> },
```

Add a final danger section to `IssuePage`:

```tsx
<section className="mt-10 border-t border-line-strong pt-7">
  <h2 className="text-lg font-bold">Delete issue</h2>
  <p className="mt-2 text-sm leading-6 text-muted">
    Permanently remove this issue from the project.
  </p>
  <Link
    className="mt-4 inline-flex min-h-11 items-center rounded-[9px] border border-[#d92d20] px-4 py-2 text-sm font-bold text-[#a51d14] no-underline"
    to={`${projectUrl}/issues/${state.issue.id}/delete`}
  >
    Review deletion
  </Link>
</section>
```

In `ProjectPage`, read the optional router-state notice with `useLocation()` and render it
as a green `role="status"` above the grid. The project collection refetch then proves the
deleted row is absent.

## Delete a disposable issue, not valuable work

Run:

```bash
npm run typecheck
npm run lint
npm run build
php artisan serve
```

Create a disposable issue and follow Detail → Review deletion. Refresh the review URL and
verify its title, description, status, warning, Cancel link, and destructive button. Delete
it. React should return to the project without a document reload, show DALT's confirmation,
and omit the row. The removed issue API should now return 404.

Force one failed deletion against an issue you intend to keep. The review and values must
remain, a red recovery message must appear, and the button must recover. Missing CSRF must
return 419 and a crossed ownership path must return 404.

If Git is available, save the completed issue workflow:

```bash
git add routes/routes.php app/Http/controllers/api/issues/destroy.php \
  resources/app/project-page-data.ts resources/app/IssuePage.tsx \
  resources/app/DeleteIssuePage.tsx resources/app/ProjectPage.tsx \
  resources/app/main.tsx
git commit -m "Delete issues without reloading"
```

The issue workflow is now a complete routed React experience. Before migrating more
screens, we will protect its visible behavior with tests that operate it like a learner.
