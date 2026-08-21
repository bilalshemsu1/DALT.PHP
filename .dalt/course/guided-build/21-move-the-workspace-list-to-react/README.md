Our issue workflow already feels like one application, but the home page still renders
its workspace list in PHP. We will move that list into React without changing workspace
creation yet. This gives us a small migration boundary we can verify before the form
starts sending JSON in the next lesson.

## Add the workspace collection endpoint

React needs workspace data, including the number of projects in each workspace. Register
one read-only route in `routes/routes.php` near the existing workspace routes:

```php
$router->get('/api/workspaces', 'api/workspaces/index.php');
```

Create `app/Http/controllers/api/workspaces/index.php`. The query keeps workspaces with no
projects by using a left join, then groups the joined rows so SQL can calculate one count
per workspace:

```php
<?php

declare(strict_types=1);

use Core\App;
use Core\Database;
use Core\Response;

$database = App::resolve(Database::class);
$workspaces = $database
    ->query(
        'SELECT workspaces.id, workspaces.name, COUNT(projects.id) AS project_count
         FROM workspaces
         LEFT JOIN projects ON projects.workspace_id = workspaces.id
         GROUP BY workspaces.id, workspaces.name
         ORDER BY workspaces.id DESC',
    )
    ->get();
```

`COUNT(projects.id)` is deliberate. For an empty workspace the left join still produces a
row, but its project ID is `NULL`; counting the project column produces `0`. Counting every
joined row would incorrectly report one project.

Return a small public JSON shape rather than exposing database column names:

```php
return Response::json([
    'workspaces' => array_map(
        static fn (array $workspace): array => [
            'id' => (int) ($workspace['id'] ?? 0),
            'name' => (string) ($workspace['name'] ?? ''),
            'projectCount' => (int) ($workspace['project_count'] ?? 0),
        ],
        $workspaces,
    ),
]);
```

The casts make the network contract honest. PDO may return aggregate values as strings,
but our React code will receive numbers for both `id` and `projectCount`.

## Check the real JSON contract

Our existing `tests/Feature/IssueApiTest.php` already creates two workspaces and one
project inside each. Add this test after its `beforeEach` hook:

```php
test('the workspace collection returns project counts in newest-first order', function () {
    $response = issueApiRequest(issueApiRouter(), 'GET', '/api/workspaces');

    expect($response->status())->toBe(200)
        ->and($response->headers()['Content-Type'])
        ->toBe('application/json; charset=UTF-8')
        ->and(json_decode($response->content(), true, flags: JSON_THROW_ON_ERROR))
        ->toBe([
            'workspaces' => [
                ['id' => 2, 'name' => 'Archive', 'projectCount' => 1],
                ['id' => 1, 'name' => 'Studio', 'projectCount' => 1],
            ],
        ]);
});
```

This is more useful than checking for a route string. The test dispatches the real route,
runs the real join, and pins the ordering and numeric JSON types React will depend on.

Run the focused backend boundary:

```bash
php artisan test tests/Feature/IssueApiTest.php
```

It should now report five passing tests and 48 assertions.

## Define the browser boundary

Create `resources/app/workspace-data.ts`. Start with the data React may trust after it has
been checked:

```ts
export type WorkspaceSummary = {
  id: number
  name: string
  projectCount: number
}

export type WorkspacePageData = {
  form: {
    csrfToken: string
    oldName: string
    nameError?: string
  }
  notice?: string
}
```

There are two sources because the page has two different jobs. The workspace collection
comes from the new API. The existing native form still needs a CSRF token and may need old
input or a validation message after PHP redirects back. We will bootstrap only that form
state into the HTML.

Network JSON is unknown until we inspect it. Add the same small runtime helpers we used for
the issue API:

```ts
function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function stringAt(record: Record<string, unknown>, key: string): string {
  const value = record[key]
  if (typeof value !== 'string') {
    throw new Error('Workspace data has an invalid ' + key + '.')
  }
  return value
}

function integerAt(record: Record<string, unknown>, key: string): number {
  const value = record[key]
  if (typeof value !== 'number' || !Number.isInteger(value) || value < 0) {
    throw new Error('Workspace data has an invalid ' + key + '.')
  }
  return value
}
```

Parse the collection one item at a time:

