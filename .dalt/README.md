# .dalt/ - Learning Platform Internals

This directory contains the DALT learning platform UI and assets. It's completely separate from the framework core.

Nothing in here is covered by the v1 compatibility promise — see
[COMPATIBILITY.md](../COMPATIBILITY.md). Course content changes continuously.

## What's Inside

- `course/` - The learning content: `lessons/` (DALT Core and Fullstack theory),
  `guided-build/` (the DALT Build course), `build/` (Fullstack build milestones), and
  `challenges/` (deliberately broken states with executable verification)
- `Core/` - Platform-only PHP classes: catalog loading, challenge lifecycle, verification
- `Http/controllers/` - Controllers for `/learn` routes (lesson viewer, challenge UI, verification API)
- `resources/` - Vue components, CSS, and views for the learning interface
- `routes/` - Platform routes (loaded automatically in `public/index.php`)
- `stubs/` - Code templates for authentication scaffolding
- `scripts/` - Setup scripts (post-create hooks)
- `tests/` - The course suite; run it with `php vendor/bin/pest .dalt/tests --bootstrap=.dalt/bootstrap.php`
- `build/` - Prebuilt platform assets, committed so a new project runs without Node.js

## The three learning surfaces

**DALT Core** teaches framework internals, Docker, and PostgreSQL through short lessons
and small experiments, backed by the debugging challenges.

**Fullstack theory** teaches React, TypeScript, Tailwind, HTTP, PHP, and PostgreSQL as
theory with small disposable experiments, plus build milestones that specify what to make.

**DALT Build** is a guided course that has you build one issue-tracking application from
an empty skeleton. It ships as *lessons*, not as starter code: the reference application
those lessons were written from is not distributed, and `app/`, `routes/`, and
`resources/` in a fresh project are the plain framework skeleton. You write the
application.

## Authentication Example

Install the small application-owned authentication example with:

```bash
php artisan example:install auth
php artisan migrate
```

The installer refuses existing destination files or conflicting literal routes and records hashes for the files it generated. Repeating install is safe. Use `php artisan example:update auth` only while the generated files are untouched, and `php artisan example:uninstall auth` to remove an unchanged example. Both commands stop if learner edits are present; `example:uninstall auth --force` explicitly discards those generated-file edits.

The example intentionally covers registration, login, logout, sessions, validation, password hashing, prepared queries, and CSRF. It is educational scaffolding, not a production authentication suite; rate limiting, password reset, verification, two-factor authentication, and account recovery are omitted.

## Removing the Learning Platform

Want to use DALT as a clean micro-framework? Run the supported cleanup command:

```bash
php artisan platform:remove
```

The command asks once before removing `.dalt` and never rewrites application routes, views, package metadata, Vite configuration, or the README. An installed auth example is preserved and becomes learner-owned application code because its update/uninstall manager leaves with the platform. Pass `--force` only for an intentional non-interactive removal.

The framework core (`framework/Core/`) remains runnable when `.dalt/` is absent. `Core\Platform` discovers the optional directory once during bootstrap and owns its boot file, routes, controller roots, and view roots. A partially removed platform fails with a focused error instead of being loaded unpredictably.

## How the Fallback Works

The framework checks user code first, then falls back to `.dalt/`:

This means:
- Your code in `app/` always takes priority
- Platform code in `.dalt/` is a fallback
- Application routes are registered before platform routes
- Removing `.dalt/` with the supported command doesn't break your app

## For Contributors

The learning platform has **its own** frontend toolchain, isolated from the root one:

| | Root `package.json` | `.dalt/package.json` |
|---|---|---|
| Serves | the learner's application | the `/learn` interface |
| Stack | React, TypeScript, Vitest, ESLint | Vue 3, Tailwind, Vite |
| Build output | `public/build/` | `.dalt/build/` |
| Install | `npm ci` | `npm ci --prefix .dalt` |
| Rebuild | `npm run build` | `npm run build --prefix .dalt` |

Both sets of built assets are committed, which is why a fresh project runs without
Node.js installed. If you change platform CSS, Vue components, or views, rebuild
`.dalt/build/` and commit the result.

See [CONTRIBUTING.md](../CONTRIBUTING.md) for the full contribution rules, including the
standard course content has to meet.
