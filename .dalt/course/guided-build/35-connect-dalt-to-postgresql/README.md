PostgreSQL is healthy, but our application still reads SQLite because DALT's database
configuration says `DB_DRIVER=sqlite`. We will switch that configuration without
hard-coding a host or password in PHP, then ask the actual PDO connection what it
opened.

## Declare the PHP driver our project requires

The `PDO` API is shared, but each database engine needs its own PHP extension. Add the
PostgreSQL requirements inside `require` in `composer.json`:

```json
"require": {
    "ext-pdo": "*",
    "ext-pdo_pgsql": "*",
    "php": "^8.2",
    "vlucas/phpdotenv": "^5.6"
}
```

Update the lock file and check this computer:

```bash
composer update --lock
composer check-platform-reqs
```

The output should report both `ext-pdo` and `ext-pdo_pgsql` as successful. If
`ext-pdo_pgsql` is missing, install the PostgreSQL PDO extension for the PHP version
shown by `php --version`, then rerun the check. Composer now stops a setup that could
never connect instead of letting the first web request discover the missing driver.

## Give DALT the PostgreSQL connection values

Replace the active SQLite block in `.env` with:

```dotenv
# DALT database connection (PHP still runs on the host)
DB_DRIVER=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=dalt_issue_tracker
DB_USERNAME=dalt
DB_PASSWORD=dalt_local_password
DB_CHARSET=utf8

# SQLite rollback while we port the schema
# DB_DRIVER=sqlite
# DB_DATABASE=database/app.sqlite
```

Use the same values in `.env.example`. If Lesson 34 required a different host port,
put that port in both `POSTGRES_PORT` and `DB_PORT` in the ignored `.env`.

The two port variables serve different programs:

```text
POSTGRES_PORT  tells Compose which host port to publish
DB_PORT        tells the host-run PHP process where to connect
```

They must agree today. Later, when PHP also runs in Compose, `DB_HOST` becomes the
service name `db` and `DB_PORT` becomes the container port 5432. We are not making
that change early.

DALT already maps these environment values in `config/database.php`:

```php
'driver' => env('DB_DRIVER', 'sqlite'),
'host' => env('DB_HOST', '127.0.0.1'),
'port' => (int) env('DB_PORT', 5432),
'dbname' => env('DB_NAME', 'dalt_php_app'),
'username' => env('DB_USERNAME', 'postgres'),
'password' => env('DB_PASSWORD', ''),
```

Configuration describes a connection. It does not prove that PDO opened it, so we
will add one truthful diagnostic.

## Ask PDO which server it opened

Create `scripts/database-status.php`:

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

use Core\App;
use Core\Database;

const BASE_PATH = __DIR__ . '/../';

require BASE_PATH . 'vendor/autoload.php';
require BASE_PATH . 'framework/Core/functions.php';
require base_path('framework/Core/bootstrap.php');
```

This boots the same environment, config, container, and lazy database binding used by
the web application. Resolve the connection with a small failure boundary:

```php
$database = null;

try {
    $database = App::resolve(Database::class);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

$connection = $database->getConnection();
$driver = (string) $connection->getAttribute(
    \PDO::ATTR_DRIVER_NAME,
);
```

Require the result we are trying to prove:

```php
if ($driver !== 'pgsql') {
    fwrite(
        STDERR,
        "Expected PostgreSQL, connected with {$driver}.\n",
    );
    exit(1);
}
```

Finally, ask PostgreSQL—not our configuration—for its identity:

```php
$server = $database->query(
    "SELECT current_database() AS database,
            current_user AS username,
            current_setting('server_version') AS version",
)->findOrFail();

echo "Driver: {$driver}\n";
echo "Database: {$server['database']}\n";
echo "User: {$server['username']}\n";
echo "PostgreSQL: {$server['version']}\n";
```

Make the script executable and run it:

```bash
chmod +x scripts/database-status.php
php scripts/database-status.php
```

The version may be newer than this example, but the first three lines should identify
the real target:

```text
Driver: pgsql
Database: dalt_issue_tracker
User: dalt
PostgreSQL: 18.x
```

## Prove the diagnostic can fail

A check that always prints success would be decoration. Temporarily override the
password for one command:

```bash
DB_PASSWORD=definitely-wrong php scripts/database-status.php
```

It exits non-zero with `Database connection failed for pgsql.` Restore no file—the
override applied only to that process—and rerun the normal command successfully.

We also kept a deliberate rollback path. This command points at the old SQLite file
without editing `.env`:

```bash
DB_DRIVER=sqlite DB_DATABASE=database/app.sqlite \
  php scripts/database-status.php
```

It refuses with `Expected PostgreSQL, connected with sqlite.` That is useful evidence:
SQLite still exists for the upcoming import, while our active application connection
is PostgreSQL.

```bash
git add .env.example composer.json composer.lock \
  scripts/database-status.php
git commit -m "Connect DALT to PostgreSQL"
```

The server is reachable and DALT is using it. Its database still contains only our
Lesson 34 probe, so next we will replace that disposable volume and create the real
issue-tracker schema with explicit PostgreSQL SQL.
