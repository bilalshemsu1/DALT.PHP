The PostgreSQL schema is empty and guarded. Our old `database/app.sqlite` still holds
the accounts and issue-tracker work we created while the application grew. Instead of
discarding it, we will build a one-time importer that preserves IDs and hashes, checks
every row against the new schema, and commits only when the whole copy is correct.

## Open two database connections deliberately

Create `scripts/import-sqlite-to-postgresql.php` and boot the normal DALT application:

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

use Core\App;
use Core\Database;
use Core\DatabaseManager;

const BASE_PATH = __DIR__ . '/../';

require BASE_PATH . 'vendor/autoload.php';
require BASE_PATH . 'framework/Core/functions.php';
require base_path('framework/Core/bootstrap.php');
```

Accept an optional source path, then create SQLite separately from DALT's active
PostgreSQL binding:

```php
$sourceArgument = $argv[1] ?? 'database/app.sqlite';
$sourcePath = str_starts_with($sourceArgument, '/')
    ? $sourceArgument
    : base_path($sourceArgument);

if (!is_file($sourcePath)) {
    fwrite(STDERR, "SQLite source not found: {$sourcePath}\n");
    exit(1);
}

$source = DatabaseManager::create([
    'driver' => 'sqlite',
    'database' => $sourcePath,
]);
$target = App::resolve(Database::class);
```

Require the target to be PostgreSQL. This prevents a mistaken `.env` from importing
SQLite back into itself:

```php
if ($target->getConnection()->getAttribute(
    \PDO::ATTR_DRIVER_NAME,
) !== 'pgsql') {
    fwrite(STDERR, "The target connection must use PostgreSQL.\n");
    exit(1);
}
```

## State the copy order and exact columns

Add the tables in dependency order:

```php
$tables = [
    'users' => [
        'id', 'name', 'email', 'password',
        'created_at', 'updated_at',
    ],
    'workspaces' => ['id', 'name', 'created_at', 'owner_id'],
    'projects' => ['id', 'workspace_id', 'name', 'created_at'],
    'issues' => [
        'id', 'project_id', 'title', 'description',
        'status', 'created_at',
    ],
];
```

Users precede their workspaces, workspaces precede projects, and projects precede
issues. The foreign keys make any other order fail. We copy password hashes as opaque
values; plaintext passwords are neither available nor needed.

## Refuse an unsafe target or source

Count the application rows already in PostgreSQL:

```php
$targetRows = 0;
foreach (array_keys($tables) as $table) {
    $count = $target
        ->query("SELECT COUNT(*) AS aggregate FROM {$table}")
        ->findOrFail();
    $targetRows += (int) $count['aggregate'];
}

if ($targetRows !== 0) {
    fwrite(STDERR, "PostgreSQL is not empty. No rows were imported.\n");
    exit(1);
}
```

Merging two independently numbered development databases would need a different tool.
This importer is intentionally safer: it works only once against an empty target.

The PostgreSQL schema also requires every workspace to have an owner. Diagnose legacy
SQLite data before starting the transaction:

```php
$ownerless = $source->query(
    'SELECT COUNT(*) AS aggregate
     FROM workspaces WHERE owner_id IS NULL',
)->findOrFail();

if ((int) $ownerless['aggregate'] !== 0) {
    fwrite(
        STDERR,
        "SQLite contains ownerless workspaces. "
        . "Register the first account there before importing.\n",
    );
    exit(1);
}
```

Do not invent an owner. If this message appears, temporarily point DALT back to SQLite,
register the first real account so Lesson 32 claims the legacy rows, then restore the
PostgreSQL environment values.

## Copy everything in one transaction

Begin a transaction and build parameterized inserts only from our fixed table/column
map:

```php
$connection = $target->getConnection();
$currentTable = 'preflight';
$currentId = 0;
$sourceCounts = [];

try {
    $connection->beginTransaction();

    foreach ($tables as $table => $columns) {
        $currentTable = $table;
        $rows = $source
            ->query("SELECT * FROM {$table} ORDER BY id")
            ->get();
        $sourceCounts[$table] = count($rows);
        $columnList = implode(', ', $columns);
        $placeholderList = implode(', ', array_map(
            static fn (string $column): string => ":{$column}",
            $columns,
        ));
```

Inside that table loop, copy each selected value without logging it:

```php
foreach ($rows as $row) {
    $currentId = (int) $row['id'];
    $parameters = [];

    foreach ($columns as $column) {
        $parameters[$column] = $row[$column];
    }

    $target->query(
        "INSERT INTO {$table} ({$columnList})
         VALUES ({$placeholderList})",
        $parameters,
    );
}
```

After all inserts, query each target count and compare it with `$sourceCounts`. Throw
if any count differs. Then move each identity sequence beyond the imported maximum:

```php
foreach (array_keys($tables) as $table) {
    $target->query(
        "SELECT setval(
            pg_get_serial_sequence('{$table}', 'id'),
            COALESCE((SELECT MAX(id) FROM {$table}), 0) + 1,
            false
        )",
    );
}

$connection->commit();
```

Explicit IDs do not automatically advance identity sequences. Without `setval`, the
next normal insert could try ID 1 again.

Close the `try` with a rollback that identifies location but never prints row values:

```php
} catch (Throwable) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    fwrite(
        STDERR,
        "Import failed while copying {$currentTable} row {$currentId}. "
        . "PostgreSQL rolled back every imported row.\n",
    );
    exit(1);
}
```

Finally, print each verified count and `SQLite data is now in PostgreSQL.`

## Prove failure rolls back before importing for real

Make the script executable and create a disposable broken copy outside the project:

```bash
chmod +x scripts/import-sqlite-to-postgresql.php
cp database/app.sqlite /tmp/dalt-invalid-import.sqlite
sqlite3 /tmp/dalt-invalid-import.sqlite \
  "UPDATE issues SET status = 'blocked' WHERE id = 1;"
```

Run the importer against that copy:

```bash
php scripts/import-sqlite-to-postgresql.php \
  /tmp/dalt-invalid-import.sqlite
```

It exits non-zero at the issues row and says PostgreSQL rolled back every imported
row. Confirm the target tables still contain zero rows, then run the real import:

```bash
php scripts/import-sqlite-to-postgresql.php
```

The output lists the source count for users, workspaces, projects, and issues, followed
by the success message. Running it a second time must refuse with
`PostgreSQL is not empty. No rows were imported.`

If the old practice data is not worth preserving, the explicit alternative is:

```bash
docker compose down --volumes
docker compose up -d --wait db
php artisan migrate
```

That is a destructive development reset, not an import.

```bash
git add scripts/import-sqlite-to-postgresql.php
git commit -m "Import SQLite data into PostgreSQL"
```

Our real rows, IDs, ownership, timestamps, and password hashes now live behind the
PostgreSQL constraints. Next we will stop using in-memory SQLite in application tests
and prove every current user journey against a dedicated PostgreSQL test database.
