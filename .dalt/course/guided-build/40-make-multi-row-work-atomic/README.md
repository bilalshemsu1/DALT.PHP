PostgreSQL now protects each statement, but several product actions mean more than one
statement: registration may claim legacy workspaces, reviewed deletion removes a
hierarchy, and the importer copies four tables. We repeated the same transaction
lifecycle in four places. We will centralize that lifecycle while leaving each piece
of business SQL visible where it belongs.

## Broaden the application namespace

Our Composer autoload currently maps only `App\Http`. We now have an application
support class that is not HTTP-specific, so change `composer.json`:

```json
"autoload": {
    "psr-4": {
        "Core\\": "framework/Core/",
        "App\\": "app/"
    }
}
```

Refresh Composer's class map:

```bash
composer dump-autoload
```

`App\Http` still maps naturally to `app/Http`; the broader prefix also allows
`App\Support` to map to `app/Support`.

## Extract only begin, commit, and rollback

Create `app/Support/Transaction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

use Core\Database;
use LogicException;
use Throwable;

final class Transaction
{
    /**
     * @template TResult
     * @param callable(): TResult $operation
     * @return TResult
     */
    public static function run(
        Database $database,
        callable $operation,
    ): mixed {
        $connection = $database->getConnection();

        if ($connection->inTransaction()) {
            throw new LogicException(
                'This operation already has an active transaction.',
            );
        }

        $connection->beginTransaction();
```

The explicit nested-transaction refusal prevents an inner helper from committing or
rolling back an outer business operation. We do not need savepoints for the current
application.

Complete the lifecycle:

```php
try {
    $result = $operation();
    $connection->commit();

    return $result;
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    throw $exception;
}
```

The original exception continues outward after rollback. Controllers keep their
normal error handling, and the importer can keep its safe command-line message.

## Refactor registration without hiding its SQL

Add the import in `app/Http/controllers/api/auth/register.php`:

```php
use App\Support\Transaction;
```

Replace the manual transaction scaffold with a closure that returns the inserted ID:

```php
$userId = Transaction::run($database, function () use (
    $database,
    $name,
    $email,
    $password,
): int {
    $database->query(
        'INSERT INTO users (name, email, password)
         VALUES (:name, :email, :password)',
        [
            'name' => $name,
            'email' => $email,
            'password' => password_hash(
                $password,
                PASSWORD_DEFAULT,
            ),
        ],
    );
    $userId = (int) $database
        ->getConnection()
        ->lastInsertId();
```

Keep the existing account count and ownerless-workspace update, then return rather
than commit locally:

```php
if ((int) ($userCount['aggregate'] ?? 0) === 1) {
    $database->query(
        'UPDATE workspaces
         SET owner_id = :owner_id
         WHERE owner_id IS NULL',
        ['owner_id' => $userId],
    );
}

return $userId;
});
```

PostgreSQL's current schema cannot contain ownerless workspaces; this update now
normally touches zero rows. Keeping it costs nothing and preserves compatibility with
the transitional registration path until memberships replace sole ownership.

## Refactor both reviewed deletions

In `api/workspaces/destroy.php`, import `Transaction` and wrap the three existing
deletes:

```php
Transaction::run(
    $database,
    function () use ($database, $workspace): void {
        $database->query(
            'DELETE FROM issues
             WHERE project_id IN (
                 SELECT id FROM projects
                 WHERE workspace_id = :workspace_id
             )',
            ['workspace_id' => $workspace['id']],
        );
        $database->query(
            'DELETE FROM projects
             WHERE workspace_id = :workspace_id',
            ['workspace_id' => $workspace['id']],
        );
        $database->query(
            'DELETE FROM workspaces WHERE id = :id',
            ['id' => $workspace['id']],
        );
    },
);
```

Use the same shape around issue deletion followed by project deletion in
`api/projects/destroy.php`. We retain the explicit child deletes because they teach
the business operation and make its intention visible; PostgreSQL's cascades remain a
final safety net.

In `scripts/import-sqlite-to-postgresql.php`, replace the manual begin/commit/rollback
with:

```php
try {
    Transaction::run($target, function () use (
        $source,
        $target,
        $tables,
        &$currentTable,
        &$currentId,
        &$sourceCounts,
    ): void {
        // Keep the existing table copy, count checks,
        // and sequence repair here.
    });
} catch (Throwable) {
    fwrite(
        STDERR,
        "Import failed while copying {$currentTable} row {$currentId}. "
        . "PostgreSQL rolled back every imported row.\n",
    );
    exit(1);
}
```

The abstraction owns transaction mechanics; the importer still owns what to copy and
what message is safe to display.

## Prove success and late failure

Create `tests/Feature/TransactionTest.php`. First use
`PostgresTestDatabase::fresh()`, insert a user and workspace inside
`Transaction::run`, return the workspace ID, and assert both rows exist after the
closure returns.

The stronger test starts with one complete hierarchy and captures the durable state:

```php
$before = [
    'projects' => $database
        ->query('SELECT id, name FROM projects ORDER BY id')
        ->get(),
    'issues' => $database
        ->query('SELECT id, title FROM issues ORDER BY id')
        ->get(),
];
```

Delete the issue and project, then deliberately violate the project-name check as the
last statement:

```php
try {
    Transaction::run($database, function () use ($database): void {
        $database->query(
            'DELETE FROM issues WHERE project_id = 1',
        );
        $database->query(
            'DELETE FROM projects WHERE id = 1',
        );
        $database->query(
            "INSERT INTO projects (workspace_id, name)
             VALUES (1, 'x')",
        );
    });
    test()->fail(
        'The invalid project name should fail the transaction.',
    );
} catch (PDOException) {
    // The check is the deliberate second-step failure.
}
```

Load the same snapshot as `$after` and require exact equality plus
`inTransaction() === false`. A fake helper that performs only the first statements,
swallows the exception, or forgets rollback cannot pass this test.

Run the batch's application proof:

```bash
php vendor/bin/pest \
  tests/Feature/AuthenticationTest.php \
  tests/Feature/IssueApiTest.php \
  tests/Feature/WorkspaceAuthorizationTest.php \
  tests/Feature/TransactionTest.php
```

It reports 28 passing tests and 152 assertions. Also rerun typecheck, lint, sixteen
React tests, and the production build.

```bash
git add composer.json app/Support/Transaction.php \
  app/Http/controllers/api/auth/register.php \
  app/Http/controllers/api/workspaces/destroy.php \
  app/Http/controllers/api/projects/destroy.php \
  scripts/import-sqlite-to-postgresql.php \
  tests/Feature/TransactionTest.php
git commit -m "Centralize database transactions"
```

The PostgreSQL batch is complete: a persistent service, environment-driven DALT
connection, native schema, database invariants, preserved SQLite data, real-engine
feature tests, and proven atomic business operations. Next, session identity can grow
into workspace memberships without changing database engines underneath us.
