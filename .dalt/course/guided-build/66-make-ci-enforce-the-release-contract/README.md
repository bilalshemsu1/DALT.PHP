# Make CI enforce the release contract

We now have a lot of checks: PHP tests against PostgreSQL, two type checks, lint,
component tests, a verified production build, configuration and secret scans, and
browser journeys. Running them is currently a matter of remembering. We will put them
in one script, have a pipeline run that same script, and make a green pipeline mean
exactly what a green laptop means.

> **Helpful background:** GitHub's [service containers documentation](https://docs.github.com/en/actions/using-containerized-services/about-service-containers)
> covers the PostgreSQL service this workflow depends on.

## One script, two callers

The temptation is to list every command in the workflow file. Do not: the moment CI
and local development have separate definitions of "passing", a failure that only
happens in CI cannot be reproduced without pushing.

`scripts/ci-gate.sh` is the single definition:

```sh
step 'Composer manifest'
composer validate --strict

step 'PHP application tests (PostgreSQL)'
DALT_NESTED_TEST_RUN=1 vendor/bin/pest tests

step 'TypeScript — application'
npm run typecheck

step 'TypeScript — browser tests'
npm run typecheck:browser

step 'Lint'
npm run lint

step 'React component tests'
npm run test

step 'Production build'
npm run build
php scripts/verify-build.php

step 'Configuration and secrets'
php scripts/check-configuration.php
php scripts/scan-for-secrets.php

step 'Browser journeys'
npm run test:browser
```

`set -eu` at the top means the first failure stops everything, and the order is
deliberate: the cheap checks run before the ones that need a browser.

That `DALT_NESTED_TEST_RUN=1` deserves an explanation, because it looks like sweeping
something under a rug. DALT ships a learning platform under `.dalt/`, and
`tests/Feature/DaltSuiteTest.php` shells out to run *that* platform's suite — five
minutes of tests belonging to the course we are learning from, not to the issue tracker
we are building. The flag is the mechanism that test already provides for a nested run.
Our release contract is about our application.

Run the whole thing:

```bash
./scripts/ci-gate.sh
```

```text
▸ Composer manifest
▸ PHP application tests (PostgreSQL)
  Tests:  1 skipped, 336 passed (1017 assertions)
▸ TypeScript — application
▸ TypeScript — browser tests
▸ Lint
▸ React component tests
▸ Production build
▸ Configuration and secrets
▸ Browser journeys

All release checks passed.
```

## The pipeline

`.github/workflows/ci.yml` supplies an environment and calls the script.

The database is a service container, pinned by digest exactly as our Compose files are:

```yaml
    services:
      postgres:
        image: postgres@sha256:9a8afca54e7861fd90fab5fdf4c42477a6b1cb7d293595148e674e0a3181de15
        env:
          POSTGRES_DB: dalt_issue_tracker
          POSTGRES_USER: dalt
          # A throwaway credential scoped to this run. It unlocks a database that
          # exists for four minutes and is never reachable from outside the runner.
          POSTGRES_PASSWORD: ci-only-not-a-secret
        ports:
          - 55432:5432
        options: >-
          --health-cmd "pg_isready -U dalt -d dalt_issue_tracker"
          --health-interval 2s
          --health-retries 20
```

The health options are the same idea as Lesson 64: a started container is not a ready
database, and a job that begins querying too early fails for the wrong reason.

That password is written in plain text on purpose, and it is worth being precise about
why that is acceptable here and nowhere else. It unlocks a database that is created
when the job starts, listens only on the runner's loopback, and is destroyed minutes
later. It is not a secret; it is a fixture. A real credential in this file would be a
leak, which is what the scan below is for.

Runtime versions are pinned rather than floating:

```yaml
      - uses: shivammathur/setup-php@v2
        with:
          # Pinned: "latest" would move the runtime under us between releases.
          php-version: "8.4"
          extensions: pdo, pdo_pgsql

      - uses: actions/setup-node@v4
        with:
          # The same Node that builds our production image writes our lockfile.
          node-version: "22.21.1"
          cache: npm
```

Node 22.21.1 is not an arbitrary choice — it is the version in our Dockerfile's
frontend stage, and Lesson 63 showed what happens when the npm that writes the lockfile
and the npm that reads it disagree.

## Cache downloads, never generated truth

