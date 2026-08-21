Our workspace detail now loads projects through JSON, but its create form still reloads the
document. We will give that nested mutation the same server-confirmed React flow as workspace
creation, while keeping the workspace ownership check inside DALT.

## Add the nested create endpoint

Register a protected POST route beside the project collection in `routes/routes.php`:

```php
$router->get('/api/workspaces/{workspace}/projects', 'api/projects/index.php');
$router->post(
    '/api/workspaces/{workspace}/projects',
    'api/projects/store.php',
)->only('csrf');
```

Create `app/Http/controllers/api/projects/store.php`. Validate the route parameter and find
the parent before reading the new project:

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

if (!is_string($workspaceId)
    || preg_match('/\A[1-9]\d*\z/', $workspaceId) !== 1) {
    abort(404);
}

$database = App::resolve(Database::class);
$workspace = $database
    ->query('SELECT id FROM workspaces WHERE id = :id', [
        'id' => (int) $workspaceId,
    ])
    ->findOrFail();
```

The URL supplies context, not trusted ownership. Looking up the workspace makes a missing
parent a real 404 and gives the insert a confirmed foreign key value.

Normalize and validate the name:

```php
$nameInput = $request->input('name');
$name = is_string($nameInput) ? trim($nameInput) : '';

if (!Validator::string($name, 2, 60)) {
    return Response::json([
        'errors' => ['name' => 'Use between 2 and 60 characters.'],
    ], 422);
}
```

Insert with the confirmed workspace ID and return the list-row shape:

```php
$database->query(
    'INSERT INTO projects (workspace_id, name)
     VALUES (:workspace_id, :name)',
    ['workspace_id' => $workspace['id'], 'name' => $name],
);

return Response::json([
    'project' => [
        'id' => (int) $database->getConnection()->lastInsertId(),
        'name' => $name,
        'issueCount' => 0,
    ],
    'message' => "{$name} was created.",
], 201);
```

## Prove the API stores the relationship

Add a feature test to `tests/Feature/IssueApiTest.php`:

```php
test('project creation is protected scoped validated and persisted', function () {
    $router = issueApiRouter();
    $uri = '/api/workspaces/1/projects';

    $missingToken = issueApiRequest(
        $router, 'POST', $uri, ['name' => 'Protected'], withCsrf: false,
    );
    $invalid = issueApiRequest($router, 'POST', $uri, ['name' => 'x']);
    $created = issueApiRequest($router, 'POST', $uri, ['name' => '  API  ']);
    $createdBody = json_decode(
        $created->content(), true, flags: JSON_THROW_ON_ERROR,
    );
```

Read the inserted row independently and assert both columns:

```php
    $stored = issueApiDatabase()
        ->query('SELECT workspace_id, name FROM projects WHERE id = :id', [
            'id' => $createdBody['project']['id'],
        ])
        ->find();

    expect($missingToken->status())->toBe(419)
        ->and($invalid->status())->toBe(422)
        ->and(json_decode(
            $invalid->content(), true, flags: JSON_THROW_ON_ERROR,
        ))->toBe([
            'errors' => ['name' => 'Use between 2 and 60 characters.'],
        ])
        ->and($created->status())->toBe(201)
        ->and($createdBody['project'])->toMatchArray([
            'name' => 'API',
            'issueCount' => 0,
        ])
        ->and((int) $stored['workspace_id'])->toBe(1)
        ->and($stored['name'])->toBe('API');
});
```

This catches a response that looks right but stores the project under the wrong workspace.

## Add the TypeScript mutation

In `resources/app/workspace-detail-data.ts`, simplify bootstrap data now that React will own
the form lifecycle:

```ts
export type WorkspaceDetailPageData = {
  workspace: { id: number; name: string }
  form: { csrfToken: string }
}