```ts
export function parseWorkspacesResponse(value: unknown): WorkspaceSummary[] {
  if (!isRecord(value) || !Array.isArray(value.workspaces)) {
    throw new Error('The workspaces response has an invalid workspace list.')
  }

  return value.workspaces.map((workspace) => {
    if (!isRecord(workspace)) {
      throw new Error('The response contains an invalid workspace.')
    }

    const id = integerAt(workspace, 'id')
    if (id < 1) throw new Error('The response contains an invalid workspace id.')

    return {
      id,
      name: stringAt(workspace, 'name'),
      projectCount: integerAt(workspace, 'projectCount'),
    }
  })
}
```

Now add the request function. It accepts an abort signal so the component can stop an
obsolete request during cleanup:

```ts
export async function fetchWorkspaces(
  signal: AbortSignal,
): Promise<WorkspaceSummary[]> {
  const response = await fetch('/api/workspaces', {
    headers: { Accept: 'application/json' },
    signal,
  })

  if (!response.ok) {
    throw new Error(
      'The workspaces request failed with status ' + response.status + '.',
    )
  }

  const value: unknown = await response.json()
  return parseWorkspacesResponse(value)
}
```

Keep the page-bootstrap parser in this file too. It should require the form fields and
allow PHP to send either a string or `null` for optional flash values:

```ts
function optionalStringAt(
  record: Record<string, unknown>,
  key: string,
): string | undefined {
  const value = record[key]
  if (value === undefined || value === null) return undefined
  if (typeof value !== 'string') {
    throw new Error('Workspace data has an invalid ' + key + '.')
  }
  return value
}

export function parseWorkspacePageData(value: unknown): WorkspacePageData {
  if (!isRecord(value) || !isRecord(value.form)) {
    throw new Error('Workspace page data must contain a form.')
  }

  return {
    form: {
      csrfToken: stringAt(value.form, 'csrfToken'),
      oldName: stringAt(value.form, 'oldName'),
      nameError: optionalStringAt(value.form, 'nameError'),
    },
    notice: optionalStringAt(value, 'notice'),
  }
}
```

Read that JSON from a non-executable script element:

```ts
export function readWorkspacePageData(): WorkspacePageData {
  const source = document.getElementById('workspace-page-data')
  if (!(source instanceof HTMLScriptElement)) {
    throw new Error('Workspace page data was not found.')
  }

  let value: unknown
  try {
    value = JSON.parse(source.textContent ?? '')
  } catch {
    throw new Error('Workspace page data is not valid JSON.')
  }

  return parseWorkspacePageData(value)
}
```

## Render all collection states

Create `resources/app/WorkspaceIndexPage.tsx`. Model the request as a small state machine:

```tsx
import { useEffect, useState } from 'react'
import { fetchWorkspaces } from './workspace-data'
import type { WorkspacePageData, WorkspaceSummary } from './workspace-data'

type WorkspaceState =
  | { status: 'loading' }
  | { status: 'ready'; workspaces: WorkspaceSummary[] }
  | { status: 'error' }
```

The `ready` branch is the only branch that contains a workspace array. TypeScript now
prevents us from accidentally reading collection data while the request is still loading.

Inside `WorkspaceIndexPage`, fetch on mount and whenever a retry increments `attempt`:

```tsx
export function WorkspaceIndexPage({ data }: { data: WorkspacePageData }) {
  const [attempt, setAttempt] = useState(0)
  const [state, setState] = useState<WorkspaceState>({ status: 'loading' })

  useEffect(() => {
    const controller = new AbortController()
    setState({ status: 'loading' })

    void fetchWorkspaces(controller.signal)
      .then((workspaces) => setState({ status: 'ready', workspaces }))
      .catch((error: unknown) => {
        if (!(error instanceof DOMException && error.name === 'AbortError')) {
          setState({ status: 'error' })
        }
      })

    return () => controller.abort()
  }, [attempt])
```

React runs an Effect's cleanup before the Effect runs again and when the component leaves
the page. Aborting here also makes the extra development setup-and-cleanup cycle under
Strict Mode harmless.

Build the heading and count from confirmed state:

```tsx
  return (
    <main className="mx-auto w-[calc(100%_-_40px)] max-w-[960px] py-14 sm:py-[72px]">
      <header className="flex items-end justify-between gap-6 border-b border-line-strong pb-7 max-sm:flex-col max-sm:items-start">
        <div>
          <h1 className="text-[clamp(36px,6vw,56px)] leading-[1.02] font-bold tracking-[-0.04em]">
            Your workspaces
          </h1>
          <p className="mt-4 leading-7 text-muted">
            Keep each team’s projects, decisions, and issues together.
          </p>
        </div>

        {state.status === 'ready' && (
          <span className="shrink-0 text-sm font-semibold text-muted" aria-live="polite">
            {state.workspaces.length}{' '}
            {state.workspaces.length === 1 ? 'workspace' : 'workspaces'}
          </span>
        )}
      </header>
```

