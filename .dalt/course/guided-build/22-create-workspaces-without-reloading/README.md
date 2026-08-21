Our workspace list is now React-owned, but creating a workspace still submits the old PHP
form and reloads `/`. We will replace only that mutation boundary: DALT will return the
stored workspace as JSON, and React will add it to the list after the server confirms it.

## Add a protected JSON create route

Keep the existing native route for now and register a second POST route in
`routes/routes.php`:

```php
$router->get('/api/workspaces', 'api/workspaces/index.php');
$router->post('/api/workspaces', 'api/workspaces/store.php')->only('csrf');
$router->post('/workspaces', 'workspaces/store.php')->only('csrf');
```

The React form will send the same CSRF token as the native form. Putting `csrf` middleware
on the API route keeps the protection at DALT's request boundary; a caller cannot bypass it
by avoiding our component.

Create `app/Http/controllers/api/workspaces/store.php`. Read and normalize the name exactly
as the existing controller does:

```php
<?php

declare(strict_types=1);

use Core\App;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Validator;

$request = App::resolve(Request::class);
$nameInput = $request->input('name');
$name = is_string($nameInput) ? trim($nameInput) : '';

if (!Validator::string($name, 2, 50)) {
    return Response::json([
        'errors' => ['name' => 'Use between 2 and 50 characters.'],
    ], 422);
}
```

The native controller throws a `ValidationException` because it needs redirect flash data.
Our React caller needs a stable JSON error instead, so this controller returns status 422
and a field-keyed `errors` object.

Insert the valid row and return the exact item the list can render:

```php
$database = App::resolve(Database::class);
$database->query(
    'INSERT INTO workspaces (name) VALUES (:name)',
    ['name' => $name],
);

return Response::json([
    'workspace' => [
        'id' => (int) $database->getConnection()->lastInsertId(),
        'name' => $name,
        'projectCount' => 0,
    ],
    'message' => "{$name} was created.",
], 201);
```

The new workspace has no projects, so the response includes `projectCount: 0`. React does
not invent an ID or make a second collection request. It receives one server-confirmed
object with the same shape as the index endpoint.

## Prove protection, validation, and persistence

Add a test to `tests/Feature/IssueApiTest.php`. Exercise three paths through the real route:

```php
test('workspace creation is protected validates input and returns the stored row', function () {
    $router = issueApiRouter();

    $missingToken = issueApiRequest(
        $router,
        'POST',
        '/api/workspaces',
        ['name' => 'Protected'],
        withCsrf: false,
    );
    $invalid = issueApiRequest(
        $router,
        'POST',
        '/api/workspaces',
        ['name' => 'x'],
    );
    $created = issueApiRequest(
        $router,
        'POST',
        '/api/workspaces',
        ['name' => '  Product  '],
    );
```

Read the stored row independently from the response:

```php
    $createdBody = json_decode(
        $created->content(),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $stored = issueApiDatabase()
        ->query('SELECT id, name FROM workspaces WHERE id = :id', [
            'id' => $createdBody['workspace']['id'],
        ])
        ->find();
```

Now pin the complete contract:

```php
    expect($missingToken->status())->toBe(419)
        ->and($invalid->status())->toBe(422)
        ->and(json_decode(
            $invalid->content(),
            true,
            flags: JSON_THROW_ON_ERROR,
        ))->toBe([
            'errors' => ['name' => 'Use between 2 and 50 characters.'],
        ])
        ->and($created->status())->toBe(201)
        ->and($createdBody)->toBe([
            'workspace' => [
                'id' => (int) $stored['id'],
                'name' => 'Product',
                'projectCount' => 0,
            ],
            'message' => 'Product was created.',
        ])
        ->and($stored['name'])->toBe('Product');
});
```

The separate SQL lookup prevents a plausible fake: returning a convincing 201 body without
actually inserting a workspace.

Run the backend file:

```bash
php artisan test tests/Feature/IssueApiTest.php
```

It should report six passing tests and 57 assertions.

## Teach TypeScript the mutation result

In `resources/app/workspace-data.ts`, the page bootstrap now needs only the CSRF token:

