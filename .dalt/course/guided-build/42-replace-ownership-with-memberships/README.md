# Replace ownership with memberships

One `owner_id` was enough while a workspace belonged to one account. Collaboration
needs a relationship with its own data: which user belongs to which workspace, what
role they have, and when they joined. We will migrate every existing owner safely,
then make that relationship our only source of access.

## Create and backfill the relationship

Create `database/migrations/007_create_workspace_memberships_table.sql`:

```sql
CREATE TABLE workspace_memberships (
    workspace_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    role VARCHAR(20) NOT NULL,
    joined_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT workspace_memberships_primary
        PRIMARY KEY (workspace_id, user_id),
    CONSTRAINT workspace_memberships_workspace_foreign
        FOREIGN KEY (workspace_id)
        REFERENCES workspaces(id) ON DELETE CASCADE,
    CONSTRAINT workspace_memberships_user_foreign
        FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT workspace_memberships_role_check
        CHECK (role IN ('owner', 'member'))
);
```

The two IDs form the primary key because one user may belong to many workspaces but
may belong to the same workspace only once. The role check keeps misspellings and
invented privileges out of durable data.

Our first common lookup starts with the current user and asks for their workspaces,
so add an index in that order:

```sql
CREATE INDEX workspace_memberships_user_workspace_index
    ON workspace_memberships (user_id, workspace_id DESC);
```

Do not drop `owner_id` yet. First copy every existing relationship:

```sql
INSERT INTO workspace_memberships (
    workspace_id,
    user_id,
    role,
    joined_at
)
SELECT id, owner_id, 'owner', created_at
FROM workspaces;
```

Migration 006 already made `owner_id` non-null and added its user foreign key. That
means every workspace produces exactly one valid owner membership. Only after the
copy succeeds should the same migration remove the transitional column:

```sql
ALTER TABLE workspaces DROP COLUMN owner_id;
```

PostgreSQL executes the migration as a transaction through DALT's migrator. A failed
copy therefore cannot leave half the workspaces on the new model.

Run it:

```bash
php artisan migrate
```

Inspect the result in `psql`:

```sql
SELECT workspace_id, user_id, role
FROM workspace_memberships
ORDER BY workspace_id;

SELECT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_name = 'workspaces'
      AND column_name = 'owner_id'
);
```

Every old workspace should have one `owner` row, and the second query should return
`f`. We have moved the relationship instead of duplicating it.

## Read workspaces through membership

Open `app/Http/WorkspaceAccess.php`. Keep the authenticated user lookup, but replace
the owner predicate with a membership join:

```php
return $database
    ->query(
        'SELECT workspaces.id,
                workspaces.name,
                workspaces.created_at,
                workspace_memberships.role
         FROM workspaces
         INNER JOIN workspace_memberships
            ON workspace_memberships.workspace_id = workspaces.id
           AND workspace_memberships.user_id = :user_id
         WHERE workspaces.id = :id',
        [
            'id' => $workspaceId,
            'user_id' => $userId,
        ],
    )
    ->findOrFail();
```

The `INNER JOIN` is the authorization boundary: a workspace row is visible only when
the current user has a matching membership. `findOrFail` preserves our existing 404
behavior for outsiders. Returning `role` prepares the next lesson without deciding
permissions here.

Make the same change in `app/Http/controllers/api/workspaces/index.php`:

```php
$workspaces = $database->query(
    'SELECT workspaces.id,
            workspaces.name,
            workspace_memberships.role,
            COUNT(projects.id) AS project_count
     FROM workspaces
     INNER JOIN workspace_memberships
        ON workspace_memberships.workspace_id = workspaces.id
       AND workspace_memberships.user_id = :user_id
     LEFT JOIN projects
        ON projects.workspace_id = workspaces.id
     GROUP BY workspaces.id,
              workspaces.name,
              workspace_memberships.role
     ORDER BY workspaces.id DESC',
    ['user_id' => $userId],
)->get();
```

Include the role in each JSON summary:

```php
[
    'id' => (int) $workspace['id'],
    'name' => (string) $workspace['name'],
    'role' => (string) $workspace['role'],
    'projectCount' => (int) $workspace['project_count'],
]
```

