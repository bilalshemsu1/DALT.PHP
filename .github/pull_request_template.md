<!--
Security fixes do not go here. See SECURITY.md for the private disclosure route.
-->

## What this changes

<!-- One or two sentences. If it fixes an issue, write "Fixes #123". -->

## Why

<!-- The problem, not the patch. What was wrong, or what could not be done before? -->

## How it was verified

<!--
"Tests pass" means you ran them and read the output. Paste the summary lines.
If something was skipped, say so and why — an unexplained skip is treated as a failure.
-->

```
php artisan test
php vendor/bin/pest .dalt/tests --bootstrap=.dalt/bootstrap.php
```

- [ ] `php artisan challenge:stop` was run before committing
- [ ] Framework suite passes
- [ ] Course suite passes
- [ ] `git diff --check` is clean
- [ ] `CHANGELOG.md` updated under `## [Unreleased]`

## Scope

<!-- Tick everything this touches. -->

- [ ] `framework/` — the framework itself
- [ ] `artisan`, `public/`, `config/`, or root `tests/`
- [ ] `.dalt/` — the learning platform
- [ ] Course content (lessons, challenges, build milestones)
- [ ] Documentation only
- [ ] Dependencies or build tooling

### If you ticked `framework/`, `artisan`, `public/`, `config/`, or root `tests/`

The framework must still stand alone without the course. Paste the result:

```bash
S=/tmp/dalt-skeleton && rm -rf $S && cp -r . $S && cd $S && rm -rf .git node_modules
[ -f .env ] || cp .env.example .env
php artisan platform:remove --force && php artisan test
```

- [ ] The skeleton suite passes
- [ ] Nothing I added under `framework/`, `config/`, `public/`, `artisan`, or `tests/`
      reads a course artifact

### If you ticked course content

- [ ] I ran every command the lesson tells the learner to run, in a clean copy, and
      compared the real output against what the lesson claims
- [ ] Every build stage I touched has a "Check it yourself"
- [ ] Verification meets the plausible-fake standard: the broken state fails, a genuine
      fix passes, **and a plausible fake fix fails**
- [ ] Where verification is manual, the learner-facing text says so

### If you ticked dependencies

- [ ] `composer audit` and `npm audit --audit-level=high` report nothing
- [ ] Committed build output was rebuilt and the result committed

## Compatibility

- [ ] This changes nothing listed as covered in [COMPATIBILITY.md](../blob/main/COMPATIBILITY.md)
- [ ] This changes a covered contract, and I have said so below

<!--
If it does change a covered contract: which one, and why it cannot be done additively.
That conversation belongs in an issue before the pull request.
-->