```ts
export type WorkspacePageData = {
  form: {
    csrfToken: string
  }
}

export type CreateWorkspaceResult = {
  workspace: WorkspaceSummary
  message: string
}
```

We no longer bootstrap old input, a validation error, or a success flash. React will keep
the attempted value in component state and receive feedback from JSON.

Add a dedicated error type so the form can distinguish expected validation from a broken
network or malformed response:

```ts
export class WorkspaceValidationError extends Error {
  constructor(readonly errors: { name?: string }) {
    super('The workspace could not be created.')
  }
}
```

Create the request function. URL-encoded input lets the DALT `Request` and CSRF middleware
read the body just like an ordinary form submission:

```ts
export async function createWorkspace(
  data: WorkspacePageData,
  name: string,
): Promise<CreateWorkspaceResult> {
  const body = new URLSearchParams({
    _token: data.form.csrfToken,
    name,
  })

  const response = await fetch('/api/workspaces', {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body,
  })
  const value: unknown = await response.json()
```

Handle 422 before the general failure path:

```ts
  if (response.status === 422) {
    if (!isRecord(value) || !isRecord(value.errors)) {
      throw new Error('The validation response is invalid.')
    }

    const nameError = value.errors.name
    if (nameError !== undefined && typeof nameError !== 'string') {
      throw new Error('The validation response is invalid.')
    }

    throw new WorkspaceValidationError({ name: nameError })
  }
```

For success, reuse the collection parser rather than creating a second, weaker definition
of a workspace:

```ts
  if (!response.ok || !isRecord(value)) {
    throw new Error('The workspace could not be created.')
  }

  return {
    workspace: parseWorkspacesResponse({ workspaces: [value.workspace] })[0],
    message: stringAt(value, 'message'),
  }
}
```

The mutation is now safe on both sides of the network: PHP constructs the public shape and
TypeScript checks it at runtime before the UI accepts it.

## Turn the form into a React mutation

In `resources/app/WorkspaceIndexPage.tsx`, import the new request and error:

```tsx
import {
  createWorkspace,
  fetchWorkspaces,
  WorkspaceValidationError,
} from './workspace-data'
```

Extract a `CreateWorkspaceForm` component. Give each visible concern its own state:

```tsx
function CreateWorkspaceForm({ data, onCreated }: {
  data: WorkspacePageData
  onCreated: (workspace: WorkspaceSummary) => void
}) {
  const [name, setName] = useState('')
  const [nameError, setNameError] = useState<string | null>(null)
  const [notice, setNotice] = useState<{
    tone: 'success' | 'error'
    message: string
  } | null>(null)
  const [submitting, setSubmitting] = useState(false)
```

The submit handler prevents the native navigation, clears stale feedback, and waits for
DALT:

```tsx
  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSubmitting(true)
    setNameError(null)
    setNotice(null)

    try {
      const result = await createWorkspace(data, name)
      onCreated(result.workspace)
      setName('')
      setNotice({ tone: 'success', message: result.message })
    } catch (error) {
      if (error instanceof WorkspaceValidationError) {
        setNameError(error.errors.name ?? 'Check the workspace name.')
      } else {
        setNotice({
          tone: 'error',
          message: 'The workspace could not be created. Check the connection and try again.',
        })
      }
    } finally {
      setSubmitting(false)
    }
  }
```

Invalid input is not cleared. Only the confirmed success path empties `name`, calls
`onCreated`, and displays the server message.

Change the form markup from `method` and `action` to `onSubmit`, then control its input:

```tsx
<form onSubmit={(event) => void submit(event)} noValidate>
  <label htmlFor="workspace-name">Workspace name</label>
  <input
    id="workspace-name"
    name="name"
    type="text"
    value={name}
    onChange={(event) => setName(event.target.value)}
    autoComplete="organization"
    aria-invalid={nameError !== null}
    aria-describedby={nameError !== null ? 'workspace-name-error' : undefined}
  />
  {nameError !== null && (
    <p id="workspace-name-error" role="alert">{nameError}</p>
  )}
  <button type="submit" disabled={submitting}>
    {submitting ? 'Creating…' : 'Create workspace'}
  </button>
</form>
```

