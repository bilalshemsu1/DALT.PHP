# Build membership-scoped dashboard views

Project filters help when we already know where to look. We will add one dashboard
that gathers assigned, overdue, recent, and workspace-level work across every
workspace the signed-in user may actually open.

## Register a page and an authenticated API

Add these routes in `routes/routes.php`:

```php
$router->get('/dashboard', 'welcome.php')->only('auth');
$router->get('/api/dashboard', 'api/dashboard/index.php')
    ->only(ApiAuth::class);
```

The page route reuses our existing React shell, so refreshing or pasting
`/dashboard` works. The API uses the JSON authentication boundary and still checks
the current user inside its query controller.

## Scope every dashboard query through membership

Create `app/Http/controllers/api/dashboard/index.php`. Resolve the actor before
querying:

```php
$userId = (new Authenticator())->id();
if ($userId === null) abort(401);

$database = App::resolve(Database::class);
```

Define the shared issue projection and, most importantly, its membership join:

```php
$select = 'SELECT issues.id, issues.title, issues.status,
                  issues.priority, issues.due_date,
                  projects.id AS project_id,
                  projects.name AS project_name,
                  workspaces.id AS workspace_id,
                  workspaces.name AS workspace_name
           FROM issues
           JOIN projects ON projects.id = issues.project_id
           JOIN workspaces ON workspaces.id = projects.workspace_id
           JOIN workspace_memberships
             ON workspace_memberships.workspace_id = workspaces.id
            AND workspace_memberships.user_id = :user_id';
```

This join is the authorization boundary for every dashboard issue. We do not fetch
all issues and remove foreign rows in PHP. Unauthorized data never leaves PostgreSQL.

## Find work assigned to the actor

Assigned work is useful while it remains open. Put dated work first and settle ties
with the unique ID:

```php
$assigned = $database->query(
    $select . "
     WHERE issues.assignee_id = :assignee_id
       AND issues.status = 'open'
     ORDER BY issues.due_date ASC NULLS LAST, issues.id DESC
     LIMIT 6",
    ['user_id' => $userId, 'assignee_id' => $userId],
)->get();
```

Both conditions matter. `assignee_id` alone proves assignment, not membership, and a
membership alone does not mean this work is assigned to the actor.

## Find overdue open work

Use PostgreSQL's calendar date on the same connection:

```php
$overdue = $database->query(
    $select . "
     WHERE issues.status = 'open'
       AND issues.due_date < CURRENT_DATE
     ORDER BY issues.due_date ASC, issues.id DESC
     LIMIT 6",
    ['user_id' => $userId],
)->get();
```

Closed issues are deliberately absent even if their due date is in the past. The
dashboard is an action surface, not a historical report.

## Derive recent movement from activity

Our issues do not have a generic `updated_at` column. Their append-only activity is a
more honest record of meaningful work. Sort by the latest event, falling back to
creation for an issue with no event:

```php
$recent = $database->query(
    $select . '
     ORDER BY COALESCE(
        (SELECT MAX(issue_activity.created_at)
         FROM issue_activity
         WHERE issue_activity.issue_id = issues.id),
        issues.created_at
     ) DESC, issues.id DESC
     LIMIT 6',
    ['user_id' => $userId],
)->get();
```

Comments, status changes, planning changes, and label changes can now move an issue
into the recent card without maintaining a second timestamp contract.

## Count open work per authorized workspace

The final query starts from memberships so a workspace with no projects or issues
still appears with zero:

```php
$workspaces = $database->query(
    "SELECT workspaces.id, workspaces.name,
            COUNT(issues.id) FILTER (
              WHERE issues.status = 'open'
            ) AS open_issue_count
     FROM workspaces
     JOIN workspace_memberships
       ON workspace_memberships.workspace_id = workspaces.id
      AND workspace_memberships.user_id = :user_id
     LEFT JOIN projects ON projects.workspace_id = workspaces.id
     LEFT JOIN issues ON issues.project_id = projects.id
     GROUP BY workspaces.id, workspaces.name
     ORDER BY workspaces.name, workspaces.id",
    ['user_id' => $userId],
)->get();
```

PostgreSQL's filtered aggregate counts only open issues without turning the outer
joins into inner joins.

Return the four arrays. Each issue contains its own ID, title, status, priority, due
date, workspace identity, and project identity. Do not return descriptions, member
records, or other fields the cards do not use.

## Parse the dashboard response

Create `resources/app/dashboard-data.ts` with these types:

```ts
export type DashboardIssue = {
  id: number
  title: string
  status: IssueStatus
  priority: IssuePriority
  dueDate: string | null
  workspace: { id: number; name: string }
  project: { id: number; name: string }
}

export type DashboardData = {
  assignedToMe: DashboardIssue[]
  overdue: DashboardIssue[]
  recent: DashboardIssue[]
  workspaces: Array<{
    id: number
    name: string
    openIssueCount: number
  }>
}
```

