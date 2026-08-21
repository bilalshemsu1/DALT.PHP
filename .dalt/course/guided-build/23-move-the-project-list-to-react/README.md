The home workflow is React-owned, but following a workspace link still opens a PHP-rendered
project list. We will migrate that collection next. PHP will continue to prove the workspace
exists before serving HTML, while React loads and renders its projects from JSON.

## Keep the deep URL honest

The existing `app/Http/controllers/workspaces/show.php` validates the route ID and calls
`findOrFail()` for the workspace. Keep that boundary: a refresh of a missing workspace must
still return 404 before React starts.

The controller no longer needs to query projects, so remove that query and pass only the
confirmed workspace to the view:

```php
$workspace = $database
    ->query(
        'SELECT id, name, created_at FROM workspaces WHERE id = :id',
        ['id' => (int) $workspaceId],
    )
    ->findOrFail();

view('workspaces/show.view.php', [
    'workspace' => $workspace,
]);
```

This is a useful split of responsibility. The document request owns whether this URL
exists; the collection request owns the changing list below it.

## Return workspace-scoped projects

Register the new collection in `routes/routes.php`:

```php
$router->get('/workspaces/{workspace}', 'workspaces/show.php');
$router->get('/api/workspaces/{workspace}/projects', 'api/projects/index.php');
```

Create `app/Http/controllers/api/projects/index.php`. Validate and resolve the workspace
before selecting children:

```php
<?php

declare(strict_types=1);

use Core\App;
use Core\Database;
use Core\Request;
use Core\Response;

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

Query the projects and calculate each issue count in SQL:

```php
$projects = $database
    ->query(
        'SELECT projects.id, projects.name, COUNT(issues.id) AS issue_count
         FROM projects
         LEFT JOIN issues ON issues.project_id = projects.id
         WHERE projects.workspace_id = :workspace_id
         GROUP BY projects.id, projects.name
         ORDER BY projects.id DESC',
        ['workspace_id' => $workspace['id']],
    )
    ->get();
```

The workspace predicate is not cosmetic. It prevents this nested URL from becoming an
unscoped list of every project. The left join retains projects that have zero issues.

Return camel-cased, typed values:

```php
return Response::json([
    'projects' => array_map(
        static fn (array $project): array => [
            'id' => (int) ($project['id'] ?? 0),
            'name' => (string) ($project['name'] ?? ''),
            'issueCount' => (int) ($project['issue_count'] ?? 0),
        ],
        $projects,
    ),
]);
```

## Test the nested collection

Add this feature test to `tests/Feature/IssueApiTest.php`:

```php
test('the project collection stays inside its workspace and returns issue counts', function () {
    $response = issueApiRequest(
        issueApiRouter(),
        'GET',
        '/api/workspaces/1/projects',
    );

    expect($response->status())->toBe(200)
        ->and(json_decode(
            $response->content(),
            true,
            flags: JSON_THROW_ON_ERROR,
        ))->toBe([
            'projects' => [
                ['id' => 1, 'name' => 'Launch', 'issueCount' => 2],
            ],
        ]);
```

Also prove a missing parent cannot expose a collection:

```php
    try {
        issueApiRequest(
            issueApiRouter(),
            'GET',
            '/api/workspaces/999/projects',
        );
        $this->fail('A missing workspace must not expose a project collection.');
    } catch (HttpException $exception) {
        expect($exception->statusCode)->toBe(404);
    }
});
```

Run it:

```bash
php artisan test tests/Feature/IssueApiTest.php
```

The file should report seven passing tests and 63 assertions.

## Define project collection data

Create `resources/app/workspace-detail-data.ts`:

```ts
export type ProjectSummary = {
  id: number
  name: string
  issueCount: number
}

export type WorkspaceDetailPageData = {
  workspace: { id: number; name: string }
  form: {
    csrfToken: string
    oldName: string
    nameError?: string
  }
  notice?: string
}
```

The workspace identity and native create-form state come from the safe HTML bootstrap.
The project collection comes from the API. Add record, string, integer, and optional-string
guards like the workspace data module, then parse every project:

```ts
export function parseProjectsResponse(value: unknown): ProjectSummary[] {
  if (!isRecord(value) || !Array.isArray(value.projects)) {
    throw new Error('The projects response has an invalid project list.')
  }

  return value.projects.map((project) => {
    if (!isRecord(project)) {
      throw new Error('The response contains an invalid project.')
    }
    const id = integerAt(project, 'id')
    if (id < 1) throw new Error('The response contains an invalid project id.')

    return {
      id,
      name: stringAt(project, 'name'),
      issueCount: integerAt(project, 'issueCount'),
    }
  })
}
```

Add the abortable request:

```ts
export async function fetchProjects(
  workspaceId: number,
  signal: AbortSignal,
): Promise<ProjectSummary[]> {
  const response = await fetch(
    '/api/workspaces/' + workspaceId + '/projects',
    { headers: { Accept: 'application/json' }, signal },
  )
  if (!response.ok) {
    throw new Error(
      'The projects request failed with status ' + response.status + '.',
    )
  }
  const value: unknown = await response.json()
  return parseProjectsResponse(value)
}
```

Finish the module with `readWorkspaceDetailPageData()`. It reads the JSON script with ID
`workspace-detail-page-data`, parses the workspace and form objects, and rejects a
workspace ID below one.

## Build the React workspace screen

Create `resources/app/WorkspaceDetailPage.tsx`. Model the familiar request states:

```tsx
type ProjectsState =
  | { status: 'loading' }
  | { status: 'ready'; projects: ProjectSummary[] }
  | { status: 'error' }
