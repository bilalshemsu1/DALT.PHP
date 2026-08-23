# Containerize the complete web application

Our application currently runs because a laptop happens to have PHP 8.4, a PostgreSQL
extension, Node, npm, Composer, and a `.env` file. We will replace that pile of
assumptions with one image that carries exactly what serving a request needs — and
leaves every build tool behind.

> **Helpful background:** Docker's [multi-stage build guide](https://docs.docker.com/build/building/multi-stage/)
> explains the `COPY --from` mechanism this lesson is built on.

## Decide what ships before writing anything

Lesson 62 ended on the distinction that shapes this entire file:

```text
build time    node, npm, node_modules, resources/app/**   → produces public/build
              composer, git, unzip, composer.json         → produces vendor/
run time      PHP + pdo_pgsql, our code, vendor, public/build
```

Nothing on the first two lines belongs in the shipped image. Not because of size — the
real reason is that every tool present in production is something an attacker can use
and something we have to keep patched.

Start with `.dockerignore`, so the build context cannot carry what we are about to
exclude:

```text
.git
.env
.env.*
!.env.production.example
node_modules
vendor
public/build
storage/logs/*
test-results
playwright-report
tests/browser/.fixtures.json
.dalt
```

`.env` is first for a reason. Lesson 61 kept secrets out of Git; this keeps them out of
a layer, where a `RUN rm` later would not remove them. `vendor` and `public/build` are
excluded because the image builds its own — copying the host's would ship whatever
state our laptop happened to be in.

## Stage one: build the frontend

```dockerfile
FROM node@sha256:b84d52cd45bfe261096ccbf886955d431b8b9ed01b72eaef588e8886bda09e78 AS frontend

WORKDIR /build

# Manifests first: an unchanged lockfile reuses the install layer when only source moved.
COPY package.json package-lock.json ./
RUN npm ci

COPY tsconfig.json vite.config.mjs ./
COPY resources ./resources
RUN npm run build
```

`npm ci` rather than `npm install`, because `ci` installs exactly the lockfile and fails
when the lockfile and `package.json` disagree.

It failed on our first build, and the reason is worth knowing:

```text
npm error `npm ci` can only install packages when your package.json and
npm error package-lock.json are in sync.
npm error Missing: @emnapi/core@1.11.3 from lock file
```

Nothing was wrong with our dependencies. Our host runs npm 11 and the image runs npm
10, and the two resolve one optional transitive package to different places in the
tree. A lockfile is only reproducible with a compatible npm — which means the version
that builds the image should be the version that writes the lockfile:

```bash
docker run --rm -v "$PWD":/w -w /w --user "$(id -u):$(id -g)" \
  -e npm_config_cache=/tmp/npmcache \
  node@sha256:b84d52cd45bfe261096ccbf886955d431b8b9ed01b72eaef588e8886bda09e78 \
  npm install --package-lock-only
```

Commit the regenerated lockfile. From now on, one Node builds this project.

## Stage two: the PHP platform

Both the dependency install and the runtime need PHP with `pdo_pgsql`, so build it once
and branch:

```dockerfile
FROM php@sha256:f78661b492226388a7057679cc731c3e43bc92ba66cd49a8cfe12374a56bee9f AS php-base

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends libpq-dev; \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql; \
    apt-get purge -y --auto-remove libpq-dev; \
    apt-get install -y --no-install-recommends libpq5; \
    rm -rf /var/lib/apt/lists/*
```

`libpq-dev` provides the headers the extension compiles against; `libpq5` is the shared
library it loads at run time. Installing, compiling, purging, and cleaning in **one**
`RUN` matters: a later `RUN rm` would leave everything in the earlier layer, where it
still occupies the image and still appears in its history.

## Stage three: dependencies, on the platform that will run them

```dockerfile
FROM php-base AS vendor

COPY --from=composer@sha256:4d71c3c2109c61d5415544264b59ad4087e4c5b7244481723664138fd36d5040 /usr/bin/composer /usr/bin/composer

# --prefer-dist downloads zipped packages, so Composer needs something that can open
# them; a few packages resolve as VCS clones, which is what git is for. Both are
# build-time only and stay in this stage.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip; \
    rm -rf /var/lib/apt/lists/*

WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist
```

Our first attempt used the `composer:2` image directly and failed:

```text
- Root composer.json requires PHP extension ext-pdo_pgsql * but it is missing from your system.
```

Composer was right. Lesson 34 declared `ext-pdo_pgsql` as a requirement, and the
Composer image does not have it. We could have passed `--ignore-platform-req`, which
would have made the message go away and the check meaningless. Building `FROM php-base`
instead means dependencies are resolved against the platform that will actually run
them — which is the entire point of declaring a platform requirement.

`--no-dev` leaves Pest, Faker, and everything else we test with out of the image.

## Stage four: the runtime

```dockerfile
FROM php-base AS runtime

COPY docker/php.ini /usr/local/etc/php/conf.d/application.ini

RUN useradd --create-home --uid 10001 app

WORKDIR /app

COPY --chown=app:app app ./app
COPY --chown=app:app config ./config
COPY --chown=app:app database ./database
COPY --chown=app:app framework ./framework
COPY --chown=app:app public ./public
COPY --chown=app:app resources/views ./resources/views
COPY --chown=app:app routes ./routes
COPY --chown=app:app artisan composer.json composer.lock ./

COPY --from=vendor --chown=app:app /build/vendor ./vendor
COPY --from=frontend --chown=app:app /build/public/build ./public/build
```

