Authentication tells us which account sent a request. Authorization now has to use
that identity. We will give every workspace an owner and make the owner-qualified
workspace lookup the entrance to every project and issue path.

## Add ownership without losing existing work

Our database already contains workspaces, so this is a data migration as well as a
schema change. Create `database/migrations/005_add_owner_to_workspaces_table.sql`:

```sql
-- Migration: Add workspace ownership

ALTER TABLE workspaces
ADD COLUMN owner_id INTEGER REFERENCES users(id);

CREATE INDEX idx_workspaces_owner_id ON workspaces(owner_id);

UPDATE workspaces
SET owner_id = (SELECT id FROM users ORDER BY id LIMIT 1)
WHERE owner_id IS NULL;
```

The new column begins nullable because a clean database may run every migration before
any account exists. When an account already exists, the update assigns old workspaces
to the first one. When no account exists yet, the rows remain temporarily unclaimed
and registration will handle them.

Run the migration against the application we have built:

```bash
php artisan migrate
```

DALT should report `005_add_owner_to_workspaces_table.sql` as successful.

## Let the first registration claim legacy rows

Update `app/Http/controllers/api/auth/register.php`. Account creation and legacy
ownership must either both happen or both roll back, so begin a transaction before
the user insert:

```php
$connection = $database->getConnection();
$connection->beginTransaction();

try {
    $database->query(
        'INSERT INTO users (name, email, password)
         VALUES (:name, :email, :password)',
        [
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ],
    );
    $userId = (int) $connection->lastInsertId();
```

Capture the inserted ID before another statement changes the connection's last insert
state. Then count accounts and claim only when this is the first:

```php
$userCount = $database
    ->query('SELECT COUNT(*) AS aggregate FROM users')
    ->find();

if ((int) ($userCount['aggregate'] ?? 0) === 1) {
    $database->query(
        'UPDATE workspaces
         SET owner_id = :owner_id
         WHERE owner_id IS NULL',
        ['owner_id' => $userId],
    );
}

$connection->commit();
```

Close the `try` with the same rollback pattern used by our destructive transactions:

```php
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    throw $exception;
}

$user = ['id' => $userId, 'email' => $email];
```

A second account never claims ownerless data accidentally. Later product features may
introduce explicit invitations or transfers; we are not inventing those before the
product needs them.

## Create workspaces for the session actor

In both current JSON creation and the older PHP creation controller, read identity
from DALT—not from form data:

```php
use Core\Authenticator;

$ownerId = (new Authenticator())->id();

if ($ownerId === null) {
    abort(401);
}
```

The route middleware already requires authentication. This local check makes the
write fail closed if the controller is ever reached another way.

Change the insert in `app/Http/controllers/api/workspaces/store.php`:

```php
$database->query(
    'INSERT INTO workspaces (owner_id, name)
     VALUES (:owner_id, :name)',
    ['owner_id' => $ownerId, 'name' => $name],
);
```

Apply the same insert to `app/Http/controllers/workspaces/store.php`. There is no
`owner_id` input in React or HTML. A browser cannot choose another owner.

## Filter the home collection

Update `app/Http/controllers/api/workspaces/index.php` to obtain the authenticated ID,
then bind it into the collection query:

```php
$ownerId = (new Authenticator())->id();

if ($ownerId === null) {
    abort(401);
}

$workspaces = $database->query(
    'SELECT workspaces.id, workspaces.name,
            COUNT(projects.id) AS project_count
     FROM workspaces
     LEFT JOIN projects ON projects.workspace_id = workspaces.id
     WHERE workspaces.owner_id = :owner_id
     GROUP BY workspaces.id, workspaces.name
     ORDER BY workspaces.id DESC',
    ['owner_id' => $ownerId],
)->get();
```

Filtering the list is useful UX, but it is not authorization by itself. A person can
still type a predictable `/workspaces/3` URL. Every individual lookup must enforce the
same rule.

## Centralize the repeated security boundary

Workspace lookup now appears throughout workspace, project, and issue controllers.
This repetition carries one security invariant, so it has earned one small shared
class. Create `app/Http/WorkspaceAccess.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http;

use Core\Authenticator;
use Core\Database;

final class WorkspaceAccess
{
    /** @return array<string, mixed> */
    public static function findOrFail(
        Database $database,
        int $workspaceId,
    ): array {
        $ownerId = (new Authenticator())->id();

        if ($ownerId === null) {
            abort(401);
        }

        return $database
            ->query(
                'SELECT id, name, created_at
                 FROM workspaces
                 WHERE id = :id AND owner_id = :owner_id',
                ['id' => $workspaceId, 'owner_id' => $ownerId],
            )
            ->findOrFail();
    }
}
```

