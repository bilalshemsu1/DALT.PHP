# Contributing to DALT.PHP

Thank you for wanting to help. DALT is a teaching tool, so the bar for a change is
slightly unusual: **a green check nobody earned is worse than no check.** Most of what
follows exists because of a specific defect that shipped.

Participation is governed by the [Code of Conduct](CODE_OF_CONDUCT.md).

---

## Local setup

```bash
git clone https://github.com/Ibnu-Afdel/DALT.PHP.git
cd DALT.PHP
cp .env.example .env

composer install
npm ci                          # root toolchain (React, Vite, Vitest, ESLint)
npm ci --prefix .dalt           # learning-platform toolchain (Vue, Vite, Tailwind)

php artisan serve               # http://localhost:8000
```

You need PHP 8.2, 8.3, or 8.4 with `pdo` and `pdo_sqlite`. Node 20.19+ or 22.12+ is
needed only to rebuild frontend assets — a plain checkout runs without it, because the
built assets are committed. PostgreSQL and Docker are needed only for the labs that use
them.

---

## What must pass before you open a pull request

Run all of these. CI runs the same set, plus a PHP version matrix.

```bash
php artisan challenge:stop            # a committed challenge transaction corrupts the tree
php artisan test                      # framework suite
php vendor/bin/pest .dalt/tests --bootstrap=.dalt/bootstrap.php    # course suite
php artisan platform:status           # catalog loads; validation errors throw here

composer validate --strict
composer audit
npm audit --audit-level=high
npm run typecheck && npm run lint && npm test && npm run build
npm run build --prefix .dalt
git diff --check
```

Two things to know before you debug a failure:

- `Tests\Feature\DaltSuiteTest` shells out to the course suite and asserts exit `0`. Any
  course failure therefore also appears as exactly **one** framework failure. One
  framework failure plus a red course suite means the course suite is red, not that
  framework code regressed.
- The lab tests copy each lab to `/tmp` and run a real `npm install`. On a nearly full
  `/tmp` they fail with `ERR_INVALID_PACKAGE_CONFIG` on a file npm just wrote, which says
  nothing about disk. Check `df -h /tmp` and clear stale `/tmp/dalt-*` directories.

### If you touch `framework/`, `artisan`, `public/`, `config/`, or root `tests/`

You must also prove the framework still stands alone without the course:

```bash
S=/tmp/dalt-skeleton && rm -rf $S && cp -r . $S && cd $S && rm -rf .git node_modules
[ -f .env ] || cp .env.example .env
php artisan platform:remove --force && php artisan test
```

The suite is green with `.dalt/` present no matter how badly a root test depends on it,
because `.dalt/` is always there in development. A root test added as an *isolation
guard* once read `.dalt/package.json` unconditionally and threw on a skeleton. Only
actually removing the platform reveals that class of bug.

---

## The boundary that matters most

**Deleting `.dalt/` must leave a working framework.** Nothing in `framework/`, `config/`,
`public/`, `artisan`, or root `tests/` may depend on a course artifact. This is the
project's central promise and it is enforced in CI on every commit.

| Directory | What belongs there |
|---|---|
| `framework/` | The framework. No course knowledge, no lesson data, no `.dalt` paths. |
| `app/`, `routes/`, `resources/`, `config/`, `database/` | The learner's application. |
| `.dalt/` | Everything about learning: lessons, challenges, the platform UI, its own Vite build, its own tests. Freely changeable; not covered by the v1 promise. |
| `documentation/` | Public prose documentation that ships with the package. |
| `tests/` | Framework tests. Must pass on a skeleton. |

[COMPATIBILITY.md](COMPATIBILITY.md) records what `1.x` promises. A change that breaks
anything listed as covered needs a major release, so raise it as an issue first rather
than opening a pull request against it.

---

## Contributing course content

Course content has a higher bar than most documentation, because a lesson that is wrong
teaches something wrong.

**Every command a lesson tells the learner to run must have been run by you, in a clean
copy, with its real output compared against what the lesson claims will happen.** Not
read, not reasoned about — run. Two build milestones once passed every structural check
and still could not be completed as written, and neither failure was visible from
reading them.

**Verification must satisfy the plausible-fake standard.** Wherever something is checked
automatically: the broken state must fail, a genuine fix must pass, **and a plausible
fake fix must fail.** The third is the one that gets skipped. A challenge that passes on
a string match cannot tell a learner whether they understood anything, which is why
challenge checks assert behavior rather than matching source text.

**Every stage of a build milestone needs a "Check it yourself".** Without one the stage
is a suggestion and the learner has no way to know they finished it.

**If verification is manual, say so in the learner-facing text.** Do not imply a check
happened when it did not.

Labs and fixtures must be *executed* by a test, not merely asserted to exist.
`.dalt/tests/Feature/FullstackLabExecutionTest.php` is where that lives. A structural
assertion like `is_file(...)` cannot tell you the learner's first command works — that
exact gap once shipped a lab whose `npm test` failed on the first run.

Most of these rules are enforced by the course suite rather than left to diligence. When
one fails, fix the content. If the standard itself is wrong, change the governing
document **and** the test together, and say why in the pull request.

---

## Pull requests

- Branch from `main`. Keep one logical change per pull request.
- Write commit messages in the imperative mood: "Fix route ordering", not "Fixed" or
  "Fixes".
- Update `CHANGELOG.md` under `## [Unreleased]` using the Keep a Changelog headings
  (`Added`, `Changed`, `Deprecated`, `Removed`, `Fixed`, `Security`).
- If you deviate from a governing document, record the deviation **in that document** in
  the same pull request. A silent deviation is how a plan stops describing the repo.
- Say what you ran. "Tests pass" means you ran them and read the output. If something was
  skipped, say it was skipped and why — an unexplained skip is treated as a failure.

Pull requests need CI green and one review before merge.

---

## Reporting bugs

Open an issue using the bug template. Include your PHP version, operating system,
database driver, the exact command you ran, what you expected, and what actually
happened. A reproduction beats a description.

For course content, name the lesson or challenge ID (for example `FS04.2`, `B07`,
`broken-routing`) and quote the instruction that did not work.

## Reporting a vulnerability

**Do not open a public issue.** [SECURITY.md](SECURITY.md) has the private disclosure
route and the response timeline.

Note that `.dalt/course/challenges/` contains *deliberately* vulnerable code — that is
the point of the challenges. Those fixtures are isolated from the running application
and are removed entirely by `php artisan platform:remove --force`. A report that a
challenge fixture is insecure is not a vulnerability report; a report that challenge code
can reach the running application is.
