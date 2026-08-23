# Rehearse a complete release and recovery

This is the last lesson, and it adds no features. We are going to take the application
from a clean checkout to a serving deployment, put real data in it, destroy it
completely, and bring the data back — with every step run rather than described. A
backup nobody has restored is a hope, and a release path nobody has walked is a guess.

> **Helpful background:** PostgreSQL's [backup and restore documentation](https://www.postgresql.org/docs/18/backup-dump.html)
> explains why a logical dump is a backup and a volume is not.

## One command, five steps

`scripts/release.sh` collects commands from earlier lessons so there is one answer to
"how do we ship this":

```sh
step '1. Check the release contract'
# DB_PASSWORD is the *deployment's* credential. Our Dotenv load is immutable, so
# leaving it in the environment would override .env and point the local test run at a
# database that does not exist. The gate runs without it.
env -u DB_PASSWORD ./scripts/ci-gate.sh > /tmp/release-gate.log 2>&1 || {
  echo 'The release gate failed. Nothing was deployed.'
  tail -20 /tmp/release-gate.log
  exit 1
}
```

That `env -u DB_PASSWORD` was not planned. The first run of this script failed with
**80 test failures** and `Database connection failed for pgsql` — because the shell
that supplies the deployment's password to Compose was also supplying it to the local
test suite, and our immutable Dotenv lets an exported variable win over `.env`. The trap
Lesson 63 warned about, arriving in the one place it does the most damage.

The rest of the script is ordering:

```sh
step '2. Back up the current database, if there is one'
if compose ps --status running --services 2>/dev/null | grep -q '^db$'; then
  ./scripts/backup-database.sh
else
  echo 'No running database; this is a first deployment.'
fi

step '3. Build the images and start the stack'
# The migrate service runs to completion before app starts; --wait blocks until every
# service reports healthy.
compose up -d --build --wait

step '4. Confirm readiness through the production entrypoint'
compose exec -T app php -r "exit(@file_get_contents('http://127.0.0.1:8000/ready') === false ? 1 : 0);"
curl -fsS "$APP_URL_LOCAL/ready" > /dev/null

step '5. Smoke test the deployed application'
```

Readiness is checked twice on purpose: from inside the container, which is what the
orchestrator's health check sees, and from outside, which is what a user sees. Those can
disagree, and knowing which one failed is the difference between a networking problem
and an application problem.

## The lockfile trap, again

The first real deployment attempt died in the frontend stage:

```text
npm error `npm ci` can only install packages when your package.json and
npm error package-lock.json are in sync.
npm error Missing: @emnapi/core@1.11.3 from lock file
```

Exactly the Lesson 63 failure, reintroduced by running `npm install` on the host when
adding Playwright and axe. Lesson 63 gave the rule; nothing enforced it. So make the
correct action the easy one — `scripts/sync-lockfile.sh`:

```sh
# Lesson 63 found this the hard way: a lockfile written by a newer npm than the build
# image's places one optional transitive package differently, and `npm ci` in the image
# fails with "Missing: @emnapi/core from lock file". Running `npm install` on the host
# reintroduces that every time. Use this instead.
docker run --rm \
  -v "$PWD":/w -w /w \
  --user "$(id -u):$(id -g)" \
  -e npm_config_cache=/tmp/npmcache \
  "$NODE_IMAGE" \
  npm install --package-lock-only --no-audit --no-fund
```

Run it, commit the lockfile, and the image builds. Worth generalising: a rule that
lives only in a lesson gets broken. A rule with a command attached mostly does not.

## The release

```bash
DB_PASSWORD=… ./scripts/release.sh
```

```text
▸ 1. Check the release contract
All release checks passed.
▸ 2. Back up the current database, if there is one
No running database; this is a first deployment.
▸ 3. Build the images and start the stack
▸ 4. Confirm readiness through the production entrypoint
Ready at http://127.0.0.1:8200
▸ 5. Smoke test the deployed application
  /            200
  /login       200
  /register    200

Release complete.
```

## Put real data in it

A recovery drill against an empty database proves nothing. Two accounts, a workspace, a
project, an issue, a comment, and a membership — all created through HTTP, so every row
went through the same validation and authorization a user would meet:

```text
ada@release.test: 201
grace@release.test: 201
comment: 201
workspace=1 project=1 issue=1 member=2
member reads issues: 200
member renames workspace: 403
```

That last pair is the state we most need to survive a restore: the member can
collaborate and cannot administer.

## Back up, then destroy everything

```bash
./scripts/backup-database.sh
```

```text
Wrote backups/issues-20260823T143202Z.sql (25097 bytes).
```

```text
users=2 memberships=2 issues=1 comments=1 activity=2
```

Now destroy it — not stop it, destroy it:

```bash
docker compose … down -v
docker volume ls | grep -c daltprod
```

```text
0
```

