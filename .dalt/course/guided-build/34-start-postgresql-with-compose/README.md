Our issue tracker has outgrown the single SQLite file we started with. Before we ask
DALT to use PostgreSQL, we will give PostgreSQL its own repeatable development home.
PHP and Vite will still run on our computer for now; this lesson adds only the database
service.

## Describe the database service

Create `compose.yaml` in the project root:

```yaml
services:
  db:
    image: postgres:18-alpine
    environment:
      POSTGRES_DB: ${POSTGRES_DB:-dalt_issue_tracker}
      POSTGRES_USER: ${POSTGRES_USER:-dalt}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD:-dalt_local_password}
    ports:
      - "127.0.0.1:${POSTGRES_PORT:-5432}:5432"
    volumes:
      - postgres_data:/var/lib/postgresql
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U $${POSTGRES_USER} -d $${POSTGRES_DB}"]
      interval: 2s
      timeout: 5s
      retries: 10
      start_period: 5s

volumes:
  postgres_data:
```

Compose turns this description into a running container. The image supplies
PostgreSQL 18; `db` is the service name we will use again when the PHP application is
containerized later.

The port has two sides:

```text
127.0.0.1:${POSTGRES_PORT:-5432} : 5432
             host computer          container
```

Binding the host side to `127.0.0.1` keeps this development database off the wider
network. If 5432 is already occupied, set `POSTGRES_PORT=5433` in `.env`; PostgreSQL
inside the container still listens on 5432.

PostgreSQL 18 stores its versioned data directory below `/var/lib/postgresql`, so that
is where we mount the named volume. Older examples often mount
`/var/lib/postgresql/data`; do not copy that path into this PostgreSQL 18 service.

## Keep local values configurable

Add the development defaults to `.env.example`, below the session settings:

```dotenv
# Docker Compose PostgreSQL service
POSTGRES_PORT=5432
POSTGRES_DB=dalt_issue_tracker
POSTGRES_USER=dalt
POSTGRES_PASSWORD=dalt_local_password
```

Copy the same values into the ignored `.env`. The example makes the required names
discoverable; `.env` is the local value Compose actually reads. These are deliberately
low-value development credentials, not production secrets.

Ask Compose to resolve the file before it starts anything:

```bash
docker compose config
```

The output should contain one `db` service, the resolved port and environment values,
and a `postgres_data` volume. This catches indentation and interpolation mistakes
without creating a container.

## Start PostgreSQL and wait for readiness

Start only the database:

```bash
docker compose up -d db
docker compose ps
```

`-d` leaves it running in the background. At first the status may say
`health: starting`; initialization is real work, so “the container process exists” is
not the same as “the database accepts queries.” Run `docker compose ps` again until it
reports `healthy`.

The health check uses `pg_isready` inside the image. We can run the same proof
directly:

```bash
docker compose exec -T db \
  pg_isready -U dalt -d dalt_issue_tracker
```

It should end with `accepting connections`. `exec` runs a command in the existing
container; it does not install PostgreSQL on our computer.

Now ask the server itself which database it opened:

```bash
docker compose exec -T db \
  psql -U dalt -d dalt_issue_tracker \
  -c 'SELECT current_database(), current_user;'
```

The row should name `dalt_issue_tracker` and `dalt`. DALT is not using it yet—that is
the next lesson—but the service is no longer an assumption.

## Prove the volume survives the container

A container is replaceable. Our development rows should not disappear when Compose
recreates it, so create one small probe:

```bash
docker compose exec -T db \
  psql -U dalt -d dalt_issue_tracker \
  -c "CREATE TABLE lesson_34_probe (message text NOT NULL);" \
  -c "INSERT INTO lesson_34_probe VALUES ('volume survives');"
```

Remove the container and network, but leave the named volume:

```bash
docker compose down
docker compose up -d db
```

Wait for `healthy`, then read the row:

```bash
docker compose exec -T db \
  psql -U dalt -d dalt_issue_tracker \
  -Atc 'SELECT message FROM lesson_34_probe;'
```

The output is `volume survives`. `docker compose down` removed the disposable
container, and the next `up` attached a new container to the same named volume.

There is also a destructive reset:

```bash
docker compose down --volumes
```

That removes this Compose project's named database volume. Do not run it casually;
we will use it deliberately when an empty PostgreSQL database is required. Leave the
service running now.

```bash
git add compose.yaml .env.example
git commit -m "Start PostgreSQL with Compose"
```

We now have a healthy, persistent PostgreSQL service and have not changed the working
SQLite application. Next we will configure DALT through environment variables and
prove which driver it really connects to.
