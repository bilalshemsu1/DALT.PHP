# Manage members without losing an owner

A directory is useful only when the team can evolve. Owners need to promote, demote,
and remove people; members need to leave; pending invitations need revocation. Every
path must preserve one invariant under retries and concurrent requests: a workspace
always has at least one owner.

## Add the four mutation shapes

Add these protected routes in `routes/routes.php`:

```php
$router->post(
    '/api/workspaces/{workspace}/members/{member}',
    'api/members/update.php',
)->only([ApiAuth::class, 'csrf']);

$router->delete(
    '/api/workspaces/{workspace}/members/{member}',
    'api/members/destroy.php',
)->only([ApiAuth::class, 'csrf']);

$router->delete(
    '/api/workspaces/{workspace}/membership',
    'api/members/leave.php',
)->only([ApiAuth::class, 'csrf']);

$router->delete(
    '/api/workspaces/{workspace}/invitations/{invitation}',
    'api/invitations/destroy.php',
)->only([ApiAuth::class, 'csrf']);
```

Role change, removal, and revocation require `MANAGE_MEMBERS`. Leaving requires only
membership because a member must be able to remove themselves. CSRF protects all four.

## Lock the complete ownership decision

Counting owners before a transaction is unsafe. Two owners could both see a count of
two and leave at the same time. Create `app/Http/MemberManagement.php` with one lock:

```php
public static function lock(
    Database $database,
    int $workspaceId,
): array {
    return $database->query(
        'SELECT workspace_id, user_id, role
         FROM workspace_memberships
         WHERE workspace_id = :workspace_id
         ORDER BY user_id
         FOR UPDATE',
        ['workspace_id' => $workspaceId],
    )->get();
}
```

Locking every membership in the workspace serializes decisions that might change its
owner count. The fixed `ORDER BY` also gives concurrent callers the same lock order.

Add `findOrFail($members, $userId)` to locate a target inside the locked snapshot, and
the invariant check:

```php
public static function isLastOwner(
    array $members,
    array $target,
): bool {
    if ($target['role'] !== 'owner') return false;

    return count(array_filter(
        $members,
        static fn (array $member): bool =>
            $member['role'] === 'owner',
    )) === 1;
}
```

This small support class does not decide who may administer people. `WorkspaceAccess`
still owns that capability boundary. It only answers questions about the locked
membership state.

## Change roles transactionally

Create `api/members/update.php`. Validate both route IDs and require `owner` or
`member`. Resolve the workspace with `MANAGE_MEMBERS`, then run:

```php
$result = Transaction::run(
    $database,
    function () use (
        $database,
        $workspace,
        $memberId,
        $role,
    ): bool {
        $members = MemberManagement::lock(
            $database,
            (int) $workspace['id'],
        );
        $target = MemberManagement::findOrFail(
            $members,
            (int) $memberId,
        );

        if (
            $role === 'member'
            && MemberManagement::isLastOwner($members, $target)
        ) {
            return false;
        }

        $database->query(
            'UPDATE workspace_memberships SET role = :role
             WHERE workspace_id = :workspace_id
               AND user_id = :user_id',
            [
                'role' => $role,
                'workspace_id' => $workspace['id'],
                'user_id' => (int) $memberId,
            ],
        );

        return true;
    },
);
```

Return 422 with “A workspace must keep at least one owner” when the closure returns
false. Otherwise return the confirmed member ID and role.

## Remove and leave through the same invariant

`api/members/destroy.php` has the same lock and target lookup. If the target is the
last owner, return 422; otherwise delete that membership. We have no assignee data yet,
so there are no issue relationships to clear. Lesson 48 will add that rule when the
data first exists.

`api/members/leave.php` gets the current user ID rather than a submitted member ID:

```php
$userId = (new Authenticator())->id();
```

Inside the transaction, lock, find the current user's membership, reject the final
owner, and delete it. Return `redirectTo: '/'` only after PostgreSQL confirms the
change. An owner may leave when another owner remains; a sole owner must promote
someone first.

## Serialize revocation with acceptance

Create `api/invitations/destroy.php`. Require owner capability and select the matching
workspace invitation `FOR UPDATE` inside `Transaction::run`. Acceptance locks the same
row, so these two actions cannot both decide from stale timestamps.

Only a row with both `accepted_at` and `revoked_at` null may be revoked:

```php
$database->query(
    'UPDATE workspace_invitations
     SET revoked_at = CURRENT_TIMESTAMP
     WHERE id = :id',
    ['id' => $invitation['id']],
);
```

Return 422 for an already accepted or revoked invitation, and 404 when the ID does not
belong to this workspace. The acceptance endpoint will see the committed revocation
and return 410.

## Add focused client mutations

In `resources/app/members-data.ts`, add one internal `mutate` helper that posts a CSRF
token and optional method override, runs the centralized 401 behavior, and extracts a
server error from a failed JSON response.

Build three focused functions on it:

```tsx
changeMemberRole(workspaceId, userId, csrfToken, role)
removeMember(workspaceId, userId, csrfToken)
leaveWorkspace(workspaceId, csrfToken)
```

The last requires a string `redirectTo`. In `invitations-data.ts`, add
`revokeInvitation` with `_method: 'DELETE'`.

## Update the directory from confirmed results

In `MembersPage.tsx`, get the current user from `useSession`. Owners see a role select
and Remove button for other people. The current user sees their role badge and uses
the separate `Leave workspace` action; this avoids leaving stale owner controls on
screen after self-demotion.

After a successful role response, update only that member:

```tsx
setState((current) => current.status === 'ready'
  ? {
      status: 'ready',
      members: current.members.map((item) =>
        item.id === member.id ? { ...item, role } : item),
    }
  : current)
```

After confirmed removal, filter the row. Do not optimistically hide it while the
server might still reject the last-owner operation. Display the server's 422 message
in the page notice.

The leave button navigates to the returned root only after success. A rejected sole
owner remains on the directory and sees “Transfer ownership before leaving this
workspace.”

In `InvitationPanel.tsx`, show Revoke only for pending invitations. After the server
confirms, change that row's status to `revoked`; the raw link immediately stops working.

## Prove the complete transition sequence

Add a backend test with one owner and two members. Perform this sequence:

1. promote a member so two owners exist;
2. remove the unrelated member;
3. demote the original owner;
4. reject demotion and removal of the remaining owner;
5. let the original member leave;
6. reject the final owner's attempt to leave;
7. create and revoke a pending invitation.

Also attempt an owner action as an ordinary member and require 403. After every
rejected last-owner operation, query PostgreSQL and require one owner still exists.
This catches a fake implementation that returns the right message after already
changing the row.

Extend `members-workflow.test.tsx`: return two members, change one role through the
select, and remove them. Assert the UI changes only after each MSW response. Existing
authorization tests continue to cover outsider 404 and CSRF middleware.

Run the batch's focused proof:

```bash
php artisan test tests/Feature/WorkspaceAuthorizationTest.php
npm run typecheck
npm run lint
npm test -- --run
npm run build
```

The authorization file passes ten tests with 90 assertions. React passes 28 tests
across eight files. A workspace can now gain people, change responsibilities, and
lose people without ever losing its final owner. Our collaboration batch is complete;
next we can make issues team-aware by assigning them to these workspace members.