```

Fetch the scoped collection with cleanup and retry:

```tsx
export function WorkspaceDetailPage({ data }: {
  data: WorkspaceDetailPageData
}) {
  const [attempt, setAttempt] = useState(0)
  const [state, setState] = useState<ProjectsState>({ status: 'loading' })

  useEffect(() => {
    const controller = new AbortController()
    setState({ status: 'loading' })
    void fetchProjects(data.workspace.id, controller.signal)
      .then((projects) => setState({ status: 'ready', projects }))
      .catch((error: unknown) => {
        if (!(error instanceof DOMException && error.name === 'AbortError')) {
          setState({ status: 'error' })
        }
      })
    return () => controller.abort()
  }, [data.workspace.id, attempt])
```

Render the known workspace immediately, then place the project collection and native form
in the established responsive grid:

```tsx
<header className="mt-10 border-b border-line-strong pb-7">
  <p className="mb-3 text-xs font-extrabold tracking-[0.1em] text-accent uppercase">
    Workspace {data.workspace.id}
  </p>
  <h1 className="text-[clamp(36px,6vw,56px)] font-bold">
    {data.workspace.name}
  </h1>
  <p className="mt-4 leading-7 text-muted">
    Projects and issues for this workspace live here.
  </p>
</header>

<div className="mt-12 grid items-start gap-7 sm:grid-cols-[minmax(0,1fr)_320px]">
  <section aria-labelledby="projects-title">
    <h2 id="projects-title" className="text-[22px] font-bold">Projects</h2>
    <ProjectsPanel
      state={state}
      workspaceId={data.workspace.id}
      retry={() => setAttempt((value) => value + 1)}
    />
  </section>
  {/* Keep the create form here for now. */}
</div>
```

`ProjectsPanel` should mirror the quality of our other collections: a loading status, a
failure alert with a retry button, a useful empty state, and a ready list. The ready row
uses a native anchor because the project destination is handled by a different browser
router:

```tsx
<a href={'/workspaces/' + workspaceId + '/projects/' + project.id}>
  <span>{project.name}</span>
  <span>
    {project.issueCount} {project.issueCount === 1 ? 'issue' : 'issues'}
  </span>
</a>
```

Keep the create form native for this lesson:

```tsx
<form method="POST" action={'/workspaces/' + data.workspace.id + '/projects'}>
  <input name="_token" type="hidden" value={data.form.csrfToken} />
  <label htmlFor="project-name">Project name</label>
  <input
    id="project-name"
    name="name"
    type="text"
    defaultValue={data.form.oldName}
    minLength={2}
    maxLength={60}
    required
    aria-invalid={data.form.nameError !== undefined}
  />
  {data.form.nameError !== undefined && (
    <p id="project-name-error" role="alert">{data.form.nameError}</p>
  )}
  <button type="submit">Create project</button>
</form>
```

## Replace the PHP markup with a shell

In `resources/views/workspaces/show.view.php`, build `$pageData` from the confirmed
workspace and existing form flash state:

```php
$pageData = json_encode(
    [
        'workspace' => ['id' => $workspaceId, 'name' => $workspaceName],
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
```

Replace the CSS and page markup with the Vite shell:

```php
<title><?= htmlspecialchars($workspaceName, ENT_QUOTES, 'UTF-8') ?> · DALT Issues</title>
<?= vite('resources/app/main.tsx') ?>
</head>
<body data-page="workspace">
  <div id="root"></div>
  <script id="workspace-detail-page-data" type="application/json"><?= $pageData ?></script>
  <noscript>This page needs JavaScript to show the workspace projects.</noscript>
</body>
```

Add the workspace branch to `resources/app/main.tsx` before the project branch:

```tsx
} else if (document.body.dataset.page === 'workspace') {
  const data = readWorkspaceDetailPageData()
  const router = createBrowserRouter([
    {
      path: '/workspaces/:workspaceId',
      element: <AppLayout />,
      children: [
        { index: true, element: <WorkspaceDetailPage data={data} /> },
      ],
    },
  ])
  applicationScreen = <RouterProvider router={router} />
} else {
```

## Test the visible states

Create `resources/app/workspace-detail-workflow.test.tsx`. One test should return a project
and prove its nested link, issue count, and total:

```tsx
server.use(http.get('/api/workspaces/2/projects', () => HttpResponse.json({
  projects: [{ id: 7, name: 'Release', issueCount: 3 }],
})))

renderWorkspaceDetail()

expect(screen.getByRole('status')).toHaveTextContent('Loading projects')
expect(await screen.findByRole('link', { name: /Release/i }))
  .toHaveAttribute('href', '/workspaces/2/projects/7')
expect(screen.getByText('3 issues')).toBeInTheDocument()
expect(screen.getByText('1 project')).toBeInTheDocument()
```

Add a second test whose first request returns 503 and whose retry returns an empty list.
Drive the `Try again` button and assert the `No projects yet` heading.

Run all affected boundaries:

```bash
php artisan test tests/Feature/IssueApiTest.php
npm run typecheck
npm run lint
npm test
npm run build
```

The backend file should report seven tests and 63 assertions. The frontend should report
ten tests across three files.

Open an existing `/workspaces/{id}` URL directly and refresh it. Its heading should render,
then its projects and real issue counts should arrive. A missing workspace URL must still
return 404. At a narrow viewport the create panel should move below the project collection.
Creating a project still makes one intentional reload.

If Git is available, save the checkpoint:

```bash
git add routes/routes.php app/Http/controllers/api/projects/index.php \
  app/Http/controllers/workspaces/show.php resources/app \
  resources/views/workspaces/show.view.php tests/Feature/IssueApiTest.php
git commit -m "Move the project list to React"
```

The workspace detail is now React-owned without weakening deep-link behavior. Next we will
move project creation to JSON and remove that screen's last reload.
