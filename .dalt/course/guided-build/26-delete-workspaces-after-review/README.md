A workspace contains projects, and those projects contain issues. Deleting only the parent
would leave disconnected data. We will add an explicit review screen and one transaction
that removes the complete hierarchy only after confirmation.

## Register the review and mutation routes

Add the shell route and protected API route in `routes/routes.php`:

```php
$router->get('/workspaces/{workspace}/delete', 'workspaces/show.php');
$router->delete('/api/workspaces/{workspace}', 'api/workspaces/destroy.php')
    ->only('csrf');
```

The GET reuses the workspace shell controller, preserving direct-link validation and 404
behavior. The DELETE route describes the mutation accurately. Our browser will send it
through DALT's `_method` override so the CSRF token can stay in a URL-encoded body.

## Delete one hierarchy atomically

Create `app/Http/controllers/api/workspaces/destroy.php`. Validate the ID and load the row
before starting destructive work:

```php
$request = App::resolve(Request::class);
$workspaceId = $request->route('workspace');

if (!is_string($workspaceId)
    || preg_match('/\A[1-9]\d*\z/', $workspaceId) !== 1) {
    abort(404);
}

$database = App::resolve(Database::class);
$workspace = $database
    ->query('SELECT id, name FROM workspaces WHERE id = :id', [
        'id' => (int) $workspaceId,
    ])
    ->findOrFail();
$connection = $database->getConnection();
```

Start a transaction and delete from the deepest child upward:

```php
$connection->beginTransaction();

try {
    $database->query(
        'DELETE FROM issues
         WHERE project_id IN (
             SELECT id FROM projects WHERE workspace_id = :workspace_id
         )',
        ['workspace_id' => $workspace['id']],
    );
    $database->query(
        'DELETE FROM projects WHERE workspace_id = :workspace_id',
        ['workspace_id' => $workspace['id']],
    );
    $database->query(
        'DELETE FROM workspaces WHERE id = :id',
        ['id' => $workspace['id']],
    );
    $connection->commit();
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    throw $exception;
}
```

The transaction is the important new idea. If any statement fails, rollback restores the
earlier deletions. We never leave half a workspace behind.

Return the name captured before deletion:

```php
return Response::json([
    'message' => "{$workspace['name']} was deleted.",
]);
```

## Prove every dependent row is gone

Add a feature test in `tests/Feature/IssueApiTest.php`. First prove CSRF still protects the
route, then make the real method-override request:

```php
$missingToken = issueApiRequest(
    $router,
    'POST',
    '/api/workspaces/1',
    ['_method' => 'DELETE'],
    withCsrf: false,
);
$deleted = issueApiRequest(
    $router,
    'POST',
    '/api/workspaces/1',
    ['_method' => 'DELETE'],
);
```

Query all three tables independently:

```php
$workspaceCount = issueApiDatabase()
    ->query('SELECT COUNT(*) AS aggregate FROM workspaces WHERE id = 1')
    ->find();
$projectCount = issueApiDatabase()
    ->query('SELECT COUNT(*) AS aggregate FROM projects WHERE workspace_id = 1')
    ->find();
$issueCount = issueApiDatabase()
    ->query('SELECT COUNT(*) AS aggregate FROM issues WHERE project_id = 1')
    ->find();

expect($missingToken->status())->toBe(419)
    ->and($deleted->status())->toBe(200)
    ->and(json_decode(
        $deleted->content(), true, flags: JSON_THROW_ON_ERROR,
    ))->toBe(['message' => 'Studio was deleted.'])
    ->and((int) $workspaceCount['aggregate'])->toBe(0)
    ->and((int) $projectCount['aggregate'])->toBe(0)
    ->and((int) $issueCount['aggregate'])->toBe(0);
```

A fake success response or parent-only DELETE now fails.

## Add the browser request

In `resources/app/workspace-detail-data.ts`, send `_method=DELETE` with the token:

```ts
export async function deleteWorkspace(
  data: WorkspaceDetailPageData,
): Promise<string> {
  const body = new URLSearchParams({
    _token: data.form.csrfToken,
    _method: 'DELETE',
  })
  const response = await fetch('/api/workspaces/' + data.workspace.id, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body,
  })
  const value: unknown = await response.json()
  if (!response.ok || !isRecord(value)) {
    throw new Error('The workspace could not be deleted.')
  }
  return stringAt(value, 'message')
}
```

## Build a dedicated review screen

Create `resources/app/DeleteWorkspacePage.tsx`. Read the current workspace name from route
state with the bootstrap value as fallback. Do not delete on mount or when the review link
is followed. The destructive button alone calls this handler:

```tsx
async function confirmDelete() {
  setSubmitting(true)
  setNotice(null)
  try {
    await deleteWorkspace(data)
    leave('/')
  } catch {
    setNotice(
      'The workspace could not be deleted. Check the connection and try again.',
    )
    setSubmitting(false)
  }
}
```

The optional `leave` function defaults to `window.location.assign`. Root and workspace
screens currently have separate browser-router bootstraps, so crossing that boundary uses
one intentional document navigation after success. Injecting `leave` also lets the test
observe the destination without asking jsdom to navigate.

Render the consequence before the controls:

```tsx
<h1>Delete workspace?</h1>
<p>
  Review what will be removed before deleting <strong>{workspaceName}</strong>.
</p>

<section aria-labelledby="delete-workspace-title">
  <h2 id="delete-workspace-title">This cannot be undone</h2>
  <p>
    The workspace, every project inside it, and all of those projects’ issues
    will be permanently removed.
  </p>
  <button
    type="button"
    disabled={submitting}
    onClick={() => void confirmDelete()}
  >
    {submitting ? 'Deleting…' : 'Delete workspace'}
  </button>
  <Link to={'/workspaces/' + data.workspace.id}>Cancel</Link>
</section>
```

Register `delete` beside `edit` in the workspace router, and add a restrained red
`Delete workspace` link next to the edit action on `WorkspaceDetailPage`.

## Test review before mutation

Extend `workspace-detail-workflow.test.tsx`. Inject a function that records destinations,
open the delete link, and assert the warning while the destination list is still empty.
Only then click `Delete workspace`. The MSW handler should assert `_token=test-token` and
`_method=DELETE`; afterward the destination list must equal `['/']`.

Run every boundary:

```bash
php artisan test tests/Feature/IssueApiTest.php
npm run typecheck
npm run lint
npm test
npm run build
```

The backend file should report ten tests and 90 assertions. The frontend should report
thirteen tests. In the browser, cancel must preserve the workspace; confirm must return to
`/`, remove its row, and make the old deep URL return 404.

```bash
git add routes/routes.php app/Http/controllers/api/workspaces/destroy.php \
  resources/app tests/Feature/IssueApiTest.php
git commit -m "Delete workspaces after review"
```

Next we will add the same dedicated editing quality to projects while keeping both
workspace and project ownership in every API query.