export class ProjectValidationError extends Error {
  constructor(readonly errors: { name?: string }) {
    super('The project could not be created.')
  }
}
```

Add `createProject()`:

```ts
export async function createProject(
  data: WorkspaceDetailPageData,
  name: string,
): Promise<{ project: ProjectSummary; message: string }> {
  const body = new URLSearchParams({
    _token: data.form.csrfToken,
    name,
  })
  const response = await fetch(
    '/api/workspaces/' + data.workspace.id + '/projects',
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
```

Convert a valid 422 body into the dedicated error, then reuse our project-list parser on a
successful item:

```ts
  if (response.status === 422) {
    if (!isRecord(value) || !isRecord(value.errors)) {
      throw new Error('The validation response is invalid.')
    }
    const nameError = value.errors.name
    if (nameError !== undefined && typeof nameError !== 'string') {
      throw new Error('The validation response is invalid.')
    }
    throw new ProjectValidationError({ name: nameError })
  }

  if (!response.ok || !isRecord(value)) {
    throw new Error('The project could not be created.')
  }

  return {
    project: parseProjectsResponse({ projects: [value.project] })[0],
    message: stringAt(value, 'message'),
  }
}
```

## Make the form React-owned

In `resources/app/WorkspaceDetailPage.tsx`, create a `CreateProjectForm` with controlled
`name`, field-error, notice, and submitting state. Its submit handler follows the response,
not optimism:

```tsx
async function submit(event: React.FormEvent<HTMLFormElement>) {
  event.preventDefault()
  setSubmitting(true)
  setNameError(null)
  setNotice(null)

  try {
    const result = await createProject(data, name)
    onCreated(result.project)
    setName('')
    setNotice({ tone: 'success', message: result.message })
  } catch (error) {
    if (error instanceof ProjectValidationError) {
      setNameError(error.errors.name ?? 'Check the project name.')
    } else {
      setNotice({
        tone: 'error',
        message: 'The project could not be created. Check the connection and try again.',
      })
    }
  } finally {
    setSubmitting(false)
  }
}
```

Replace the native form attributes with a controlled form:

```tsx
<form onSubmit={(event) => void submit(event)} noValidate>
  <label htmlFor="project-name">Project name</label>
  <input
    id="project-name"
    name="name"
    type="text"
    value={name}
    onChange={(event) => setName(event.target.value)}
    aria-invalid={nameError !== null}
    aria-describedby={nameError !== null ? 'project-name-error' : undefined}
  />
  {nameError !== null && (
    <p id="project-name-error" role="alert">{nameError}</p>
  )}
  <button type="submit" disabled={submitting}>
    {submitting ? 'Creating…' : 'Create project'}
  </button>
</form>
```

Let the page own the collection update:

```tsx
function addProject(project: ProjectSummary) {
  setState((current) => current.status === 'ready'
    ? { status: 'ready', projects: [project, ...current.projects] }
    : { status: 'ready', projects: [project] })
}

// In the layout:
<CreateProjectForm data={data} onCreated={addProject} />
```

The new row is first because both the API collection and our UI use newest-first order.

## Remove obsolete flash bootstrap

In `resources/views/workspaces/show.view.php`, keep only the confirmed workspace identity
and CSRF token:

```php
$pageData = json_encode(
    [
        'workspace' => ['id' => $workspaceId, 'name' => $workspaceName],
        'form' => ['csrfToken' => csrf_token()],
    ],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        | JSON_THROW_ON_ERROR,
);
```

Old input and flash messages are no longer lost; their lifecycle now lives in the mounted
component, so PHP no longer needs to serialize them.

## Test rejection and confirmation

Update the workspace-detail test data to contain only `csrfToken`. Then add an MSW test
whose first POST returns 422 and whose second returns 201:

```tsx
server.use(
  http.get('/api/workspaces/2/projects', () => HttpResponse.json({ projects: [] })),
  http.post('/api/workspaces/2/projects', async ({ request }) => {
    attempts += 1
    const body = await request.formData()
    expect(body.get('_token')).toBe('test-token')
    if (attempts === 1) {
      return HttpResponse.json({
        errors: { name: 'Use between 2 and 60 characters.' },
      }, { status: 422 })
    }
    return HttpResponse.json({
      project: { id: 9, name: String(body.get('name')).trim(), issueCount: 0 },
      message: 'API was created.',
    }, { status: 201 })
  }),
)
```

Drive `x`, assert the alert and preserved value, then submit `API`. Assert the new link is
`/workspaces/2/projects/9`, its count is `0 issues`, the success status is announced, and
the input is empty.

Run the application boundaries:

```bash
php artisan test tests/Feature/IssueApiTest.php
npm run typecheck
npm run lint
npm test
npm run build
```

The backend file should report eight tests and 73 assertions; the frontend should report
eleven tests. In the browser, invalid and valid submissions should stay on the same
workspace URL. A confirmed project appears immediately with `0 issues` and an updated total.

If Git is available, save the checkpoint:

```bash
git add routes/routes.php app/Http/controllers/api/projects/store.php \
  resources/app/WorkspaceDetailPage.tsx resources/app/workspace-detail-data.ts \
  resources/app/workspace-detail-workflow.test.tsx \
  resources/views/workspaces/show.view.php tests/Feature/IssueApiTest.php
git commit -m "Create projects without reloading"
```

The workspace and project creation paths are now fully React-owned. Next we will add the
first missing management action: editing a workspace name from a dedicated client route.