`noValidate` lets the same server validation message drive browser behavior and tests. The
disabled button prevents accidental duplicate requests while this one is pending.

In `WorkspaceIndexPage`, add the callback that owns collection state:

```tsx
function addWorkspace(workspace: WorkspaceSummary) {
  setState((current) => current.status === 'ready'
    ? { status: 'ready', workspaces: [workspace, ...current.workspaces] }
    : { status: 'ready', workspaces: [workspace] })
}
```

Pass it to the form:

```tsx
<CreateWorkspaceForm data={data} onCreated={addWorkspace} />
```

The functional state update reads the latest collection. If another state change happened
while the request was pending, we do not prepend into an old array captured by the render
that started the request.

## Shrink the PHP bootstrap

Since React owns input and feedback, simplify `$pageData` in
`resources/views/welcome.view.php`:

```php
$pageData = json_encode(
    [
        'form' => [
            'csrfToken' => csrf_token(),
        ],
    ],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        | JSON_THROW_ON_ERROR,
);
```

Keep the token server-generated and keep the JSON escaping flags. We removed only values
whose lifecycle moved into React.

## Test the user-visible conversation

Update the test bootstrap in `resources/app/workspace-workflow.test.tsx`:

```tsx
const data: WorkspacePageData = {
  form: { csrfToken: 'test-token' },
}
```

Add a test whose first POST is rejected and whose second is accepted:

```tsx
test('keeps rejected input and adds only the confirmed workspace', async () => {
  let attempts = 0
  server.use(
    http.get('/api/workspaces', () => HttpResponse.json({ workspaces: [] })),
    http.post('/api/workspaces', async ({ request }) => {
      attempts += 1
      const body = await request.formData()
      expect(body.get('_token')).toBe('test-token')

      if (attempts === 1) {
        return HttpResponse.json({
          errors: { name: 'Use between 2 and 50 characters.' },
        }, { status: 422 })
      }

      return HttpResponse.json({
        workspace: {
          id: 8,
          name: String(body.get('name')).trim(),
          projectCount: 0,
        },
        message: 'Product was created.',
      }, { status: 201 })
    }),
  )
```

Drive the form as a user would:

```tsx
  const { user } = renderWorkspaceIndex()
  await screen.findByRole('heading', { name: 'No workspaces yet' })
  const name = screen.getByRole('textbox', { name: 'Workspace name' })

  await user.type(name, 'x')
  await user.click(screen.getByRole('button', { name: 'Create workspace' }))
  expect(await screen.findByRole('alert'))
    .toHaveTextContent('Use between 2 and 50 characters.')
  expect(name).toHaveValue('x')

  await user.clear(name)
  await user.type(name, 'Product')
  await user.click(screen.getByRole('button', { name: 'Create workspace' }))
  expect(await screen.findByRole('link', { name: /Product/i }))
    .toHaveAttribute('href', '/workspaces/8')
  expect(screen.getByText('0 projects')).toBeInTheDocument()
  expect(screen.getByRole('status')).toHaveTextContent('Product was created.')
  expect(name).toHaveValue('')
})
```

The first response never creates a list row, and its value remains editable. The second
response supplies the ID, makes the row navigable, updates the count, and clears the field.

Run the complete boundaries:

```bash
npm run typecheck
npm run lint
npm test
npm run build
php artisan test tests/Feature/IssueApiTest.php
```

The frontend suite should report eight passing tests. The backend file should report six
passing tests and 57 assertions.

Open `/`, submit a one-character name, then submit a valid name. The validation message
should appear without losing the attempted value. The valid workspace should appear at the
top with `0 projects`, the total should increase, and the address must stay `/` without a
document reload.

If Git is available, save this point:

```bash
git add routes/routes.php app/Http/controllers/api/workspaces/store.php \
  resources/app/WorkspaceIndexPage.tsx resources/app/workspace-data.ts \
  resources/app/workspace-workflow.test.tsx resources/views/welcome.view.php \
  tests/Feature/IssueApiTest.php
git commit -m "Create workspaces without reloading"
```

The home workflow is now fully React-owned. Next we will migrate the workspace detail and
its project collection, keeping its create-project form native for one more deliberate
step.