Render loading, failure, and ready as distinct outcomes. The retry button changes only the
dependency that should start a new request:

```tsx
      <section aria-label="Workspace collection">
        {state.status === 'loading' && (
          <div className="border-t border-line-strong py-8" role="status">
            <p className="text-sm font-semibold text-muted">Loading workspaces…</p>
          </div>
        )}

        {state.status === 'error' && (
          <div className="rounded-[12px] border border-[#f1a9a3] bg-[#fff1f0] p-5" role="alert">
            <h2 className="text-[15px] font-bold text-[#7a271a]">
              Workspaces could not be loaded
            </h2>
            <p className="mt-2 text-[13px] leading-6 text-[#7a271a]">
              Check the connection, then try this request again.
            </p>
            <button type="button" onClick={() => setAttempt((value) => value + 1)}>
              Try again
            </button>
          </div>
        )}

        {state.status === 'ready' && (
          <WorkspaceList workspaces={state.workspaces} />
        )}
      </section>
```

Extract `WorkspaceList` above the page component. Keep ordinary anchors for workspace
navigation; those destination screens are still PHP-owned:

```tsx
function WorkspaceList({ workspaces }: { workspaces: WorkspaceSummary[] }) {
  if (workspaces.length === 0) {
    return (
      <section className="rounded-[14px] border border-dashed border-line-strong bg-surface px-5 py-12 text-center">
        <h2 className="text-lg font-bold">No workspaces yet</h2>
        <p className="mt-2.5 text-sm leading-6 text-muted">
          Create the first shared space for your team’s projects and issues.
        </p>
      </section>
    )
  }

  return (
    <ol className="list-none border-t border-line-strong p-0" aria-label="Workspaces">
      {workspaces.map((workspace) => (
        <li className="border-b border-line" key={workspace.id}>
          <a className="flex items-center justify-between gap-5 py-5" href={'/workspaces/' + workspace.id}>
            <span className="min-w-0 break-words font-bold">{workspace.name}</span>
            <span className="text-[13px] text-muted">
              {workspace.projectCount}{' '}
              {workspace.projectCount === 1 ? 'project' : 'projects'}
            </span>
          </a>
        </li>
      ))}
    </ol>
  )
}
```

Finally, move the existing create form into the second column. Do not add an `onSubmit`
handler yet:

```tsx
<form method="POST" action="/workspaces">
  <input name="_token" type="hidden" value={data.form.csrfToken} />
  <label className="mb-2 block text-sm font-bold" htmlFor="workspace-name">
    Workspace name
  </label>
  <input
    id="workspace-name"
    name="name"
    type="text"
    defaultValue={data.form.oldName}
    minLength={2}
    maxLength={50}
    autoComplete="organization"
    required
    aria-invalid={data.form.nameError !== undefined}
    aria-describedby={data.form.nameError ? 'workspace-name-error' : undefined}
  />
  {data.form.nameError !== undefined && (
    <p id="workspace-name-error" role="alert">{data.form.nameError}</p>
  )}
  <button type="submit">Create workspace</button>
</form>
```

This is progressive migration, not unfinished behavior. The browser still submits to the
working PHP controller, which validates, inserts, flashes a message, and redirects. After
the reload, React fetches the updated list. Lesson 22 will replace precisely that boundary.

## Give the home page its React shell

The shared `AppLayout` no longer needs project data. Change its function signature in
`resources/app/AppLayout.tsx` and use a normal home link:

```tsx
export function AppLayout() {
  return (
    <>
      <header className="border-b border-line bg-surface">
        <div className="mx-auto flex min-h-16 max-w-[960px] items-center justify-between">
          <a className="inline-flex items-center gap-2.5 font-bold text-ink no-underline" href="/">
            <span className="h-6 w-2.5 rounded-[3px] bg-accent" aria-hidden="true" />
            DALT Issues
          </a>
          <span className="text-[13px] text-muted">Local development</span>
        </div>
      </header>
      <Outlet />
    </>
  )
}
```

