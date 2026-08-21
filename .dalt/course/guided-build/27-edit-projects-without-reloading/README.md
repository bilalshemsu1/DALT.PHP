Workspace names can now evolve, but project names cannot. We will add a project edit route
inside the existing project/issue SPA, while DALT proves that both the workspace and project
IDs form one valid ownership path.

## Register the project edit boundaries

Add a deep-link shell route and a protected API route in `routes/routes.php`:

```php
$router->get(
    '/workspaces/{workspace}/projects/{project}/edit',
    'projects/show.php',
);
$router->post(
    '/api/workspaces/{workspace}/projects/{project}',
    'api/projects/update.php',
)->only('csrf');
```

The GET reuses the project shell controller. Pasting or refreshing the edit URL therefore
runs the same two-ID lookup as the project screen before React mounts.

## Update only an owned project

Create `app/Http/controllers/api/projects/update.php`. Validate both route parameters, then
resolve the parent and child separately:

```php
$workspace = $database
    ->query('SELECT id FROM workspaces WHERE id = :id', [
        'id' => (int) $workspaceId,
    ])
    ->findOrFail();
$project = $database
    ->query(
        'SELECT id FROM projects
         WHERE id = :id AND workspace_id = :workspace_id',
        [
            'id' => (int) $projectId,
            'workspace_id' => $workspace['id'],
        ],
    )
    ->findOrFail();
```

Two existing records are not enough: the second query proves this project belongs to this
workspace. Normalize and validate the name with the same rule as creation:

```php
$nameInput = $request->input('name');
$name = is_string($nameInput) ? trim($nameInput) : '';

if (!Validator::string($name, 2, 60)) {
    return Response::json([
        'errors' => ['name' => 'Use between 2 and 60 characters.'],
    ], 422);
}
```

Persist and return the confirmed value:

```php
$database->query(
    'UPDATE projects SET name = :name WHERE id = :id',
    ['name' => $name, 'id' => $project['id']],
);

return Response::json([
    'project' => ['id' => (int) $project['id'], 'name' => $name],
    'message' => "{$name} was updated.",
]);
```

## Test protection and crossed IDs

Add a feature test that sends the endpoint without CSRF, with `x`, and with a valid padded
name. Assert statuses 419, 422, and 200; assert the exact trimmed JSON; then independently
select the stored project name.

Finish the test by trying `/api/workspaces/2/projects/1`. Both rows exist in the fixture,
but project 1 belongs to workspace 1. Catch `HttpException` and assert 404. The focused file
should now report eleven tests and 99 assertions.

## Add the TypeScript request

In `resources/app/project-page-data.ts`, introduce a field-specific error:

```ts
export class ProjectNameValidationError extends Error {
  constructor(readonly errors: { name?: string }) {
    super('The project could not be updated.')
  }
}
```

Add `updateProject(data, name)`. Send `_token` and `name` as URL-encoded input to:

```ts
`/api/workspaces/${data.workspace.id}/projects/${data.project.id}`
```

Turn a runtime-checked 422 `errors.name` into `ProjectNameValidationError`. On success,
require an object containing numeric `project.id`, string `project.name`, and string
`message`. Static TypeScript types do not replace these checks because the response began
outside our program.

## Build the client edit location

Create `resources/app/EditProjectPage.tsx`. Read a newer `projectName` from route state when
available, falling back to the server bootstrap. Keep `name`, field error, request failure,
and submitting state independently.

The submit handler mirrors our workspace edit but remains inside the project router:

```tsx
const result = await updateProject(data, name)
void navigate(
  `/workspaces/${data.workspace.id}/projects/${data.project.id}`,
  {
    replace: true,
    state: {
      projectName: result.project.name,
      notice: result.message,
    },
  },
)
```

On `ProjectNameValidationError`, display the field message and preserve the attempted name.
For any other failure, display a recoverable connection message. The form uses a controlled
labelled input, `aria-invalid`, a `role="alert"` field error, `Saving…` while disabled, and
React Router links for Back and Cancel.

Register the location in `resources/app/main.tsx`:

```tsx
children: [
  { index: true, element: <ProjectPage data={data} /> },
  { path: 'edit', element: <EditProjectPage data={data} /> },
  // Existing issue routes continue here.
],
```

## Return with the confirmed name

In `ProjectPage`, derive `routedProjectName` from `useLocation().state` with
`data.project.name` as fallback. Render it in the heading and add:

```tsx
<Link to="edit" state={{ projectName: routedProjectName }}>
  Edit project
</Link>
```

The existing routed notice already displays the update message. React shows the confirmed
name without a reload; refreshing proves the shell controller reads the same name from the
database.

Extend `resources/app/issue-workflow.test.tsx` with the edit child route. Let MSW reject the
first POST and accept the second. Drive the real `Edit project` link, assert `x` survives
the 422, then assert the confirmed heading and status after saving `Release API`.

Run all boundaries:

```bash
php artisan test tests/Feature/IssueApiTest.php
npm run typecheck
npm run lint
npm test
npm run build
```

The frontend should report fourteen tests. In the browser, the invalid request remains on
the edit URL; success returns to the project URL without a document reload. Refresh and
confirm the updated name remains while all issues are untouched.

```bash
git add routes/routes.php app/Http/controllers/api/projects/update.php \
  resources/app tests/Feature/IssueApiTest.php
git commit -m "Edit projects without reloading"
```

Next we will complete project management with a reviewed deletion that removes the
project's issues in one transaction and returns to its workspace.
