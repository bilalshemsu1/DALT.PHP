# Run migrations as a deployment step

Our schema currently arrives because somebody remembers to run `php artisan migrate`.
That works exactly until the release where nobody does, and then the application starts
against a database missing the column it was written for. We will make the schema part
of deploying: one job, before any traffic, that is safe to repeat, cannot run twice at
once, and stops the release when it fails.

> **Helpful background:** PostgreSQL's [advisory locks](https://www.postgresql.org/docs/18/explicit-locking.html#ADVISORY-LOCKS)
> are the mechanism that keeps two migrators from racing.

## Where the migration must not live

Two placements look reasonable and are both wrong.

Running migrations **inside the web container's startup** means every replica runs them.
With one replica that is merely redundant; with three it is a race, and the failure mode
is two processes applying the same `ALTER TABLE` at the same time.

Running them **by hand after the deployment** means there is a window — sometimes
seconds, sometimes an afternoon — where new code is serving requests against an old
schema.

The correct shape is a one-shot job, using the same image, that finishes before the web
service starts.

## The migration script

`scripts/deploy-migrate.php` starts by refusing to run into a broken environment:

```php
// The same guard the application boots with. A release should stop here rather than
// migrate a database the application will then refuse to talk to.
Configuration::guard($_ENV);
```

Then the lock:

```php
// One arbitrary but fixed key. Two migrators started by two replicas will contend for
// it, and the second waits rather than applying the same file concurrently.
const MIGRATION_LOCK_KEY = 8_150_423;

if ($driver === 'pgsql') {
    // A session-level advisory lock: held until this process disconnects, so a crashed
    // migrator cannot leave the lock behind.
    $database->query('SELECT pg_advisory_lock(:key)', ['key' => MIGRATION_LOCK_KEY]);
    $locked = true;
    echo "Acquired the migration lock.\n";
}
```

An advisory lock is the right tool here because it locks *nothing in particular*. There
is no row or table to lock — the thing we are protecting is the act of migrating. And
because it is session-level, it disappears when the connection does. A migrator killed
mid-flight releases it automatically, which a lock row in a table would not.

Then the run itself, with the failure path stated plainly:

```php
try {
    $applied = (new Migration($database))->runMigrations();
} catch (Throwable $exception) {
    fwrite(STDERR, "Migration failed: {$exception->getMessage()}\n");
    fwrite(STDERR, "The release must not continue; the schema is not what the application expects.\n");
    exit(1);
} finally {
    if ($locked) {
        $database->query('SELECT pg_advisory_unlock(:key)', ['key' => MIGRATION_LOCK_KEY]);
    }
}
```

The exit code is the whole interface. Everything downstream — Compose, a pipeline, an
orchestrator — decides what to do next by reading it.

Idempotency we already have: `Migration::runMigrations()` skips anything recorded in the
`migrations` table, which is why re-running is a no-op rather than an error.

## A one-shot service, and a gate

In `compose.production.yaml`:

```yaml
  # One-shot: the same image and the same environment as the web service, run to
  # completion before any request is served. Never a step inside the web container's
  # startup, which would run it once per replica.
  migrate:
    build:
      context: .
      target: runtime
    command: ["php", "scripts/deploy-migrate.php"]
    restart: "no"
    environment:
      # …the same DB_* and APP_* values the app service receives…
    depends_on:
      db:
        condition: service_healthy
```

`restart: "no"` matters. A job that exits is not a crash, and a restart policy would put
it in a loop.

Then the web service waits for it to *succeed*:

```yaml
    depends_on:
      db:
        condition: service_healthy
      # A failed migration stops the release here, instead of starting an application
      # against a schema that is not there.
      migrate:
        condition: service_completed_successfully
```

`service_completed_successfully` is different from `service_started`: it requires exit
code 0. That single condition is what turns a failed migration into a failed release.

One thing that caught us out immediately:

```text
migrate-1  | Could not open input file: scripts/deploy-migrate.php
```

The runtime image copies named directories, and `scripts/` was not one of them. The
migrator runs *from the image*, so its script has to be in it:

