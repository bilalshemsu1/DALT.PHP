The project page now reads issues through JSON, but creating one still submits an HTML
form and reloads the document. We will give that write operation a JSON boundary too.
DALT will remain responsible for CSRF, validation, ownership, and persistence. React will
keep the attempted values on screen and update the list only after DALT confirms the row.

## Give the API a protected write route

Open `routes/routes.php` and add a POST route beside the issue collection's GET route:

```php
$router->get('/api/workspaces/{workspace}/projects/{project}/issues', 'api/issues/index.php');
$router->post('/api/workspaces/{workspace}/projects/{project}/issues', 'api/issues/store.php')->only('csrf');
```

Both methods address the same collection. GET reads it; POST creates a member. Only POST
changes state, so only POST carries the CSRF middleware.

Create `app/Http/controllers/api/issues/store.php`. Begin with the same nested boundary
as our other issue controllers:

```php
<?php

declare(strict_types=1);

use Core\App;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Validator;

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

Then verify that the project really belongs to the workspace in this URL:

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
```

An API does not weaken our ownership rules. A valid project beneath the wrong workspace
still returns 404 before any insert is attempted.

## Return validation instead of redirecting it

Read, normalize, and validate the two fields:

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

The rules remain on the server. React may improve feedback, but it cannot be trusted to
protect a database. Status 422 means DALT understood the request and its media type but
could not process these field values. Returning every error together lets the learner fix
the form in one pass.

Insert the issue and return the stored representation:

```php
$database->query(
    'INSERT INTO issues (project_id, title, description, status)
     VALUES (:project_id, :title, :description, :status)',
    [
        'project_id' => $project['id'],
        'title' => $title,
        'description' => $description,
        'status' => 'open',
    ],
);

return Response::json([
    'issue' => [
        'id' => (int) $database->getConnection()->lastInsertId(),
        'title' => $title,
        'description' => $description,
        'status' => 'open',
    ],
    'message' => "{$title} was created.",
], 201);
```

React does not invent an ID or assume a status. The 201 response carries the exact record
DALT accepted, including the database-generated ID.

## Make the document bootstrap smaller

Validation no longer travels through a redirect and session flash. Open
`resources/views/projects/show.view.php`. Remove the `$errors`, `$success`, `old()` values,
and their corresponding entries from `$projectPageData`. Keep the form token:

```php
$projectPageData = [
    'workspace' => ['id' => $workspaceId, 'name' => $workspaceName],
    'project' => ['id' => $projectId, 'name' => $projectName],
    'form' => ['csrfToken' => csrf_token()],
];
```

The HTML shell now contains stable page context. Request-specific creation state belongs
to the React form that initiated the request.

## Describe both successful and rejected responses

Open `resources/app/project-page-data.ts`. Reduce the form portion of
`ProjectPageData` and add the result types:

```ts
export type FieldErrors = {
  title?: string
  description?: string
}

export type ProjectPageData = {
  workspace: { id: number; name: string }
  project: { id: number; name: string }
  form: { csrfToken: string }
}

export type CreateIssueResult = {
  issue: Issue
  message: string
}

export class IssueValidationError extends Error {
  constructor(readonly errors: FieldErrors) {
    super('The issue could not be created.')
  }
}
```

Update `parseProjectPageData()` so `form` returns only `csrfToken`. Add a small optional
string parser and use it for field errors:

```ts
function optionalStringAt(record: Record<string, unknown>, key: string) {
  const value = record[key]
  if (value === undefined || value === null) return undefined
  if (typeof value !== 'string') throw new Error(`Project data has an invalid ${key}.`)
  return value
}

function parseFieldErrors(value: unknown): FieldErrors {
  if (!isRecord(value)) throw new Error('The validation response is invalid.')
  return {
    title: optionalStringAt(value, 'title'),
    description: optionalStringAt(value, 'description'),
  }
}
```

Now add the write request:

```ts
export async function createIssue(
  data: ProjectPageData,
  values: { title: string; description: string },
): Promise<CreateIssueResult> {
  const body = new URLSearchParams({
    _token: data.form.csrfToken,
    title: values.title,
    description: values.description,
  })

  const response = await fetch(
    `/api/workspaces/${data.workspace.id}/projects/${data.project.id}/issues`,
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
    throw new Error('The issue could not be created.')
  }

  return {
    issue: parseIssue(value.issue),
    message: stringAt(value, 'message'),
  }
}
```

