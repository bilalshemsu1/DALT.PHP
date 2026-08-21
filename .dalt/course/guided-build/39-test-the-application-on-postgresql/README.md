Our browser is now backed by PostgreSQL, but the PHP feature tests still create
`:memory:` SQLite databases and execute selected old migrations. They can no longer
run the SQL production uses, and a green result would prove the wrong engine. We will
give application tests a guarded PostgreSQL database rebuilt from every real migration.
Framework-only tests remain fast and independent on SQLite.

## Name a database that tests are allowed to erase

Add this beside the active DALT connection in `.env` and `.env.example`:

```dotenv
TEST_DB_NAME=dalt_issue_tracker_test
```

The development database remains `dalt_issue_tracker`. The `_test` suffix will become
an enforced safety rule, not a naming suggestion.

Create `tests/Support/PostgresTestDatabase.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Support;

use Core\Database;
use Core\DatabaseManager;
use Core\Migration;
use Dotenv\Dotenv;
use RuntimeException;

final class PostgresTestDatabase
{
    public static function fresh(): Database
    {
        Dotenv::createImmutable(base_path(''))->safeLoad();

        $developmentName = (string) env(
            'DB_NAME',
            'dalt_issue_tracker',
        );
        $testName = (string) env(
            'TEST_DB_NAME',
            'dalt_issue_tracker_test',
        );
```

Refuse anything that is not a distinct, simple `_test` name:

```php
if (
    preg_match('/\A[a-z][a-z0-9_]*_test\z/', $testName) !== 1
    || $testName === $developmentName
) {
    throw new RuntimeException(
        'TEST_DB_NAME must be a distinct safe name ending in _test.',
    );
}
```

This helper will drop a schema. It must be impossible to aim it at the imported
development rows by changing one innocent-looking variable.

## Create the test database when needed

Build the shared connection values from the same environment as DALT:

```php
$shared = [
    'driver' => 'pgsql',
    'host' => (string) env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', 5432),
    'username' => (string) env('DB_USERNAME', 'dalt'),
    'password' => (string) env('DB_PASSWORD', ''),
    'charset' => (string) env('DB_CHARSET', 'utf8'),
];

$admin = DatabaseManager::create([
    ...$shared,
    'dbname' => 'postgres',
]);
$exists = $admin->query(
    'SELECT 1 FROM pg_database WHERE datname = :name',
    ['name' => $testName],
)->find();

if ($exists === false) {
    $admin->getConnection()->exec(
        'CREATE DATABASE "' . $testName . '"',
    );
}
```

The identifier cannot be bound like a normal value, which is why the strict regular
expression precedes its use. Our local Compose bootstrap user can create this isolated
development test database.

## Rebuild the schema from production migrations

Connect to the test database, clear only its public schema, and run the real migration
runner:

```php
$database = DatabaseManager::create([
    ...$shared,
    'dbname' => $testName,
]);
$database->getConnection()->exec(
    'DROP SCHEMA public CASCADE; CREATE SCHEMA public',
);

ob_start();
try {
    (new Migration($database))->runMigrations();
} finally {
    ob_end_clean();
}

return $database;
```

The output buffer keeps six migration progress reports from obscuring every test. A
migration failure still escapes and fails the test.

## Replace the three SQLite application fixtures

In each of these files, remove the `DatabaseManager` import, add the test helper, and
replace in-memory database creation plus the hand-picked migration loop:

```text
tests/Feature/AuthenticationTest.php
tests/Feature/IssueApiTest.php
tests/Feature/WorkspaceAuthorizationTest.php
```

The imports and setup now contain:

```php
use Tests\Support\PostgresTestDatabase;

$container = new Container();
$database = PostgresTestDatabase::fresh();
$container->instance(Database::class, $database);
$container->instance(
    Platform::class,
    Platform::discover(base_path()),
);
App::setContainer($container);
```

Remove each `foreach` that manually reads SQL files. The helper always runs the whole
migration history, including the constraints migration.

One transitional SQLite assertion must also leave
`AuthenticationTest.php`: it inserted an ownerless workspace immediately before first
registration. PostgreSQL now correctly makes that row impossible. The one-time
ownerless migration path was exercised before the constraint and by the importer;
current registration tests should prove account storage, hash verification, and
session identity in the schema that now exists.

## Keep the framework migration test independent

`tests/Feature/ArtisanMigrationTest.php` also forces SQLite. It is meant to test
DALT's migration command in a disposable project, but it still copies our
PostgreSQL-native users migration. Replace that copy with a private SQLite fixture:

```php
file_put_contents(
    $root . '/database/migrations/001_create_migration_probe.sql',
    "CREATE TABLE migration_probe "
    . "(id INTEGER PRIMARY KEY AUTOINCREMENT);\n",
);
```

Update the cleanup filename and expected progress line to
`001_create_migration_probe.sql`. The framework test now tests ordering and execution
without depending on the learner application's schema or database engine.

Apply the same principle in `.dalt/tests/Feature/ArtisanCommandTest.php`, whose relative
SQLite-path test has its own disposable root. Create a tiny
`001_create_path_probe.sql` there instead of copying the users migration, and update
its cleanup path. That test is about where a relative database file is created, not
about our application schema.

## Run the full current server boundary

Make sure the Compose database is healthy, then run:

```bash
php vendor/bin/pest \
  tests/Feature/AuthenticationTest.php \
  tests/Feature/IssueApiTest.php \
  tests/Feature/WorkspaceAuthorizationTest.php
```

The result is 26 passing tests and 147 assertions. Those tests cover registration,
login/logout, guest pages and APIs, CSRF, validation, every workspace/project/issue
mutation, nested ownership, and reciprocal account isolation on PostgreSQL.

Keep the independent React boundary green:

```bash
npm run typecheck
npm run lint
npm test -- --run
npm run build
```

React reports four passing files and sixteen tests.

## Repeat one assembled HTTP journey

Start DALT, log in with an imported account, and use the application normally:

```bash
php artisan serve
```

Confirm that the workspace list contains the imported rows. Create a temporary
workspace, refresh its deep URL, and delete it through the reviewed interface. Restart
only PostgreSQL:

```bash
docker compose restart db
```

After it returns to `healthy`, refresh the signed-in workspace list. Imported data is
still present and the temporary workspace remains deleted. This proves the browser,
session, DALT controllers, and PostgreSQL connection together; component tests and
direct SQL cannot replace that evidence.

```bash
git add .env.example \
  tests/Feature/ArtisanMigrationTest.php \
  tests/Support/PostgresTestDatabase.php \
  tests/Feature/AuthenticationTest.php \
  tests/Feature/IssueApiTest.php \
  tests/Feature/WorkspaceAuthorizationTest.php
git commit -m "Test the application on PostgreSQL"
```

Every current feature is now proven on the database we actually run. The last lesson
in this batch will make the repeated multi-statement transaction boundary explicit and
prove rollback with deliberately failing business operations.
