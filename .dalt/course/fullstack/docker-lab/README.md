# Part 10 lab — Docker

A very small PHP service used to observe processes, images, layers, Compose, and
health. It grows across Part 10:

- `public/index.php` answers `/health`, `/ready`, `/issues`, and `/whoami`;
- `Dockerfile.first` is the smallest thing that produces a running container;
- `Dockerfile.single` adds `pdo_pgsql` in one stage;
- `Dockerfile` splits build from runtime and drops privileges;
- `compose.yaml` runs the service with PostgreSQL, a named volume, and health gates.

The published port is `127.0.0.1:58000`. The database is not published at all: the
application reaches it over the project's private network.

`/health` deliberately answers whenever the process is alive. `/ready` deliberately
checks the database. FS10.5 is built on the difference.

Every image is pinned by digest. The published port is bound to `127.0.0.1` only.

## Set up

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/docker-lab/starter .dalt/workspace/fs10-docker
cd .dalt/workspace/fs10-docker
```

Docker is required. Nothing here installs npm packages.

## Check each completed slice

```bash
docker build -f Dockerfile.first -t dalt-docker-lab:first .   # FS10.2
docker build -f Dockerfile.single -t dalt-docker-lab:single . # FS10.3
docker build -t dalt-docker-lab:multi .                       # FS10.3
docker compose up -d --wait                                   # FS10.4, FS10.5
curl http://127.0.0.1:58000/issues                            # three seeded issues
docker compose stop db && sleep 12
docker compose ps                                             # app: unhealthy
curl http://127.0.0.1:58000/health                            # still {"status":"ok"}
docker compose down -v
```

## Prove the evidence can fail honestly

```text
delete .dockerignore and rebuild            → config.local.php ships in the image
move COPY . . above the RUN apt-get block   → every source edit reinstalls the extension
point the app health check at /health       → the stack stays green through an outage
```

The course test performs the first and the third automatically and requires both to
behave as described.

## Reset

`docker compose down -v` removes the containers and the database volume. Delete the
workspace copy to start over. Remove the images with
`docker image rm dalt-docker-lab:first dalt-docker-lab:single dalt-docker-lab:multi`.