Each directory is named rather than `COPY . .`. `.dockerignore` would already exclude
most of what we do not want, but naming what we *do* want means a new top-level folder
does not silently join the image.

Notice `resources/views` alone — the runtime renders those, and never compiles
`resources/app`.

The PHP configuration is production's, not development's:

```ini
display_errors = Off
log_errors = On
expose_php = Off
session.cookie_httponly = 1
session.use_strict_mode = 1
```

Then the two writable paths, created by the image and owned by the application user, so
the source tree can stay read-only:

```dockerfile
RUN set -eux; \
    mkdir -p storage/logs storage/framework/sessions; \
    chown -R app:app storage

USER app
EXPOSE 8000

# artisan serve takes positional host and port.
CMD ["php", "artisan", "serve", "0.0.0.0", "8000"]
```

`0.0.0.0` rather than `127.0.0.1`: a server bound to the container's own loopback is
reachable only from inside the container, and the published port would connect to
nothing.

## A production stack, separate from development

`compose.production.yaml` runs the image with nothing bind-mounted, so what runs is
what would ship:

```yaml
  app:
    build:
      context: .
      target: runtime
    environment:
      APP_ENV: production
      APP_DEBUG: "false"
      APP_URL: ${APP_URL:?APP_URL must be provided}
      SESSION_SECURE_COOKIE: "true"
      DB_HOST: db
      DB_PASSWORD: ${DB_PASSWORD:?DB_PASSWORD must be provided}
    ports:
      - "127.0.0.1:8200:8000"
    depends_on:
      db:
        condition: service_healthy
```

`${DB_PASSWORD:?...}` fails the whole command when the variable is absent, instead of
starting a stack with an empty password. `DB_HOST: db` is the service name on this
project's private network — the database is not published to the host at all.

Now the part that cost us a confusing ten minutes. The first run returned 500 on every
route, and the log said:

```text
- APP_URL must use https in production.
```

But the compose file sets `APP_URL` from an environment variable. The catch is that
**Compose interpolates `${...}` from `.env` in the project directory**, and our `.env`
is configured for host development with `APP_URL=http://localhost:8000`. A "production"
stack had quietly inherited the laptop's settings.

Give it an explicit env file instead:

```bash
DB_PASSWORD=a-real-deployment-secret \
  docker compose --env-file .env.production.example \
  -f compose.production.yaml -p daltprod up -d --wait
```

```text
Container daltprod-db-1   Healthy
Container daltprod-app-1  Healthy
```

Take the Lesson 61 guard seriously here: it turned a silent misconfiguration into a
refusal with a named reason. That is what it was for.

## The second trap: a volume remembers

With the env file in place the application started, and the database still refused:

```text
psql: FATAL:  role "issues_app" does not exist
```

PostgreSQL creates `POSTGRES_USER` only when it initialises an **empty** data
directory. Our first attempt had already created a cluster in the named volume with
different credentials, and the second run reused it. Changing the environment does not
retroactively change a database that already exists:

```bash
docker compose --env-file .env.production.example -f compose.production.yaml \
  -p daltprod down -v
```

`down -v` discards the volume. Then everything works:

```bash
docker compose … exec -T app php artisan migrate
```

```text
Ran 13 migrations.
```

## Prove the whole thing through the container

Every route, through the production entrypoint:

```text
/                            200
/login                       200
/register                    200
/dashboard                   302   (guest → login, correct)
```

Registering and creating a workspace over real HTTP, then following a React-owned deep
URL:

```text
register: 201
deep link /workspaces/1: 200
api dashboard: 200
unknown deep url: 404
```

The hashed assets from the frontend stage are served by the same entrypoint:

```text
/build/assets/main-CdYNWB63.css   200
/build/assets/main-Cpmp8osd.js    200
```

And the process is not root:

```bash
docker compose … exec -T app id
```

```text
uid=10001(app) gid=10001(app) groups=10001(app)
```

## Prove what is absent

The claim that build tools stayed behind is worth checking rather than assuming:

```bash
docker run --rm dalt-issues:local sh -c '
  echo "node: $(command -v node || echo absent)";
  echo "npm: $(command -v npm || echo absent)";
  echo "composer: $(command -v composer || echo absent)";
  echo "git: $(command -v git || echo absent)";
  echo ".env present: $([ -f /app/.env ] && echo yes || echo no)"'
```

```text
node: absent
npm: absent
composer: absent
git: absent
.env present: no
```

The Node stage alone is 1.13GB and the Composer image 225MB; our runtime image is
542MB, and both of those contributed only their output.

The configuration guard travels with the image too:

```bash
docker run --rm -e APP_ENV=production -e APP_DEBUG=true -e APP_URL=http://x \
  dalt-issues:local php -r '…Configuration::problems($_ENV)…'
```

```text
DB_HOST is required in production and is missing or empty.
…
APP_DEBUG must be false in production.
APP_URL must use https in production.
```

## Host development is unchanged

```bash
php scripts/database-status.php
curl -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8091/login
```

```text
Database: dalt_issue_tracker
200
```

One caution learned the hard way while writing this lesson: because our Dotenv load is
immutable, a `DB_PASSWORD` exported into your shell for the production stack **also**
overrides `.env` for host commands. If a host command suddenly cannot reach the
database, check your shell before you check the database.

Our application now ships as one image that carries its code, its dependencies, and its
built assets — and nothing that made them. The next lesson gives that container an
honest answer to "are you ready?".
