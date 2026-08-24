# FS10.4 — Compose services, networking, environment, and volumes

Lesson ID: FS10.4
Lesson format: Concise theory
Part: 10 — Docker
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Applied
Prerequisites: FS10.3
Last reviewed: 2026-08-23

We will describe the application and its database as one topology, and find out precisely which parts of it survive being torn down.

> **Helpful background:** [Layers, build cache, and multi-stage images](/learn/lessons/74-fs10-3-layers-cache-and-multistage-builds)

## What we will learn

- describe several services and their relationships in one reviewable file;
- reach another service by its name on a private network;
- separate configuration, published ports, and durable storage from each other.

## Compose describes relationships

One container was a `docker run` line. Two containers that must find each other, start in the right order, and share configuration is a topology, and a topology belongs in a file that can be read and reviewed:

```yaml
services:
  db:
    image: postgres@sha256:9a8afca5…
    environment:
      POSTGRES_DB: dalt_course
      POSTGRES_USER: dalt
      POSTGRES_PASSWORD: dalt-course-local
    volumes:
      - db-data:/var/lib/postgresql
      - ./database:/docker-entrypoint-initdb.d:ro

  app:
    build:
      context: .
      dockerfile: Dockerfile
    environment:
      DATABASE_URL: postgres://dalt:dalt-course-local@db:5432/dalt_course
    ports:
      - "127.0.0.1:58000:8000"
    depends_on:
      db:
        condition: service_healthy

volumes:
  db-data:
```

Compose creates a private network for the project and gives every service a DNS name equal to its service key. `db` in that `DATABASE_URL` is not a hostname anyone configured; it is the service name.

## Networks are private roads; ports are gates

Only `app` has a `ports:` entry, and `docker compose ps` shows exactly what that means:

```text
SERVICE   STATUS                   PORTS
app       Up 2 seconds (healthy)   127.0.0.1:58000->8000/tcp
db        Up 6 seconds (healthy)   5432/tcp
```

`db` listens on 5432 *on the private network*. There is no arrow, so nothing on the host is being forwarded to it. The application reaches the database because they share a network, not because the database is exposed. Publishing a database port is something you do deliberately, for a reason, and usually only on `127.0.0.1`.

## Configuration chooses a deployment

The image is the same everywhere; the environment tells it where it is. `DATABASE_URL` is read by our code at runtime, so pointing the same image at a different database is a configuration change, not a rebuild.

Keep the distinction from FS09.3 in mind: a client bundle's configuration is public text baked in at build time. A server container's configuration arrives at run time and can hold a credential — which is why it belongs in the environment and not in a layer.

## Volumes are the only durable part

A container's filesystem dies with it. A named volume does not:

```yaml
volumes:
  - db-data:/var/lib/postgresql
  - ./database:/docker-entrypoint-initdb.d:ro
```

Those two lines are different things. The first is a **named volume** — storage Docker manages, mounted where the database keeps its data. The second is a **bind mount** — a host directory, read-only, holding the SQL that the official image runs the first time the data directory is empty.

Mount the path the image declares, not the one you remember. The PostgreSQL 18 image declares `/var/lib/postgresql`, not the older `…/data` path that most tutorials still show; getting it wrong produces a database that appears to work and quietly loses everything on restart.

## Try it

**Workspace:** copy the Part 10 lab. Docker is required.

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/docker-lab/starter .dalt/workspace/fs10-docker
cd .dalt/workspace/fs10-docker
```

**Starting state:** `compose.yaml` defines `db` and `app`, a named volume, and the seed SQL in `database/`.

```bash
docker compose up -d --wait
docker compose ps
docker compose exec app getent hosts db
docker compose exec app printenv DATABASE_URL
curl http://127.0.0.1:58000/issues
```

**Expected result:** both services report `healthy`, only `app` shows a host port, `getent` resolves `db` to a private address such as `172.22.0.2`, the environment variable is present inside the container, and `/issues` returns the three seeded issues.

Now find out what durability means:

```bash
docker compose exec db psql -U dalt -d dalt_course \
  -c "INSERT INTO issues (title) VALUES ('Survives a restart');"

docker compose down
docker compose up -d --wait
docker compose exec db psql -U dalt -d dalt_course -At -c 'SELECT count(*) FROM issues;'   # 4

docker compose down -v
docker compose up -d --wait
docker compose exec db psql -U dalt -d dalt_course -At -c 'SELECT count(*) FROM issues;'   # 3
```

Four after `down`, three after `down -v`. The containers were destroyed both times; only the second command destroyed the volume, and the seed SQL ran again because the data directory was empty.

**Reset:** `docker compose down -v` removes everything. Delete the workspace copy when finished with Part 10.

## What to notice

`getent hosts db` is the whole networking lesson in one line. No ports were published, no addresses were configured, and one container found another by the name we gave it in a file.

The count going 4 → 4 → 3 is the whole storage lesson. "Restarting the containers" and "deleting the data" are different operations, and the flag between them is one character.

## Common mistakes

- Publishing the database port because a tool on the host needed it once.
- Putting the data volume on a path the image does not actually use.
- Treating `docker compose down -v` as the normal way to restart. It deletes data.
- Baking an environment-specific value into the image instead of passing it in.

## Check your understanding

1. Where does the hostname `db` in `DATABASE_URL` come from?
2. `docker compose ps` shows `5432/tcp` for `db` and `127.0.0.1:58000->8000/tcp` for `app`. What is the difference?
3. Which of the two `volumes:` entries survives `docker compose down`?
4. Why did the seed SQL run again after `down -v` but not after `down`?

<details><summary>Check your answers</summary>

1. From the service key in `compose.yaml`. Compose puts every service on a private network under its own name.
2. `db` listens only on the private network; `app` additionally has a host port forwarded to it.
3. The named volume `db-data`. The bind mount is just a view of a host directory, and the container's own filesystem is discarded.
4. The official image runs the scripts in `/docker-entrypoint-initdb.d` only when the data directory is empty, which is only true after the volume is deleted.
</details>

## Next

Next we will make startup order and health mean something, and look at what a container tells us when it goes wrong.

<details><summary>Maintainer source record</summary>

- Source dossier: Docker documentation research notes and Full Stack Open Containers research notes, sections 41–56.
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: Compose file reference for `services`, `depends_on`, `ports`, `environment`, and `volumes`; Compose networking documentation; the official PostgreSQL image's initialisation-scripts and `PGDATA` documentation.
- Versions: Docker 29.7.2; Docker Compose 5.4.0; `postgres@sha256:9a8afca5…` (PostgreSQL 18.4).
- Consulted: 2026-08-23.
- Curriculum authority: DALT Fullstack theory curriculum, Batch 11, FS10.4.
- DALT files inspected: `docker-lab`, the Part 10 track manifest, and the former FS10.1 page.
- Extracted material: "Compose describes relationships", "persistent data needs an explicit owner", "networks are private roads, published ports are gates", and "configuration chooses a deployment" from the former FS10.1.
- Verified in the lab: every transcript above is real output, including the 4 → 4 → 3 row counts and the PostgreSQL 18 volume path, which is `/var/lib/postgresql` with `PGDATA=/var/lib/postgresql/18/docker`.
</details>