URL-encoded input works with DALT's existing request object and still receives JSON. Both
the success and validation bodies cross runtime parsers before components use them.

## Lift the collection to their common owner

The issue list currently owns its own state, so its sibling form cannot add a confirmed
issue. Open `resources/app/ProjectPage.tsx`. Move `attempt`, `issuesState`, and the fetch
effect from `IssuesPanel` into `ProjectPage`. Then make the panel receive them as props:

```tsx
function IssuesPanel({ state, workspaceId, projectId, retry }: {
  state: IssuesState
  workspaceId: number
  projectId: number
  retry: () => void
}) {
  // Keep the existing loading, failure, empty, and list branches here.
}
```

Inside `ProjectPage`, add the confirmed issue without refetching the document:

```tsx
function addIssue(issue: Issue) {
  setIssuesState((current) => current.status === 'ready'
    ? { status: 'ready', issues: [issue, ...current.issues] }
    : { status: 'ready', issues: [issue] })
}
```

This is not an optimistic insert: `addIssue` receives the parsed 201 response. Server truth
is placed first because the API and database list newest issues first.

## Turn the form into controlled React state

Import `createIssue`, `IssueValidationError`, and `FieldErrors`. Replace the old native
form state with:

```tsx
function CreateIssueForm({ data, onCreated }: {
  data: ProjectPageData
  onCreated: (issue: Issue) => void
}) {
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [notice, setNotice] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSubmitting(true)
    setErrors({})
    setNotice(null)

    try {
      const result = await createIssue(data, { title, description })
      onCreated(result.issue)
      setTitle('')
      setDescription('')
      setNotice(result.message)
    } catch (error) {
      if (error instanceof IssueValidationError) {
        setErrors(error.errors)
      } else {
        setNotice('The issue could not be created. Check the connection and try again.')
      }
    } finally {
      setSubmitting(false)
    }
  }
```

Connect the fields with `value` and `onChange`, keep their `aria-invalid` and error
relationships, and change the form opening tag:

```tsx
<form onSubmit={(event) => void submit(event)} noValidate>
  <input
    id="issue-title"
    name="title"
    value={title}
    onChange={(event) => setTitle(event.target.value)}
    aria-invalid={errors.title !== undefined}
    aria-describedby={errors.title ? 'issue-title-error' : undefined}
  />

  <textarea
    id="issue-description"
    name="description"
    value={description}
    onChange={(event) => setDescription(event.target.value)}
    aria-invalid={errors.description !== undefined}
  />

  <button type="submit" disabled={submitting}>
    {submitting ? 'Creating…' : 'Create issue'}
  </button>
</form>
```

Keep the Tailwind classes from the previous form. `noValidate` lets our server return the
same errors for every client. The button prevents a second submission while the first is
in flight, and attempted values remain controlled until creation succeeds.

Render the response message above the form with `role="status"`, and pass the shared
callback from `ProjectPage`:

```tsx
<CreateIssueForm data={data} onCreated={addIssue} />
```

## Prove there was no navigation

Run the normal frontend gate:

```bash
npm run typecheck
npm run lint
npm run build
php artisan serve
```

In the browser, submit a one-character title and an overlong description. Both server
errors should appear together, the attempted values should remain, and the URL should not
change. Submit a valid issue next. The button should briefly read **Creating…**, the fields
should clear, the new issue should appear first, and the success message should use DALT's
response. Refresh once to prove the same row came from SQLite rather than temporary state.

Also verify the boundaries directly: a POST without a token returns 419, invalid fields
return 422, a crossed workspace/project path returns 404, and a valid creation returns 201.

If Git is available, save the application checkpoint:

```bash
git add routes/routes.php app/Http/controllers/api/issues/store.php \
  resources/views/projects/show.view.php resources/app/project-page-data.ts \
  resources/app/ProjectPage.tsx
git commit -m "Create issues without reloading"
```

Our list and form now behave as one React screen. The next missing boundary is location:
issue links still leave React for PHP pages. We will add real client routes only now that
two React views need distinct URLs.
