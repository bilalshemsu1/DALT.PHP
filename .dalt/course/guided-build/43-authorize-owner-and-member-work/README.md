# Authorize owner and member work

Membership answers “does this user belong here?” Role answers “what may they do?” We
will define that distinction once on the server: owners and members collaborate on
projects and issues, while only owners rename or delete the workspace and manage its
people.

## Name capabilities instead of scattering role strings

Open `app/Http/WorkspaceAccess.php` and add three capability names:

```php
public const COLLABORATE = 'collaborate';
public const MANAGE_WORKSPACE = 'manage_workspace';
public const MANAGE_MEMBERS = 'manage_members';
```

These describe product actions. Controllers should not each invent a comparison such
as `$role === 'owner'`; otherwise the same role can acquire different meanings in
different files.

Let `findOrFail` accept a capability while preserving collaboration as the default:

```php
public static function findOrFail(
    Database $database,
    int $workspaceId,
    string $capability = self::COLLABORATE,
): array {
    // Keep the authenticated-user lookup and membership join.
```

After the join finds the membership, enforce the owner-only actions:

```php
$ownerOnly = [
    self::MANAGE_WORKSPACE,
    self::MANAGE_MEMBERS,
];

if (
    in_array($capability, $ownerOnly, true)
    && $workspace['role'] !== 'owner'
) {
    abort(403);
}

return $workspace;
```

The order creates two deliberate boundaries:

- no membership produces 404, so an outsider cannot use the response to confirm a
  foreign workspace;
- a known member asking for an owner action produces 403, because the resource is
  known but their role is insufficient.

Authentication remains outside this policy. API middleware returns 401 before a
controller runs, and private page middleware redirects a guest through login.

## Protect workspace administration

In `app/Http/controllers/api/workspaces/update.php`, request the capability explicitly:

```php
$workspace = WorkspaceAccess::findOrFail(
    $database,
    (int) $workspaceId,
    WorkspaceAccess::MANAGE_WORKSPACE,
);
```

Use the same call in `api/workspaces/destroy.php`. All project and issue controllers
keep their existing two-argument call, so both roles can list, create, update, change
status, and delete project work.

Our edit and deletion review screens share
`app/Http/controllers/workspaces/show.php`. Choose the page capability from the
already matched path:

```php
$capability = str_ends_with($request->path(), '/edit')
    || str_ends_with($request->path(), '/delete')
        ? WorkspaceAccess::MANAGE_WORKSPACE
        : WorkspaceAccess::COLLABORATE;

$workspace = WorkspaceAccess::findOrFail(
    $database,
    (int) $workspaceId,
    $capability,
);
```

A member may open the workspace itself, but a pasted owner-only edit URL receives
403 before React mounts. Hiding a link is useful interface guidance; this server check
is the security boundary.

## Carry the role through the page boundary

Update `WorkspaceDetailPageData` in
`resources/app/workspace-detail-data.ts`:

```tsx
workspace: {
  id: number
  name: string
  role: 'owner' | 'member'
}
```

The PHP view already serializes the workspace returned by `WorkspaceAccess`, including
its role. Validate that runtime string exactly:

```tsx
const role = stringAt(value.workspace, 'role')

if (role !== 'owner' && role !== 'member') {
  throw new Error('Workspace detail data has an invalid role.')
}

return {
  workspace: {
    id,
    name: stringAt(value.workspace, 'name'),
    role,
  },
  form: { csrfToken: stringAt(value.form, 'csrfToken') },
}
```

In `resources/app/WorkspaceDetailPage.tsx`, render owner actions only for owners:

```tsx
{data.workspace.role === 'owner' && (
  <div className="flex shrink-0 flex-wrap gap-2">
    <Link to="edit" state={{ workspaceName }}>
      Edit workspace
    </Link>
    <Link to="delete" state={{ workspaceName }}>
      Delete workspace
    </Link>
  </div>
)}
```

Keep the existing Tailwind classes on the links. Add visible text near the workspace
description:

```tsx
You are a {data.workspace.role}.
```

Members still see the create-project form and project links. The interface reflects
the same capability matrix the server enforces, without pretending that conditional
rendering is authorization.

## Test owner, member, and outsider separately

In `tests/Feature/WorkspaceAuthorizationTest.php`, add a helper that expects 403 in
the same style as the existing 404 helper:

```php
function expectAuthorizationForbidden(
    Router $router,
    string $method,
    string $uri,
    array $input = [],
): void {
    try {
        authorizationRequest($router, $method, $uri, $input);
        test()->fail("Insufficient role reached {$method} {$uri}.");
    } catch (HttpException $exception) {
        expect($exception->statusCode)->toBe(403);
    }
}
```

Add a third account with no membership. In a focused test, insert Grace as a member
of Ada's workspace and prove ordinary collaboration:

```php
$database->query(
    "INSERT INTO workspace_memberships
        (workspace_id, user_id, role)
     VALUES (1, 2, 'member')",
);
authorizationAs(2, 'grace@example.com');

expect(authorizationRequest(
    $router,
    'GET',
    '/api/workspaces/1/projects',
)->status())->toBe(200);

expect(authorizationRequest(
    $router,
    'POST',
    '/api/workspaces/1/projects',
    ['name' => 'Member project'],
)->status())->toBe(201);
```

Also prove project rename, issue creation, and issue status changes. Then cross the
capability boundary:

```php
expectAuthorizationForbidden(
    $router,
    'POST',
    '/api/workspaces/1',
    ['name' => 'Forbidden'],
);
expectAuthorizationForbidden(
    $router,
    'GET',
    '/workspaces/1/delete',
);
```

Switch to the third, unrelated account and require 404 for the same workspace. This
three-actor test prevents a plausible but wrong implementation that treats all
non-owners alike or accidentally grants members only read access.

Add one React test using member page data. It should find the member role and create
project button, while `Edit workspace` and `Delete workspace` are absent.

Run the focused proof:

```bash
php artisan test \
  tests/Feature/WorkspaceAuthorizationTest.php \
  tests/Feature/IssueApiTest.php
npm run typecheck
npm run lint
npm test -- --run
```

The backend passes eighteen tests with 124 assertions. The React suite now passes
twenty tests, including the role-aware interface. Our authorization policy is ready
for its first owner-only feature: a member directory everyone in the workspace may
view.
