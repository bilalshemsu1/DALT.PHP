# Show the member directory

Our workspace can now contain several people, but the product does not show them. We
will add a directory that owners and members may both open, return only public account
fields, and represent every request state in React.

## Add page and API locations

In `routes/routes.php`, add two routes beside the workspace routes:

```php
$router->get(
    '/workspaces/{workspace}/members',
    'workspaces/show.php',
)->only('auth');

$router->get(
    '/api/workspaces/{workspace}/members',
    'api/members/index.php',
)->only(ApiAuth::class);
```

The page reuses our workspace shell controller. It already validates the ID, requires
membership through `WorkspaceAccess`, and gives React the workspace name and role.
The JSON endpoint gets its own controller because it returns a new resource collection.

## Return public member data only

Create `app/Http/controllers/api/members/index.php`. Validate the route ID, resolve
the database, and require ordinary collaboration access:

```php
$database = App::resolve(Database::class);
$workspace = WorkspaceAccess::findOrFail(
    $database,
    (int) $workspaceId,
);
```

Load the directory through the membership relationship:

```php
$members = $database->query(
    'SELECT users.id,
            users.name,
            users.email,
            workspace_memberships.role,
            workspace_memberships.joined_at
     FROM workspace_memberships
     INNER JOIN users
        ON users.id = workspace_memberships.user_id
     WHERE workspace_memberships.workspace_id = :workspace_id
     ORDER BY
        CASE workspace_memberships.role
            WHEN \'owner\' THEN 0 ELSE 1
        END,
        users.name,
        users.id',
    ['workspace_id' => $workspace['id']],
)->get();
```

Owners appear first, then names are stable alphabetically. Return an explicit public
shape:

```php
return Response::json([
    'members' => array_map(
        static fn (array $member): array => [
            'id' => (int) $member['id'],
            'name' => (string) $member['name'],
            'email' => (string) $member['email'],
            'role' => (string) $member['role'],
            'joinedAt' => (string) $member['joined_at'],
        ],
        $members,
    ),
]);
```

Selecting columns deliberately matters. `SELECT users.*` would make it easy for a
future refactor to serialize the password hash or other private account data. The
directory contract contains only what this screen uses.

## Validate the new JSON boundary

Create `resources/app/members-data.ts` and define the client type:

```tsx
export type WorkspaceMember = {
  id: number
  name: string
  email: string
  role: 'owner' | 'member'
  joinedAt: string
}
```

Parse unknown JSON before the rest of React sees it:

```tsx
export function parseMembers(value: unknown): WorkspaceMember[] {
  if (!isRecord(value) || !Array.isArray(value.members)) {
    throw new Error('The member list is invalid.')
  }

  return value.members.map((member) => {
    if (!isRecord(member)) throw new Error('A member is invalid.')

    const { id, name, email, role, joinedAt } = member
    if (
      !Number.isInteger(id)
      || Number(id) < 1
      || typeof name !== 'string'
      || typeof email !== 'string'
      || typeof joinedAt !== 'string'
      || (role !== 'owner' && role !== 'member')
    ) {
      throw new Error('A member is invalid.')
    }

    return { id: Number(id), name, email, role, joinedAt }
  })
}
```

Add `fetchMembers(workspaceId, signal)`. Send `Accept: application/json`, call our
central `requireAuthenticatedResponse`, require an OK response, and pass its JSON to
`parseMembers`.

## Build the directory screen

Create `resources/app/MembersPage.tsx`. Model its request honestly:

```tsx
type State =
  | { status: 'loading' }
  | { status: 'failed' }
  | { status: 'ready'; members: WorkspaceMember[] }
```

Fetch whenever the workspace or retry attempt changes:

```tsx
useEffect(() => {
  const controller = new AbortController()
  setState({ status: 'loading' })

  void fetchMembers(data.workspace.id, controller.signal)
    .then((members) => setState({ status: 'ready', members }))
    .catch((error: unknown) => {
      if (!(error instanceof DOMException
        && error.name === 'AbortError')) {
        setState({ status: 'failed' })
      }
    })

  return () => controller.abort()
}, [data.workspace.id, attempt])
```

Render a back link and heading first. For loading, use a short `role="status"`
message. For failure, use `role="alert"` and a real retry button that increments
`attempt`. A workspace should always have an owner, but still render an actionable
empty state if corrupted data ever reaches this screen.

Render ready members as a semantic list:

```tsx
<ul aria-label="Workspace members">
  {state.members.map((member) => (
    <li key={member.id}>
      <span>
        <strong>{member.name}</strong>
        <span>{member.email}</span>
      </span>
      <span className="capitalize">
        {member.role}
      </span>
    </li>
  ))}
</ul>
```

Keep the full Tailwind styling from our application around this structure. The role
word remains visible text; color is reinforcement, never the only distinction.

## Connect the client route

Import `MembersPage` in `resources/app/main.tsx` and add it under the workspace route:

```tsx
{ path: 'members', element: <MembersPage data={data} /> },
```

In `WorkspaceDetailPage.tsx`, add a link visible to both roles:

```tsx
<Link to="members">View members</Link>
```

The resulting URL is `/workspaces/:workspaceId/members`. DALT serves the shell when
it is refreshed, while React handles navigation after the application is running.

## Prove privacy, access, and request states

Extend `WorkspaceAuthorizationTest.php`: add a member, request the directory as that
member, and require exactly `id`, `name`, `email`, `role`, and `joinedAt`. Explicitly
assert that `password` is absent. Switch to an outsider and require 404.

Create `resources/app/members-workflow.test.tsx`. One MSW response should contain an
owner and member; expect both public emails and both visible role labels. A second
test returns 503 once, clicks `Try again`, then returns the empty array and reaches
the repair-oriented empty state.

Run the proof:

```bash
php artisan test tests/Feature/WorkspaceAuthorizationTest.php
npm run typecheck
npm run lint
npm test -- --run
npm run build
```

The authorization file now passes six tests with 57 assertions. The React suite has
22 passing tests across six files. We can see who belongs to a workspace without
exposing secrets or giving members owner controls. Next we will let owners invite a
new person securely.
