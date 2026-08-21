Editing deserves its own location because it is a focused task with a safe exit. We already
proved that pattern in PHP. Now we will let React own the same `/edit` URL, load the current
issue, preserve rejected values, and return to the detail only after DALT confirms the
update.

## Serve the React shell for the edit URL

Open `routes/routes.php`. Change the edit GET route to the project shell controller and add
a protected API update route:

```php
$router->post('/api/workspaces/{workspace}/projects/{project}/issues/{issue}', 'api/issues/update.php')->only('csrf');

$router->get('/workspaces/{workspace}/projects/{project}/issues/{issue}', 'projects/show.php');
$router->get('/workspaces/{workspace}/projects/{project}/issues/{issue}/edit', 'projects/show.php');
```

Refreshing `/edit` now receives the same project bootstrap and Vite entry. POST remains a
separate CSRF-protected server boundary.

## Return an updated issue or field errors

Create `app/Http/controllers/api/issues/update.php`. Start with `Request`, `Database`,
`Response`, and `Validator`, validate all three positive IDs, then repeat the nested lookup:

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
        'SELECT id, status FROM issues WHERE id = :id AND project_id = :project_id',
        ['id' => (int) $issueId, 'project_id' => $project['id']],
    )
    ->findOrFail();
```

We select the existing status because editing content must not silently reopen or close an
issue. Read and validate title and description exactly as creation does:

```php
$titleInput = $request->input('title');
$descriptionInput = $request->input('description');
$title = is_string($titleInput) ? trim($titleInput) : '';
$description = is_string($descriptionInput) ? trim($descriptionInput) : '';
$errors = [];

if (!Validator::string($title, 2, 100)) {
    $errors['title'] = 'Use between 2 and 100 characters.';
}

if (!Validator::string($description, 0, 1000)) {
    $errors['description'] = 'Keep the description under 1,000 characters.';
}

if ($errors !== []) {
    return Response::json(['errors' => $errors], 422);
}
```

Apply the update and return the complete confirmed record:

```php
$database->query(
    'UPDATE issues SET title = :title, description = :description WHERE id = :id',
    ['title' => $title, 'description' => $description, 'id' => $issue['id']],
);

