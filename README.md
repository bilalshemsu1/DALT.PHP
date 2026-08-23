<div align="center">
  <h1>DALT.PHP</h1>
  <p><strong>A transparent PHP framework for learning backend development</strong></p>

  [![Latest Version](https://img.shields.io/packagist/v/ibnuafdel/daltphp.svg?style=flat-square)](https://packagist.org/packages/ibnuafdel/daltphp)
  [![PHP Version](https://img.shields.io/packagist/php-v/ibnuafdel/daltphp.svg?style=flat-square)](https://packagist.org/packages/ibnuafdel/daltphp)
</div>

DALT is a learning framework where you can see and understand everything. The whole framework is under 3,000 lines of readable PHP across 25 files — small enough to read in an afternoon. You write real SQL queries, handle security yourself, and see exactly how routing, sessions, and authentication work.

This isn't a framework for production apps. It's a framework for understanding how web applications actually work.

---

## 🎯 What You Get

A working web application with routing, database access, and validation, plus an optional authentication example installed with `php artisan example:install auth`. Unlike production frameworks, you can read and understand every line of code.

The auth installer is repeatable and refuses to overwrite application files or conflicting literal routes. Untouched generated files can later be refreshed with `php artisan example:update auth` or removed with `php artisan example:uninstall auth`; learner modifications are preserved by default.

You write real SQL with prepared statements - no ORM hiding the queries. You see `$_SESSION` arrays directly - no magic session handling. You add CSRF tokens to forms yourself - no automatic protection. This is intentional. You learn by doing it yourself.

The framework includes optional lessons and debugging challenges to help you get started, but they're easily removable. The real learning happens when you build your own projects.

---

## 🚀 Quick Start

```bash
# Create a new project
composer create-project ibnuafdel/daltphp my-project --remove-vcs
cd my-project

# Start the application
php artisan serve    # http://localhost:8000
```

Visit `http://localhost:8000` to see your app. Visit `http://localhost:8000/learn` for the optional courses and challenges.

Production-ready frontend assets are included, so Node.js is not required to start a new project. If you change the learning-platform CSS, JavaScript, or Vue components, run `npm ci` and `npm run dev`; use `npm run build` before distributing those changes.

### Requirements

| | |
|---|---|
| PHP | 8.2, 8.3, or 8.4 |
| Extensions | `pdo` and `pdo_sqlite` (bundled with most PHP builds); `pdo_pgsql` only if you set `DB_DRIVER=pgsql` |
| Databases | SQLite (default) and PostgreSQL |
| Composer | 2.x |
| Node.js | not required to run a project; needed only to rebuild frontend assets |
| Operating systems | tested on Linux; macOS is expected to work. Windows is untested — use WSL2 |

### Deployment boundary

DALT is an educational framework, not a production-hardened runtime. If you adapt a project for deployment, serve only the `public/` directory, install PHP dependencies with `composer install --no-dev --optimize-autoloader`, set `APP_ENV=production` and `APP_DEBUG=false`, configure HTTPS and secure session cookies, and review [SECURITY.md](SECURITY.md). The built-in `php artisan serve` command is for local development only.

---

## 📚 Learning Features (Optional)

Everything under `/learn` is optional and lives entirely in `.dalt/`. There are three
separate learning surfaces, and you do not have to take them in order:

**DALT Core** — 19 lessons on framework internals, Docker, and PostgreSQL: request
lifecycle, routing, middleware, authentication, sessions, databases, containers,
reliability, and observability. Short lessons with small experiments.

**Fullstack theory** — 60 concise lessons across 13 parts, taking React + TypeScript +
Tailwind down through HTTP and JSON into PHP and PostgreSQL under Docker. Theory with
small disposable experiments, plus 19 build milestones that specify what to make.

**DALT Build** — 71 guided lessons that build one serious issue-tracking application
from an empty DALT skeleton, in the order real product development creates the need for
each idea. This is a course, not starter code: you write the application yourself.

**Challenges** — 22 deliberately broken states across framework code, container
configuration, SQL, migrations, reliability, and database performance. Each one is
verified by behavior rather than by matching your source text.

```bash
php artisan challenge:list
php artisan challenge:start broken-routing
php artisan challenge:verify
php artisan challenge:stop
```

These are completely optional. Remove them with `php artisan platform:remove` to keep only the framework core. The command preserves application files, including an installed auth example; generated auth files become learner-owned after the platform is gone. Use `--force` only when intentionally running the removal non-interactively.

---

## 🛠️ Why PHP for Learning?

PHP is perfect for learning backend development because HTTP concepts are built into the language. You see `$_GET`, `$_POST`, and `$_SESSION` directly instead of framework abstractions. Code runs synchronously (top-to-bottom), making it easier to understand than async languages.

After learning with PHP, these concepts transfer to any backend language. You'll understand what Laravel's Eloquent is doing, what Express.js middleware means, and how authentication works in any framework.

---

## 📖 Documentation

**[Framework Reference](documentation/framework-reference.md)** — the public API, with every documented behavior verified against the framework.

- [Installation and Quick Start](documentation/installation-and-quick-start.md)
- [Architecture](documentation/architecture.md)
- [Errors and Debugging](documentation/errors-and-debugging.md)
- [Contributor Content Guide](documentation/contributor-content.md)
- [Competency Roadmap](documentation/competency-roadmap.md)

Hosted documentation: **[dalt.ibnuafdel.com/docs](https://dalt.ibnuafdel.com/docs)**

- [What is DALT?](https://dalt.ibnuafdel.com/docs/start/what-is-dalt) — what the framework is, and what it is not
- [Installation](https://dalt.ibnuafdel.com/docs/start/installation) — getting a project running
- [Learn locally](https://dalt.ibnuafdel.com/docs/start/learn-locally) — using the bundled courses
- [Architecture](https://dalt.ibnuafdel.com/docs/build/architecture) — how a request moves through DALT
- [Framework reference](https://dalt.ibnuafdel.com/docs/reference/framework) — the public API

---

## 📦 What v1 promises

`v1.0.0` is a stable release. [COMPATIBILITY.md](COMPATIBILITY.md) records exactly which
public APIs, Artisan commands, configuration keys, routes, and removal behavior are
covered by that promise, and what is deliberately left free to change. Anything that
breaks a covered contract requires a new major version.

Security fixes and the supported version window are in [SECURITY.md](SECURITY.md). The
release history is in [CHANGELOG.md](CHANGELOG.md).

---

## 🤝 Contributing

DALT is open source and welcomes contributions through the [GitHub repository](https://github.com/Ibnu-Afdel/DALT.PHP).
Read [CONTRIBUTING.md](CONTRIBUTING.md) first — it covers local setup, the commands that
must pass, the boundary between framework and course code, and how course content is
reviewed. Participation is governed by the [Code of Conduct](CODE_OF_CONDUCT.md).

Please do not report security issues in a public issue. [SECURITY.md](SECURITY.md) has
the private disclosure route.

Join the community: [Telegram](https://t.me/daltphp)

---

**Learn backend development by seeing how it actually works** 🔧
