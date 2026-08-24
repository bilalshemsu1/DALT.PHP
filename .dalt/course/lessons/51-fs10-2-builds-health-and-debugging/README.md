# FS10.5 — Health, startup order, logs, and production safety

Lesson ID: FS10.5
Lesson format: Concise theory
Part: 10 — Docker
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Applied
Prerequisites: FS10.4
Last reviewed: 2026-08-23

We will make "is it up?" a question with a useful answer, and see what a container tells us when something goes wrong.

> **Helpful background:** [Compose services, networking, environment, and volumes](/learn/lessons/75-fs10-4-compose-networks-volumes-and-environment)

## What we will learn

- write a health check that fails when the service cannot do its job;
- gate startup on readiness instead of on a guessed delay;
- read logs and status as evidence, and keep a container from running as root.

## Running is not the same as working

A process can be alive and completely unable to serve anything. That gap is where most "but the container is up" incidents live, and a health check is only useful if it can tell the difference.

Our lab has both kinds of endpoint on purpose:

```php
case '/health':
    echo json_encode(['status' => 'ok']);          // the process answered
    break;

case '/ready':
    $store->connection()->query('SELECT 1');       // the work is actually possible
    echo json_encode(['status' => 'ready']);
    break;
```

`/health` answers whenever PHP is running. `/ready` answers only when the database is reachable. Point a health check at the first one and it will report a healthy container through an entire database outage.

```yaml
healthcheck:
  test: ["CMD-SHELL", "php -r \"exit(@file_get_contents('http://127.0.0.1:8000/ready') === false ? 1 : 0);\""]
  interval: 2s
  timeout: 3s
  start_period: 30s
  retries: 5
```

`start_period` is what makes a small `retries` safe: failures during startup do not count, so a real outage is reported in about ten seconds instead of a minute.

## Gate startup on readiness, never on a delay

A database container is "started" long before it accepts connections. Waiting a fixed number of seconds encodes a guess about someone's laptop:

```yaml
depends_on:
  db:
    condition: service_healthy
```

`depends_on` alone only orders *starting*. The `condition: service_healthy` form waits for the database's own health check — `pg_isready -U dalt -d dalt_course`, a question only the database can answer — before the application starts at all. `docker compose up --wait` then returns only once every service is healthy, which makes it usable in a script.

## Debug from evidence, in order

When something is wrong, the order that wastes the least time is:

```text
docker compose ps        is it running, and does it consider itself healthy?
docker compose logs app  what did the process say before it stopped saying anything?
docker compose exec app  ask the container itself, from inside its own network
docker inspect           the exact configuration that is running, not the file you edited
```

The last one matters more than it looks: a container runs the configuration it was created with. Editing `compose.yaml` changes nothing until the container is recreated, and "I already fixed that" is usually a container that was never rebuilt.

Fix causes in the Dockerfile or in Compose, never by hand inside a running container. A manual fix survives exactly until the next `docker compose up --build`, and it is invisible to everyone else.

## Do not run as root

The runtime stage from FS10.3 ends with two lines that cost nothing:

```dockerfile
RUN useradd --create-home --uid 10001 app
USER app
```

If the process is compromised, the difference between uid 0 and uid 10001 inside the container is the difference between an incident and a much worse one. It is not a complete boundary — it is one that is free.

The same reasoning applies to secrets. FS10.2 kept them out of the build context; Compose keeps them in the environment at run time. A credential in a layer is permanent; a credential in the environment is replaceable.

## Try it

**Workspace:** copy the Part 10 lab. Docker is required.

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/docker-lab/starter .dalt/workspace/fs10-docker
cd .dalt/workspace/fs10-docker
docker compose up -d --wait
```

**Starting state:** the application's health check asks `/ready`; the database's asks `pg_isready`; `app` waits for `db` to be healthy.

```bash
docker compose ps --format '{{.Service}} {{.Status}}'
docker compose stop db
sleep 12
docker compose ps --format '{{.Service}} {{.Status}}'
curl http://127.0.0.1:58000/health
curl -o /dev/null -w '%{http_code}\n' http://127.0.0.1:58000/ready
docker compose exec app id -u
```

**Expected result:** both services start `healthy`. About ten seconds after the database stops, `app` reports `unhealthy` — while `/health` still returns `{"status":"ok"}` with a 200 and `/ready` returns 503. `id -u` prints `10001`.

Now make the check naive and watch it lie:

```bash
sed 's|/ready|/health|' compose.yaml > compose.naive.yaml
docker compose -f compose.naive.yaml -p naivecheck up -d --wait
docker compose -f compose.naive.yaml -p naivecheck stop db
sleep 20
docker compose -f compose.naive.yaml -p naivecheck ps --format '{{.Service}} {{.Status}}'
docker compose -f compose.naive.yaml -p naivecheck down -v
```

With the database stopped for twenty seconds, that stack still reports `app Up 26 seconds (healthy)`.

**Reset:** `docker compose down -v`, delete `compose.naive.yaml`, and delete the workspace copy.

## What to notice

The naive stack is not broken. Every command succeeds, the status is green, and the application cannot serve a single issue. A check that cannot fail is indistinguishable from no check — and it is worse, because someone is now relying on it.

Notice which service each check belongs to. `pg_isready` is a question about PostgreSQL and lives with PostgreSQL; `/ready` is a question about our application and lives with our application. A health check written from outside the service usually ends up testing the wrong thing.

## Common mistakes

- Health checks that only prove a process is alive.
- `sleep 10` in place of `condition: service_healthy`.
- Editing `compose.yaml` and not recreating the container, then debugging the old one.
- Fixing something by hand inside a running container.
- Shipping an image whose main process is root.

## Check your understanding

1. What is the difference between the questions `/health` and `/ready` answer?
2. What does `start_period` let you do that a plain `retries` value cannot?
3. Why is `depends_on: condition: service_healthy` better than a fixed delay?
4. Why is a manual fix inside a running container not a fix?

<details><summary>Check your answers</summary>

1. `/health` asks whether the process is alive; `/ready` asks whether the work the service exists to do is currently possible.
2. Give a slow starter time without counting those failures, so `retries` can stay low and real outages are reported quickly.
3. It waits for the database's own answer instead of guessing how long that answer takes on this machine.
4. Nothing records it: the next rebuild or recreate discards it, and no one else's environment has it.
</details>

## Next

Next we will leave containers behind and look at what PostgreSQL does when the data stops being small.

<details><summary>Maintainer source record</summary>

- Source dossier: Docker documentation research notes and Full Stack Open Containers research notes, sections 57–72.
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: Compose `healthcheck` and `depends_on` references, the Dockerfile `HEALTHCHECK` and `USER` references, `docker compose up --wait`, and the official PostgreSQL image's `pg_isready` guidance.
- Versions: Docker 29.7.2; Docker Compose 5.4.0; `postgres@sha256:9a8afca5…` (PostgreSQL 18.4); `php@sha256:f78661…` (PHP 8.4.24).
- Consulted: 2026-08-23.
- Curriculum authority: DALT Fullstack theory curriculum, Batch 11, FS10.5.
- DALT files inspected: `docker-lab`, the Part 10 track manifest, and the former FS10.2 page.
- Extracted material: "health is a question with a useful answer", "debug from evidence, in order", "non-root is a boundary, not a slogan", and the `depends_on`/`sleep` mistakes from the former FS10.2.
- Verified in the lab: the honest stack reports `unhealthy` about ten seconds after the database stops; the naive stack still reported `app Up 26 seconds (healthy)` twenty seconds into the same outage.
</details>
