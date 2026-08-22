# Assign issues to workspace members

An issue can now have one responsible person, while remaining explicitly unassigned
when nobody owns the next step. We will enforce that the chosen person belongs to the
issue's workspace instead of trusting an ID submitted by the browser.

> **Helpful background:** PostgreSQL's [constraint documentation](https://www.postgresql.org/docs/18/ddl-constraints.html)
> explains why a foreign key can prove that a user exists but a row-local `CHECK`
> cannot prove membership stored in another table.

## Add the nullable relationship

Create `database/migrations/009_assign_issues_to_members.sql`:

```sql
ALTER TABLE issues
    ADD COLUMN assignee_id BIGINT NULL,
    ADD CONSTRAINT issues_assignee_foreign
        FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE INDEX issues_assignee_open_index
    ON issues (assignee_id, id DESC)
    WHERE status = 'open';
```

`NULL` is a product state: the issue is unassigned. `ON DELETE SET NULL` prevents a
deleted account from leaving a dangling reference. The partial index matches a later
“open work assigned to me” query without indexing closed work unnecessarily.

Run the append-only migration:

```bash
php artisan migrate
```

The new migration should run once and finish with `✓ Success`.

## Validate membership, not merely existence

A foreign key accepts any user in the application, including someone from another
workspace. Create `app/Http/IssueData.php`. Its assignee lookup joins the submitted
user ID to the current workspace membership:

```php
public static function assignee(
    Database $database,
    int $workspaceId,
    mixed $input,
): array|null|false {
    if ($input === null || $input === '') return null;
    if (!is_string($input) || preg_match('/\A[1-9]\d*\z/', $input) !== 1) {
        return false;
    }

    $member = $database->query(
        'SELECT users.id, users.name
         FROM workspace_memberships
         JOIN users ON users.id = workspace_memberships.user_id
         WHERE workspace_memberships.workspace_id = :workspace_id
           AND workspace_memberships.user_id = :user_id',
        ['workspace_id' => $workspaceId, 'user_id' => (int) $input],
    )->find();

    if ($member === false) return false;
    return ['id' => (int) $member['id'], 'name' => (string) $member['name']];
}
```

The three outcomes stay distinct: blank means unassigned, a current member returns
public display data, and malformed or foreign input returns `false` for a validation
response.

In both `api/issues/store.php` and `api/issues/update.php`, resolve the submitted
value only after the nested workspace and project have been authorized:

```php
$assignee = IssueData::assignee(
    $database,
    (int) $workspace['id'],
    $request->input('assignee_id'),
);

if ($assignee === false) {
    return Response::json([
        'errors' => ['assignee' => 'Choose a current workspace member.'],
    ], 422);
}
```

Add `assignee_id` to the insert or update parameters. The response should not force
React to join IDs itself, so `IssueData::present()` returns either `null` or the
small public snapshot:

```php
'assignee' => $assigneeId === null ? null : [
    'id' => (int) $assigneeId,
    'name' => (string) ($issue['assignee_name'] ?? ''),
],
```

Use the same presenter from the collection, detail, create, update, and status
responses. A single response shape keeps every React route honest as the issue grows.

## Clear assignments when membership ends

Removing a membership does not delete the user, so the foreign key cannot detect
that product event. In both `api/members/destroy.php` and `api/members/leave.php`,
clear matching assignments before deleting the membership:

```php
$database->query(
    'UPDATE issues SET assignee_id = NULL
     WHERE assignee_id = :user_id
       AND project_id IN (
           SELECT id FROM projects WHERE workspace_id = :workspace_id
       )',
    ['workspace_id' => $workspace['id'], 'user_id' => $userId],
);
```

Keep this statement inside the existing membership transaction. If the membership
deletion fails, the assignment clearing rolls back too; the application never exposes
a half-removed member.

## Carry the assignee through React

Extend `Issue` in `resources/app/project-page-data.ts`:

```tsx
export type Issue = {
  id: number
  title: string
  description: string
  status: IssueStatus
  assignee: { id: number; name: string } | null
}
```

Runtime parsing must also reject malformed objects. Do not make the TypeScript type a
promise the network never proves.

The existing member endpoint supplies the choices. Load it with `fetchMembers()` in
the create and edit screens, then render a native select:

```tsx
<label htmlFor="issue-assignee">Assignee</label>
<select
  id="issue-assignee"
  value={assigneeId}
  onChange={(event) => setAssigneeId(event.target.value)}
>
  <option value="">Unassigned</option>
  {members.map((member) => (
    <option key={member.id} value={member.id}>{member.name}</option>
  ))}
</select>
```

Send the selected string as `assignee_id`; the server remains responsible for
membership validation. Show `Unassigned` or `Assigned to Grace` on issue rows and the
detail page. On edit, initialize the select from the server-confirmed issue.

## Prove the boundary

Add a backend test that first submits a real user who is not a member and expects
422. Add that user as a member, assign and unassign an issue, assign it again, remove
the member, and finally query PostgreSQL to prove `assignee_id` became `NULL`.

Add an MSW test that selects a member, checks the submitted `assignee_id`, and renders
the assignee only from the confirmed response. Run:

```bash
php artisan test \
  tests/Feature/IssueApiTest.php \
  tests/Feature/WorkspaceAuthorizationTest.php
npm run typecheck
npm run lint
npm test -- --run \
  resources/app/issue-workflow.test.tsx \
  resources/app/members-workflow.test.tsx
```

The backend passes 25 tests with 187 assertions. The focused React run passes 11
tests. We now have real responsibility on an issue; next we will add priority and a
calendar date so a team can distinguish urgency from ownership.