Add runtime parsing for every nested object, ID, status, priority, nullable date, and
non-negative count. Then fetch the protected endpoint:

```ts
export async function fetchDashboard(signal: AbortSignal): Promise<DashboardData> {
  const response = await fetch('/api/dashboard', {
    headers: { Accept: 'application/json' },
    signal,
  })
  requireAuthenticatedResponse(response)
  if (!response.ok) throw new Error('The dashboard could not be loaded.')
  return parseDashboard(await response.json())
}
```

## Give the dashboard one cache key

Create `resources/app/dashboard-queries.ts`:

```ts
export const dashboardKey = ['dashboard'] as const

export const dashboardQuery = () => queryOptions({
  queryKey: dashboardKey,
  queryFn: ({ signal }) => fetchDashboard(signal),
})
```

Unlike an issue collection, this endpoint has no public filters or pagination yet,
so one key completely identifies it.

## Build cards that lead back to the real workflow

Create `resources/app/DashboardPage.tsx` and load the query:

```tsx
const session = useSession()
const query = useQuery(dashboardQuery())

if (query.isPending) {
  return <main role="status">Loading dashboard…</main>
}

if (query.isError) {
  return (
    <main role="alert">
      <h1>Dashboard could not be loaded</h1>
      <button onClick={() => void query.refetch()}>Try again</button>
    </main>
  )
}
```

Render four responsive cards: Assigned to me, Overdue, Recently updated, and Open
work by workspace. Each issue title links to the existing issue detail route. The
assigned and overdue cards also link back to our existing URL-owned views:

```tsx
<a href={
  `/workspaces/${issue.workspace.id}/projects/${issue.project.id}`
  + `?status=open&assignee=${session.session.user.id}`
}>
  Open this view
</a>

<a href={
  `/workspaces/${issue.workspace.id}/projects/${issue.project.id}?due=overdue`
}>
  Open this view
</a>
```

The dashboard is not a disconnected mini-application. It summarizes and routes us
back into the filtering, pagination, editing, comments, and activity screens already
working.

These are ordinary anchors deliberately. The dashboard is mounted by the root router,
while workspace and project pages are bootstrapped into separate router families after
DALT serves their page data. A React Router `Link` would ask the dashboard router to
handle a project URL it does not own and would land on its client-side 404. Use native
anchors for the issue titles, filtered project views, and workspace links so DALT can
serve the correct shell before React takes over again.

Use a real heading for every card, ordered or unordered lists for results, and
meaningful empty copy. “Nothing needs attention here” is success for assigned,
overdue, and recent cards. With no workspaces, say “Join or create a workspace to
begin.”

## Make the route available from the shell

In the workspaces branch of `resources/app/main.tsx`, add the child route:

```tsx
children: [
  { index: true, element: <WorkspaceIndexPage data={data} /> },
  { path: 'dashboard', element: <DashboardPage /> },
]
```

Add a Dashboard link to the primary navigation in `AppLayout.tsx`. We use an ordinary
anchor because the application still has several independently bootstrapped shell
families; the server route makes the destination durable.

## Invalidate summaries when meaningful work changes

Import `dashboardKey` into issue creation, status, edit, deletion, and comment
mutations. Add this promise beside their existing targeted invalidations:

```ts
queryClient.invalidateQueries({ queryKey: dashboardKey })
```

All five can affect at least one dashboard result. A comment changes recent activity;
status changes overdue and counts; planning changes due order; assignment changes the
personal card; creation and deletion change recent results and counts.

If the dashboard is active, it refetches. If it is inactive, it is marked stale and
refetches when we return.

## Prove the authorization boundary with two users

In `tests/Feature/IssueApiTest.php`, assign an open issue to Ada and give it an overdue
date. Give a closed issue an older overdue date. Ada's response contains the open
assigned issue, excludes the closed issue from overdue, counts only her two
workspaces, and never contains Grace's private issue.

Then switch the session to Grace. Her recent list and workspace counts contain only
her private workspace. This is stronger than asserting that a card is hidden in
React: it proves the foreign row never reaches JSON.

Add `resources/app/dashboard-workflow.test.tsx` for loading, populated links and
counts, and a fully empty dashboard. The component test pins the destination URLs;
when we introduce Playwright in Lesson 60, a browser journey will follow these
cross-shell links and prove they do not stop at the dashboard router's 404 boundary.

```bash
php vendor/bin/pest tests/Feature/IssueApiTest.php tests/Feature/WorkspaceAuthorizationTest.php
npm run typecheck
npm run lint
npm test
```

The backend and authorization gate passes 32 tests with 233 assertions. All 39
frontend tests pass. We now have a useful cross-workspace starting point whose data
is membership-scoped at the database. Next we will audit every empty, denied, missing,
loading, and recoverable failure state rather than letting edge cases inherit
accidental copy and layout.