```dockerfile
# The deployment migrator runs from this image, so its script has to be in it.
COPY --chown=app:app scripts/deploy-migrate.php ./scripts/deploy-migrate.php
```

One file, not the whole directory — the backup and check scripts are operator tools and
have no business in a production image.

## Prove the four cases

**An empty deployment** applies everything and the application starts:

```bash
DB_PASSWORD=… docker compose --env-file .env.production.example \
  -f compose.production.yaml -p daltprod up -d --build --wait
```

```text
Container daltprod-migrate-1  Exited
Container daltprod-app-1      Healthy
```

```text
migrate-1  | Running migration: 013_create_issue_activity.sql
migrate-1  | ✓ Success
migrate-1  | Ran 13 migrations.
migrate-1  | Applied 13 migration(s).
```

```text
{"status":"ready"} [200]
```

**A redeploy** changes nothing:

```text
migrate-1  | Acquired the migration lock.
migrate-1  | No migrations to run.
migrate-1  | Schema already current; nothing to apply.
```

**A failed migration blocks the release.** Add one that cannot work:

```sql
-- database/migrations/014_broken_release.sql
ALTER TABLE issues ADD CONSTRAINT issues_impossible_check
  CHECK (this_column_does_not_exist > 0);
```

```text
migrate-1  | Error: SQLSTATE[42703]: Undefined column: 7 ERROR:  column "this_column_does_not_exist" does not exist
migrate-1  | The release must not continue; the schema is not what the application expects.
service "migrate" didn't complete successfully: exit 1
```

And the important part — what the application did:

```text
app      Created
db       Up 43 seconds (healthy)
migrate  Exited (1) Less than a second ago
```

`Created`, not `Up`. The new application container was never started. Delete the broken
migration and the release completes normally.

## Back up before you migrate

A named volume is not a backup. It holds one copy of the database's current state, and
a migration that damages data damages that copy too. `scripts/backup-database.sh` writes
a restorable file that predates the change:

```sh
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" -p "$PROJECT" \
  exec -T db pg_dump -U "${DB_USERNAME:-issues_app}" -d "${DB_NAME:-issues}" > "$OUT"
```

Prove it restores, rather than assuming:

```bash
./scripts/backup-database.sh
docker compose … exec -T db psql … -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"
docker compose … exec -T db psql -q … < backups/issues-20260823T131044Z.sql
```

```text
before: 1 users
after damage: schema dropped
after restore: 1 users, backup@example.test
```

That is a backup. A file nobody has restored is a hope.

Add the output directory to `.gitignore` — a dump contains every row in the database:

```text
# Database backups are local artifacts, never committed
/backups
```

## Keep demonstration data out of the deployment

A migration runs on every environment, production included. Demonstration content must
not. That is a difference of intent, so it gets a different directory and a different
command: `database/seeds/demo.php`, never invoked by `deploy-migrate.php`.

It defends itself as well:

```php
if (($_ENV['APP_ENV'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to seed demonstration data into production.\n");
    exit(1);
}
```

And it is safe to run twice, because a seed that duplicates its own rows is a seed
nobody runs:

```bash
php database/seeds/demo.php
php database/seeds/demo.php
APP_ENV=production php database/seeds/demo.php
```

```text
Seeded demo workspace 2 for demo@example.test.
Demo data is already present.
Refusing to seed demonstration data into production.
```

## Run the gate

```bash
DB_PASSWORD=… docker compose --env-file .env.production.example \
  -f compose.production.yaml -p daltprod up -d --build --wait
curl -w ' [%{http_code}]\n' http://127.0.0.1:8200/ready
php vendor/bin/pest tests/Feature/HealthTest.php tests/Unit/ConfigurationTest.php
composer check:config && composer check:secrets
```

The stack deploys, the migrator applies or reports nothing to do, readiness answers
200, the health and configuration tests pass, and both checks are clean.

Our schema now arrives with the release, refuses to run twice, and stops the deployment
rather than letting the application meet a database it does not recognise. The last
lesson of this batch makes a pipeline run the same checks we have been running by hand.