return Response::json([
    'issue' => [
        'id' => (int) ($issue['id'] ?? 0),
        'title' => $title,
        'description' => $description,
        'status' => (string) ($issue['status'] ?? ''),
    ],
    'message' => "{$title} was updated.",
]);
```

The issue identity, parent, and status survive unchanged.

## Add the typed update request

Open `resources/app/project-page-data.ts` and add:

```ts
export async function updateIssue(
  data: ProjectPageData,
  issueId: number,
  values: { title: string; description: string },
): Promise<IssueMutationResult> {
  const body = new URLSearchParams({
    _token: data.form.csrfToken,
    title: values.title,
    description: values.description,
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

  if (response.status === 422) {
    if (!isRecord(value)) throw new Error('The validation response is invalid.')
    throw new IssueValidationError(parseFieldErrors(value.errors))
  }

  if (!response.ok || !isRecord(value)) {
    throw new Error('The issue could not be updated.')
  }

  return {
    issue: parseIssue(value.issue),
    message: stringAt(value, 'message'),
  }
}
```

Creation and editing now share the same runtime field-error type without sharing a
component or pretending they are the same user task.

## Build the routed edit screen

Create `resources/app/EditIssuePage.tsx`. Import the hooks, API functions, and types:

```tsx
import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { fetchIssue, IssueValidationError, updateIssue } from './project-page-data'
import type { FieldErrors, ProjectPageData } from './project-page-data'

type LoadState = 'loading' | 'ready' | 'error'
```

Inside `EditIssuePage`, parse the route ID and create separate loading, field, mutation,
and request-error state:

```tsx
const { issueId } = useParams()
const parsedIssueId = Number(issueId)
const navigate = useNavigate()
const [loadState, setLoadState] = useState<LoadState>('loading')
const [title, setTitle] = useState('')
const [description, setDescription] = useState('')
const [errors, setErrors] = useState<FieldErrors>({})
const [saving, setSaving] = useState(false)
const [requestError, setRequestError] = useState<string | null>(null)

const issueUrl = `/workspaces/${data.workspace.id}/projects/${data.project.id}/issues/${parsedIssueId}`
```

Load the record and seed the controlled fields only when the response is valid:

```tsx
useEffect(() => {
  const controller = new AbortController()

  if (!Number.isInteger(parsedIssueId) || parsedIssueId < 1) {
    setLoadState('error')
    return () => controller.abort()
  }

  void fetchIssue(data.workspace.id, data.project.id, parsedIssueId, controller.signal)
    .then((issue) => {
      setTitle(issue.title)
      setDescription(issue.description)
      setLoadState('ready')
    })
    .catch((error: unknown) => {
      if (!(error instanceof DOMException && error.name === 'AbortError')) {
        setLoadState('error')
      }
    })

  return () => controller.abort()
}, [data.workspace.id, data.project.id, parsedIssueId])
```

Submit without clearing the form first:

```tsx
async function submit(event: React.FormEvent<HTMLFormElement>) {
  event.preventDefault()
  setSaving(true)
  setErrors({})
  setRequestError(null)

  try {
    const result = await updateIssue(data, parsedIssueId, { title, description })
    await navigate(issueUrl, { state: { notice: result.message } })
  } catch (error) {
    if (error instanceof IssueValidationError) {
      setErrors(error.errors)
    } else {
      setRequestError('The issue could not be updated. Check the connection and try again.')
    }
  } finally {
    setSaving(false)
  }
}
```

Validation and network failure leave both controlled values untouched. Successful
navigation carries a short confirmation to the detail screen through router state.

Render a narrow page with **Back to issue**, an `Edit issue` heading, and honest load/error
branches. In the ready branch, use the same accessible field pattern as creation:

```tsx
<form className="mt-8" onSubmit={(event) => void submit(event)} noValidate>
  <label className="mb-2 block text-sm font-bold" htmlFor="edit-issue-title">Title</label>
  <input
    id="edit-issue-title"
    className="min-h-11 w-full rounded-[9px] border border-line-strong px-3 py-2.5 text-base"
    value={title}
    onChange={(event) => setTitle(event.target.value)}
    aria-invalid={errors.title !== undefined}
    aria-describedby={errors.title ? 'edit-title-error' : undefined}
  />
  {errors.title && <p id="edit-title-error" role="alert">{errors.title}</p>}

  <label className="mt-5 mb-2 block text-sm font-bold" htmlFor="edit-issue-description">
    Description <span className="text-xs font-normal text-muted">(optional)</span>
  </label>
  <textarea
    id="edit-issue-description"
    className="min-h-[160px] w-full resize-y rounded-[9px] border border-line-strong px-3 py-2.5 text-base leading-6"
    value={description}
    onChange={(event) => setDescription(event.target.value)}
    aria-invalid={errors.description !== undefined}
  />

  <div className="mt-7 flex flex-wrap items-center gap-4">
    <button type="submit" disabled={saving}>
      {saving ? 'Saving…' : 'Save changes'}
    </button>
    <Link to={issueUrl}>Cancel</Link>
  </div>
</form>
```

Retain the established focus, error, button, and Tailwind classes from the create form.
Render `requestError` above the form as a red `role="alert"`.

## Connect the new route and entry point

In `main.tsx`, import `EditIssuePage` and add the child after issue detail:

```tsx
{ path: 'issues/:issueId', element: <IssuePage data={data} /> },
{ path: 'issues/:issueId/edit', element: <EditIssuePage data={data} /> },
```

In `IssuePage.tsx`, add an Edit issue link after Description:

```tsx
<Link
  className="inline-flex min-h-11 items-center rounded-[9px] border border-line-strong bg-surface px-4 py-2 text-sm font-bold text-ink no-underline"
  to={`${projectUrl}/issues/${state.issue.id}/edit`}
>
  Edit issue
</Link>
```

Use `useLocation()` to read the optional successful edit notice when the detail mounts, and
initialize its existing notice state with the green success tone. Refreshing the detail
still works without that temporary message because the data itself comes from DALT.

## Prove validation, recovery, and identity

Run:

```bash
npm run typecheck
npm run lint
npm run build
php artisan serve
```

Open an issue, follow Edit issue, and refresh the edit URL. Both stored values must load.
Submit an invalid title and overlong description: both errors appear, both attempts remain,
and the URL stays on `/edit`. Save valid content: React navigates to detail without a
document reload, the confirmation appears, and the new content survives refresh.

Force a failed update once. The edit URL and attempted values must remain, the red recovery
message must appear, and Save changes must become enabled again. Direct requests should
prove missing CSRF returns 419 and crossed ownership returns 404. Finally confirm editing
did not change the issue ID, project, or status.

If Git is available, save the checkpoint:

```bash
git add routes/routes.php app/Http/controllers/api/issues/update.php \
  resources/app/project-page-data.ts resources/app/IssuePage.tsx \
  resources/app/EditIssuePage.tsx resources/app/main.tsx
git commit -m "Edit issues without reloading"
```

Issue content and status are now fully interactive. The final issue mutation is deletion,
which needs a deliberate review screen because success removes the current route entirely.