Update `WorkspaceSummary` in `resources/app/workspace-data.ts` with:

```tsx
role: 'owner' | 'member'
```

At the runtime boundary, require the string to be exactly `owner` or `member` before
returning it. TypeScript's union is useful only after unknown JSON has earned it.

## Create a workspace and its owner together

A new workspace now needs two rows. In
`app/Http/controllers/api/workspaces/store.php`, import our transaction helper:

```php
use App\Support\Transaction;
```

After validation and authenticated user lookup, replace the old insert:

```php
$workspaceId = Transaction::run(
    $database,
    function () use ($database, $userId, $name): int {
        $database->query(
            'INSERT INTO workspaces (name) VALUES (:name)',
            ['name' => $name],
        );
        $workspaceId = (int) $database
            ->getConnection()
            ->lastInsertId();

        $database->query(
            "INSERT INTO workspace_memberships
                (workspace_id, user_id, role)
             VALUES (:workspace_id, :user_id, 'owner')",
            [
                'workspace_id' => $workspaceId,
                'user_id' => $userId,
            ],
        );

        return $workspaceId;
    },
);
```

Return `'role' => 'owner'` with the new workspace summary. Apply the same two-row
transaction to the older server-form controller at
`app/Http/controllers/workspaces/store.php`. It is not the main React path anymore,
but leaving it able to create an inaccessible workspace would make the application
internally inconsistent.

Registration no longer needs its transitional “first account claims ownerless
workspaces” update. Remove that branch from
`app/Http/controllers/api/auth/register.php`; the database can no longer contain an
ownerless workspace after this migration.

## Keep development imports usable

Our SQLite source still has `owner_id`, while PostgreSQL no longer does. In
`scripts/import-sqlite-to-postgresql.php`, copy only these workspace columns:

```php
'workspaces' => ['id', 'name', 'created_at'],
```

Before the transaction, load the old relationships:

```php
$workspaceOwners = $source
    ->query(
        'SELECT id, owner_id, created_at
         FROM workspaces ORDER BY id',
    )
    ->get();
```

After copying the four entity tables, translate those rows:

```php
foreach ($workspaceOwners as $workspace) {
    $target->query(
        "INSERT INTO workspace_memberships
            (workspace_id, user_id, role, joined_at)
         VALUES (:workspace_id, :user_id, 'owner', :joined_at)",
        [
            'workspace_id' => $workspace['id'],
            'user_id' => $workspace['owner_id'],
            'joined_at' => $workspace['created_at'],
        ],
    );
}
```

Include `workspace_memberships` in the empty-target preflight and row-count proof.
There is no identity sequence to repair because its primary key is the pair of IDs.

Prove the whole reset and translation path:

```bash
php artisan migrate:fresh --force
php scripts/import-sqlite-to-postgresql.php database/app.sqlite
```

Our import reports four users, six workspaces, four projects, three issues, and six
memberships. The six old workspaces still belong to exactly the same accounts.

## Prove the new invariant in tests

Update PostgreSQL fixtures to insert workspaces first and memberships second:

```php
$database->query(
    "INSERT INTO workspaces (name) VALUES
     ('Ada workspace'), ('Grace workspace')",
);
$database->query(
    "INSERT INTO workspace_memberships
        (workspace_id, user_id, role) VALUES
     (1, 1, 'owner'), (2, 2, 'owner')",
);
```

Change collection expectations to include `role`, snapshot the membership table in
authorization tests, and prove workspace creation ignores a forged user ID and adds
the session actor as `owner`. The transaction test should now insert the workspace
and membership together.

Run the focused application proof:

```bash
php artisan test \
  tests/Feature/AuthenticationTest.php \
  tests/Feature/IssueApiTest.php \
  tests/Feature/WorkspaceAuthorizationTest.php \
  tests/Feature/TransactionTest.php
npm run typecheck
npm run lint
npm test -- --run
```

The backend passes 29 tests with 158 assertions, and all nineteen React tests remain
green. Existing accounts see the same workspaces after the migration, but the schema
can now represent a real team. Next we will turn the stored role into one consistent
capability policy.