The containers and the data volume are gone. Bring up a fresh, empty deployment:

```text
fresh users: 0
```

This is the honest version of the drill. Restoring onto a database that still had the
data would have proved nothing at all.

## Restore

```bash
docker compose … exec -T db psql -U issues_app -d issues \
  -v ON_ERROR_STOP=1 --single-transaction -q -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"

docker compose … exec -T db psql -U issues_app -d issues \
  -v ON_ERROR_STOP=1 --single-transaction -q < backups/issues-20260823T143202Z.sql
```

Both flags are load-bearing. Without `ON_ERROR_STOP=1`, `psql` continues past a failed
statement and exits 0 — you get a partially restored database and a success message.
`--single-transaction` makes the whole restore atomic, so a failure leaves an empty
database rather than half a company's data.

```text
users=2 memberships=2 issues=1 comments=1 activity=2
```

## Prove it through the application, not the database

Matching row counts are necessary and not sufficient. A dump that lost the password
hashes would count perfectly and let nobody in. So drive the restored deployment:

```text
owner logs in: 200
workspaces: [('Release Studio', 'owner')]
issues: ['Ship the release']
comments: ['Rehearsing the release.']
activity: ['issue.created', 'comment.added']

member logs in: 200
member reads issues: 200
member renames workspace: 403
```

Every line is a different guarantee. Both accounts authenticate, so password hashes
survived. The owner's role survived. The issue, its comment, and both activity events
survived in order. The member can still read and still cannot administer — the
authorization boundary came back intact.

## The release path, written down

```text
Deploy
  ./scripts/sync-lockfile.sh        only when dependencies changed; commit the result
  DB_PASSWORD=… ./scripts/release.sh

Roll back application code
  Redeploy the previous image tag. Safe whenever the schema did not change.

Roll back a schema change
  Not automatic, and not something to improvise during an incident:
    1. take a backup before every release that migrates (step 2 does this)
    2. restore it with ON_ERROR_STOP=1 --single-transaction
    3. deploy the previous image
  Restoring loses everything written since the backup. That window is the real cost
  of a migration, and it is why step 2 runs first.
```

## Known limitations

Stating these is part of shipping. Every one is a real boundary of what we built, not a
disclaimer:

```text
Single instance        compose.production.yaml runs one app container. The migrator
                       already takes an advisory lock, so adding replicas is safe from
                       the schema's point of view — but sessions are files on that
                       container's disk, so a second replica needs a shared session
                       store first.

Backups are manual     backup-database.sh is run by a person or by release.sh. There
                       is no schedule, no retention policy, and no off-host copy.

No TLS in this stack   the container serves HTTP on 8200. Production requires a
                       terminating proxy; the configuration guard already refuses to
                       start unless APP_URL is https and the session cookie is secure.

artisan serve          PHP's development server, one request at a time. Adequate for
                       this rehearsal, not for real traffic; a real deployment puts
                       PHP-FPM behind a web server.

Point-in-time recovery a logical dump restores to the moment it was taken. Recovering
                       to an arbitrary point needs WAL archiving, which we have not set
                       up.

Accessibility          automated scans are green and the manual list in Lesson 69 was
                       checked once, by one person, without a screen reader.
```

## Production-readiness report

What the release gate proves, on demand, in about four minutes:

```text
Composer manifest                 valid
PHP application tests             345 passed, 1 skipped (1077 assertions), PostgreSQL 18
  including                       policy matrix across every workspace endpoint × 3 roles
                                  constraint, rollback, pagination, and query-count cases
                                  health, configuration, logging, and error-envelope tests
TypeScript                        application and browser tests, both clean
Lint                              clean
React component tests             47 passed
Production build                  complete, content-hashed, current, within budget
Configuration                     required production values present, debug off, https
Secrets                           none in tracked files, source, or bundle
Browser journeys                  7 passed — registration, invitation acceptance,
                                  permissions, comments, filtering, pagination,
                                  deep links, logout, session expiry
Accessibility                     5 scans passed across 10 screens and states,
                                  plus a keyboard traversal of the filter bar
```

And what this lesson proves, once:

```text
Release       clean checkout → gate → build → migrate → healthy → smoke tested
Recovery      backup → total destruction → fresh deployment → restore →
              authentication, membership, authorization, issues, comments, and
              activity all verified through the running application
```

## What we built

Seventy-one lessons ago this was an empty DALT skeleton and one route. It is now an
issue tracker with accounts, workspaces, memberships, invitations, roles, projects,
issues, assignment, priorities, due dates, labels, comments, activity history, search,
filters, pagination, and dashboards — served as a React application over a JSON API,
stored in PostgreSQL, packaged in a container, and defended by one command that decides
whether any of it may ship.

More usefully: every one of those arrived because the product needed it, in the order
the need appeared, and each one was proven before it was written down. That is the part
worth keeping.
