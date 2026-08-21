Project management still lacks its destructive action. We will add a review screen that
clearly distinguishes what disappears—the project and its issues—from what remains—the
workspace—and enforce that boundary in a transaction.

## Register the review and DELETE routes

Add these routes in `routes/routes.php`:

```php
$router->get(
    '/workspaces/{workspace}/projects/{project}/delete',
    'projects/show.php',
);
$router->delete(
    '/api/workspaces/{workspace}/projects/{project}',
    'api/projects/destroy.php',
)->only('csrf');
```

Reusing `projects/show.php` means a direct review URL must pass the existing nested
workspace/project lookup before the React shell is served.

## Delete only the owned project hierarchy

Create `app/Http/controllers/api/projects/destroy.php`. Validate both IDs, load the
workspace, and require the project to belong to it:

```php
$workspace = $database
    ->query('SELECT id FROM workspaces WHERE id = :id', [
        'id' => (int) $workspaceId,
    ])
    ->findOrFail();
$project = $database
    ->query(
        'SELECT id, name FROM projects
         WHERE id = :id AND workspace_id = :workspace_id',
        [
            'id' => (int) $projectId,
            'workspace_id' => $workspace['id'],
        ],
    )
    ->findOrFail();
```

Wrap the child and parent deletions in one transaction:

```php
$connection = $database->getConnection();
$connection->beginTransaction();

try {
    $database->query(
        'DELETE FROM issues WHERE project_id = :project_id',
        ['project_id' => $project['id']],
    );
    $database->query(
        'DELETE FROM projects WHERE id = :id',
        ['id' => $project['id']],
    );
    $connection->commit();
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    throw $exception;
}

return Response::json([
    'message' => "{$project['name']} was deleted.",
]);
```

The workspace is deliberately absent from the DELETE statements. The API proves its
ownership role, but the mutation stops at the project hierarchy.

## Test both removal and survival

Add a feature test that sends `_method=DELETE` first without CSRF and then with the token.
After the confirmed request, independently count the workspace, project, and project issues:

```php
expect($missingToken->status())->toBe(419)
    ->and($deleted->status())->toBe(200)
    ->and(json_decode(
        $deleted->content(), true, flags: JSON_THROW_ON_ERROR,
    ))->toBe(['message' => 'Launch was deleted.'])
    ->and((int) $workspaceCount['aggregate'])->toBe(1)
    ->and((int) $projectCount['aggregate'])->toBe(0)
    ->and((int) $issueCount['aggregate'])->toBe(0);
```

The positive workspace count matters as much as the two zeroes. A query that accidentally
deletes too much must fail this test. The backend file should now report twelve tests and
108 assertions.

## Add the browser mutation

In `resources/app/project-page-data.ts`, add:

```ts
export async function deleteProject(data: ProjectPageData): Promise<string> {
  const body = new URLSearchParams({
    _token: data.form.csrfToken,
    _method: 'DELETE',
  })
  const response = await fetch(
    `/api/workspaces/${data.workspace.id}/projects/${data.project.id}`,
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
    throw new Error('The project could not be deleted.')
  }
  return stringAt(value, 'message')
}
```

As before, the POST transport carries an overridden DELETE method that DALT resolves before
route dispatch.

## Build the project review screen

Create `resources/app/DeleteProjectPage.tsx`. Read `projectName` from location state with
the safe bootstrap as fallback. The review copy must say exactly what changes:

```tsx
<h1>Delete project?</h1>
<p>
  Review what will be removed before deleting <strong>{projectName}</strong>.
</p>

<section aria-labelledby="delete-project-title">
  <h2 id="delete-project-title">This cannot be undone</h2>
  <p>
    The project and every issue inside it will be permanently removed.
    The workspace will remain.
  </p>
</section>
```

Do not call the API until the destructive button is activated:

```tsx
async function confirmDelete() {
  setSubmitting(true)
  setNotice(null)
  try {
    await deleteProject(data)
    leave('/workspaces/' + data.workspace.id)
  } catch {
    setNotice(
      'The project could not be deleted. Check the connection and try again.',
    )
    setSubmitting(false)
  }
}
```

`leave` defaults to `window.location.assign`. The workspace screen has its own React
bootstrap, so one document navigation is the honest boundary after successful deletion.
Before success, the user stays in the project router and can cancel without a reload.

Render a red `Delete project` button with a disabled `Deleting…` state and a neutral Cancel
link. Register `{ path: 'delete', element: <DeleteProjectPage data={data} /> }` beside the
project edit route in `main.tsx`. On `ProjectPage`, group restrained Edit and Delete links
beside the heading; pass the current routed project name into both.

## Prove review comes first

Extend `issue-workflow.test.tsx`. Allow `renderAt` to inject the delete page's `leave`
function. Let MSW assert the CSRF token and method override, then:

```tsx
await user.click(screen.getByRole('link', { name: 'Delete project' }))

expect(screen.getByRole('heading', { name: 'Delete project?' }))
  .toBeInTheDocument()
expect(screen.getByText('This cannot be undone')).toBeInTheDocument()
expect(destinations).toEqual([])

await user.click(screen.getByRole('button', { name: 'Delete project' }))
expect(destinations).toEqual(['/workspaces/1'])
```

Run the full application boundaries:

```bash
php artisan test tests/Feature/IssueApiTest.php
npm run typecheck
npm run lint
npm test
npm run build
```

The frontend should report fifteen tests. In the browser, cancel preserves everything;
confirm returns to the workspace, removes the project row, leaves the workspace available,
and makes the old project URL return 404.

```bash
git add routes/routes.php app/Http/controllers/api/projects/destroy.php \
  resources/app tests/Feature/IssueApiTest.php
git commit -m "Delete projects after review"
```

We now have complete create, read, update, and delete workflows for workspaces, projects,
and issues. The next planning pass can introduce the first user identity boundary—starting
with registration and login—before authorization makes these records belong to people.
