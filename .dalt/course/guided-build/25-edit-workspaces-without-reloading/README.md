Creation is complete, but a mistaken workspace name is permanent. We will add a dedicated
React edit location, persist a validated rename in DALT, and return to the detail screen
with the confirmed name without reloading.

## Serve the client route and API

Add both routes in `routes/routes.php`:

```php
$router->get('/workspaces/{workspace}/edit', 'workspaces/show.php');
$router->post('/api/workspaces/{workspace}', 'api/workspaces/update.php')
    ->only('csrf');
```

The GET reuses the existing shell controller, so a direct visit still validates the ID and
returns 404 for a missing workspace. Create `app/Http/controllers/api/workspaces/update.php`
and repeat that ownership lookup before accepting input:

```php
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

Normalize, validate, update, and return the stored public shape:

```php
$nameInput = $request->input('name');
$name = is_string($nameInput) ? trim($nameInput) : '';

if (!Validator::string($name, 2, 50)) {
    return Response::json([
        'errors' => ['name' => 'Use between 2 and 50 characters.'],
    ], 422);
}

$database->query(
    'UPDATE workspaces SET name = :name WHERE id = :id',
    ['name' => $name, 'id' => $workspace['id']],
);

return Response::json([
    'workspace' => ['id' => (int) $workspace['id'], 'name' => $name],
    'message' => "{$name} was updated.",
]);
```

## Add and test the browser request

In `resources/app/workspace-detail-data.ts`, add `WorkspaceNameValidationError` and an
`updateWorkspace(data, name)` request. Send `_token` and `name` with `URLSearchParams`, turn
a valid 422 body into the dedicated error, and runtime-check `workspace.id`,
`workspace.name`, and `message` before returning them.

The core request is:

```ts
const response = await fetch('/api/workspaces/' + data.workspace.id, {
  method: 'POST',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/x-www-form-urlencoded',
  },
  body: new URLSearchParams({ _token: data.form.csrfToken, name }),
})
```

Add a feature test that sends the route without CSRF, with `x`, and with
`  Product Studio  `. Assert 419, 422, the trimmed JSON result, and an independent
`SELECT name FROM workspaces WHERE id = 1`. The focused backend file should now report nine
tests and 81 assertions.

## Build the edit screen

Create `resources/app/EditWorkspacePage.tsx`. Initialize controlled `name` from navigation
state when present, otherwise from the PHP bootstrap. Submit through `updateWorkspace`:

```tsx
async function submit(event: React.FormEvent<HTMLFormElement>) {
  event.preventDefault()
  setSubmitting(true)
  setNameError(null)
  setNotice(null)

  try {
    const result = await updateWorkspace(data, name)
    void navigate('/workspaces/' + data.workspace.id, {
      replace: true,
      state: {
        workspaceName: result.workspace.name,
        notice: result.message,
      },
    })
  } catch (error) {
    if (error instanceof WorkspaceNameValidationError) {
      setNameError(error.errors.name ?? 'Check the workspace name.')
    } else {
      setNotice('The workspace could not be updated. Check the connection and try again.')
    }
  } finally {
    setSubmitting(false)
  }
}
```

Render a labelled controlled input, its `role="alert"` validation message, a disabled
`Saving…` submit state, and a React Router cancel link back to the workspace. Attempted
invalid input stays in the field.

Register the page in the workspace branch of `resources/app/main.tsx`:

```tsx
children: [
  { index: true, element: <WorkspaceDetailPage data={data} /> },
  { path: 'edit', element: <EditWorkspacePage data={data} /> },
],
```

## Carry confirmed state back

In `WorkspaceDetailPage`, read `workspaceName` and `notice` from `useLocation().state`,
falling back to bootstrap data. Add this client link beside the heading:

```tsx
<Link to="edit" state={{ workspaceName }}>
  Edit workspace
</Link>
```

After saving, React renders the confirmed name immediately. Refreshing the URL discards
navigation state and proves the same name comes from SQLite through the shell controller.

Extend `workspace-detail-workflow.test.tsx`: navigate through `Edit workspace`, reject `x`
and assert it remains, then accept `Product Studio`. Assert the detail heading and update
notice appear without rebuilding the router.

Run the complete boundaries:

```bash
php artisan test tests/Feature/IssueApiTest.php
npm run typecheck
npm run lint
npm test
npm run build
```

The frontend should report twelve tests. In the browser, save a new name, confirm the URL
returns to `/workspaces/{id}` without a document reload, then refresh and see the same name.

```bash
git add routes/routes.php app/Http/controllers/api/workspaces/update.php \
  resources/app tests/Feature/IssueApiTest.php
git commit -m "Edit workspaces without reloading"
```

Next we will add a reviewed delete route that removes the workspace and its dependent data
only after explicit confirmation.