Update existing tests and router entries from `<AppLayout data={data} />` to
`<AppLayout />`.

In `resources/app/main.tsx`, choose the router from the page marker PHP supplies:

```tsx
if (document.body.dataset.page === 'workspaces') {
  const data = readWorkspacePageData()
  const router = createBrowserRouter([
    {
      path: '/',
      element: <AppLayout />,
      children: [
        { index: true, element: <WorkspaceIndexPage data={data} /> },
      ],
    },
  ])
  applicationScreen = <RouterProvider router={router} />
} else {
  const data = readProjectPageData()
  // Keep the existing project and issue router here.
}
```

Add `data-page="project"` to the body in
`resources/views/projects/show.view.php`. Then replace the large PHP workspace markup in
`resources/views/welcome.view.php` with a small shell. First prepare only the form data:

```php
<?php

declare(strict_types=1);

$errors = Core\Session::get('errors', []);
$errors = is_array($errors) ? $errors : [];
$oldName = old('name');
$oldName = is_string($oldName) ? $oldName : '';
$success = Core\Session::get('success');
$success = is_string($success) ? $success : null;

$pageData = json_encode(
    [
        'form' => [
            'csrfToken' => csrf_token(),
            'oldName' => $oldName,
            'nameError' => is_string($errors['name'] ?? null)
                ? $errors['name']
                : null,
        ],
        'notice' => $success,
    ],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        | JSON_THROW_ON_ERROR,
);
?>
```

The HTML now has the same three responsibilities as our project shell:

```php
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title>DALT.PHP</title>
  <?= vite('resources/app/main.tsx') ?>
</head>
<body data-page="workspaces">
  <div id="root"></div>
  <script id="workspace-page-data" type="application/json"><?= $pageData ?></script>
  <noscript>This page needs JavaScript to show your workspaces.</noscript>
</body>
</html>
```

Because the list now comes from `/api/workspaces`, simplify
`app/Http/controllers/welcome.php`:

```php
<?php

declare(strict_types=1);

view('welcome.view.php');
```

## Protect loading and retry behavior

Create `resources/app/workspace-workflow.test.tsx`. Render the page with a memory router
and let MSW respond at the same URL the browser uses:

```tsx
test('loads the workspace collection and renders project counts', async () => {
  server.use(http.get('/api/workspaces', () => HttpResponse.json({
    workspaces: [{ id: 4, name: 'Platform', projectCount: 2 }],
  })))

  renderWorkspaceIndex()

  expect(screen.getByRole('status')).toHaveTextContent('Loading workspaces')
  expect(await screen.findByRole('link', { name: /Platform/i }))
    .toHaveAttribute('href', '/workspaces/4')
  expect(screen.getByText('2 projects')).toBeInTheDocument()
})
```

Add a failure that succeeds only after the user retries:

```tsx
test('offers a retry after the workspace request fails', async () => {
  let attempts = 0
  server.use(http.get('/api/workspaces', () => {
    attempts += 1
    if (attempts === 1) {
      return HttpResponse.json({ message: 'Unavailable.' }, { status: 503 })
    }
    return HttpResponse.json({ workspaces: [] })
  }))

  const { user } = renderWorkspaceIndex()

  expect(await screen.findByRole('alert'))
    .toHaveTextContent('Workspaces could not be loaded')
  await user.click(screen.getByRole('button', { name: 'Try again' }))
  expect(await screen.findByRole('heading', { name: 'No workspaces yet' }))
    .toBeInTheDocument()
})
```

Run every application boundary affected by this migration:

```bash
npm run typecheck
npm run lint
npm test
npm run build
php artisan test tests/Feature/IssueApiTest.php
```

The frontend suite should report seven passing tests across two files. The backend file
should still report five passing tests and 48 assertions.

Open `/`. The heading, collection, real project counts, and native create form should now
be React styled with Tailwind. At a narrow viewport the two-column layout becomes one
column. Creating a workspace still reloads once, preserves CSRF protection, shows the
flash message, and returns with the new row fetched from JSON.

If Git is available, save this working point:

```bash
git add routes/routes.php app/Http/controllers resources/app \
  resources/views tests/Feature/IssueApiTest.php
git commit -m "Move the workspace list to React"
```

We now have a React-owned home screen with an intentionally native mutation. Next we will
make that form create a workspace through JSON, update this collection from the confirmed
server response, and remove its last full-page reload.