`findOrFail` returns 404 for both a missing workspace and a workspace owned by another
account. That response does not reveal whether a guessed ID exists. The actual access
rule is still readable in one query: both ID and session owner must match.

## Put the boundary before every nested lookup

Replace the direct workspace query in `workspaces/show.php`:

```php
use App\Http\WorkspaceAccess;

$database = App::resolve(Database::class);
$workspace = WorkspaceAccess::findOrFail(
    $database,
    (int) $workspaceId,
);
```

Do the same in:

```text
app/Http/controllers/projects/show.php
app/Http/controllers/projects/store.php
app/Http/controllers/api/workspaces/update.php
app/Http/controllers/api/workspaces/destroy.php
app/Http/controllers/api/projects/index.php
app/Http/controllers/api/projects/store.php
app/Http/controllers/api/projects/update.php
app/Http/controllers/api/projects/destroy.php
app/Http/controllers/api/issues/index.php
app/Http/controllers/api/issues/show.php
app/Http/controllers/api/issues/store.php
app/Http/controllers/api/issues/update.php
app/Http/controllers/api/issues/status.php
app/Http/controllers/api/issues/destroy.php
```

Keep the existing project query immediately after it:

```php
$project = $database
    ->query(
        'SELECT id FROM projects
         WHERE id = :id AND workspace_id = :workspace_id',
        [
            'id' => (int) $projectId,
            'workspace_id' => $workspace['id'],
        ],
    )
    ->findOrFail();
```

Issue controllers then keep their project-qualified issue query. The resulting chain
is important:

```text
session account + workspace ID
              ↓
       owned workspace
              ↓
    project in that workspace
              ↓
      issue in that project
```

An issue does not need its own owner column yet. Its project and workspace ancestry
already establish the boundary.

Also update the older issue mutation controllers and the unused server-rendered issue
controllers. They should not retain an unsafe direct workspace lookup that can be
accidentally reconnected later.

## Adapt the database tests to real ownership

In `IssueApiTest.php`, execute migrations 001 through 005 in dependency order. Seed
two users, two workspaces for user 1, and one private workspace for user 2:

```php
$database->query(
    "INSERT INTO users (name, email, password) VALUES
     ('Ada', 'ada@example.com', 'unused'),
     ('Grace', 'grace@example.com', 'unused')",
);

$database->query(
    "INSERT INTO workspaces (owner_id, name) VALUES
     (1, 'Studio'), (1, 'Archive'), (2, 'Private')",
);
```

Give the private workspace its own project and issue. Keep the test session as user 1.
The existing collection expectation should still contain only Archive and Studio.

When testing workspace creation, select `owner_id` from the stored row and prove it is
1. Then add a foreign-access test:

```php
try {
    issueApiRequest($router, 'GET', '/api/workspaces/3/projects');
    $this->fail('Another account workspace must not expose its projects.');
} catch (HttpException $exception) {
    expect($exception->statusCode)->toBe(404);
}

try {
    issueApiRequest($router, 'POST', '/api/workspaces/3', [
        'name' => 'Crossed',
    ]);
    $this->fail('Another account workspace must not be updated.');
} catch (HttpException $exception) {
    expect($exception->statusCode)->toBe(404);
}

$foreign = issueApiDatabase()
    ->query('SELECT name FROM workspaces WHERE id = 3')
    ->find();
expect($foreign['name'])->toBe('Private');
```

In `AuthenticationTest.php`, run migrations 001, 002, and 005. Insert an ownerless
legacy workspace before the first registration, then assert its `owner_id` equals the
new user ID afterward. This proves both migration timing paths.

Run all affected boundaries:

```bash
php vendor/bin/pest tests/Feature/AuthenticationTest.php \
  tests/Feature/IssueApiTest.php
npm run typecheck
npm run lint
npm test
npm run build
```

The PHP run should report twenty-one tests and 201 assertions. React should remain at
sixteen tests because the JSON shapes did not change. In the browser, two accounts
should see different workspace collections; copying another account's workspace,
project, or issue URL should return 404 and leave its data unchanged.

```bash
git add database/migrations/005_add_owner_to_workspaces_table.sql \
  app/Http/WorkspaceAccess.php app/Http/controllers \
  tests/Feature/AuthenticationTest.php tests/Feature/IssueApiTest.php
git commit -m "Authorize workspace ownership"
```

The application now enforces ownership. Next we will turn that rule into a compact
reciprocal matrix: each account must be allowed through its own full workflow and
refused from the other account's reads and mutations.