```yaml
      # Composer's own cache directory holds downloaded packages. vendor/ itself is
      # generated application truth and is deliberately not cached — a stale vendor
      # tree is a green pipeline testing yesterday's dependencies.
      - name: Cache Composer downloads
        uses: actions/cache@v4
        with:
          path: ~/.cache/composer/files
          key: composer-${{ hashFiles('composer.lock') }}
```

The distinction is the whole rule. `~/.cache/composer/files` holds immutable downloaded
archives — caching them is free correctness. `vendor/` and `node_modules/` are *derived*
from a lockfile, and a cache of them can be subtly wrong in a way that makes CI pass
while a fresh checkout fails. `npm ci` and `composer install` are fast enough; being
right is worth more.

## Traces only when something failed

```yaml
      # Only on failure: a trace of a passing run is noise, and traces can contain
      # page contents.
      - name: Upload browser traces
        if: failure()
        uses: actions/upload-artifact@v4
        with:
          name: playwright-traces
          path: test-results/
          retention-days: 7
```

`if: failure()` and a retention limit. A Playwright trace replays the whole session,
including whatever was on screen — for us that is fixture data, but the habit of not
storing more than needed is the right one.

Lesson 60 already configured `trace: 'retain-on-failure'`, so `test-results/` is empty
on a green run and this step uploads nothing.

## Close the gap CI opens

A pipeline makes one particular mistake easy: a database URL pasted into a job
definition looks like configuration and gets committed like code. Our secret scanner
from Lesson 61 did not look at source files at all, so extend it:

```php
// 3. A credential pasted into tracked source — a workflow file, a controller, a
// config. This is the one that a CI pipeline makes easy: a database URL copied into a
// job definition looks like configuration and is committed like code.
$textExtensions = ['php', 'yml', 'yaml', 'js', 'mjs', 'ts', 'tsx', 'json', 'sh', 'sql', 'ini'];
```

Two details make the difference between a check that works and one that looks like it
works.

First, what counts as a file worth scanning:

```php
// --others --exclude-standard includes files that are not committed yet but are not
// ignored either. A secret is just as exposed the moment it is staged, and catching it
// before the commit is the entire point.
exec('git -C ' . escapeshellarg($root) . ' ls-files --cached --others --exclude-standard', $tracked);
```

Our first version listed only committed files, which meant a brand new workflow file
containing a real password was invisible to the check — exactly when we most wanted to
be told.

Second, the shape of the value:

```php
// A long random-looking assignment, which is what a leaked key looks like. The
// quotes are optional because YAML rarely uses them, which is exactly where a
// pasted database URL ends up.
'/(secret|token|password|api[_-]?key)\s*[:=]\s*[\'"]?[A-Za-z0-9\/+_-]{24,}[\'"]?/i',
```

The first version required quotes. YAML does not use them, so the one file format the
new rule existed for was the one it could not match.

Prove it now. Replace the fixture password in the workflow with something that looks
like a real credential:

```bash
sed -i 's/ci-only-not-a-secret/Xk9mQ2pLd7vRt4wYbN8hFz3aJc6eSgU1/' .github/workflows/ci.yml
php scripts/scan-for-secrets.php
```

```text
Possible secret exposure:
- .github/workflows/ci.yml contains something secret-shaped; move it to the deployment environment.
```

Put the fixture value back and the scan is quiet. Because the scan runs *inside*
`ci-gate.sh`, the pipeline refuses its own commit.

## What this proves, and what it does not

Be honest about the boundary. Everything above — the gate script, all ten steps, the
scanner's new rule and both of the bugs found while writing it — was run locally and is
reported from real output.

The workflow file itself has not been executed here; there is no GitHub in this
environment. What we can say is that it parses, that its steps invoke the same script we
just ran, and that every version it pins matches what our Dockerfile and lockfile
already use. The first push is the first real run, and the point of putting everything
in `ci-gate.sh` is that a failure there is reproducible with one command on a laptop.

## Batch 11 is complete

```bash
./scripts/ci-gate.sh
```

```text
All release checks passed.
```

Our application can now be configured for production and refuses to start without it,
builds its frontend once behind a full gate, ships as a container carrying nothing that
made it, tells an orchestrator the difference between "restart me" and "wait", brings
its schema with the release, and has one command that decides whether any of it is
allowed to ship.

The next batch turns to running it: logs worth reading, failures that stay contained,
an accessibility audit, measured performance, and a rehearsed release.
