# Part 11 lab — PostgreSQL deeper

A PostgreSQL 18 database seeded with two hundred thousand issues, so the planner's
decisions are visible instead of theoretical. It grows across Part 11:

- `database/001_schema.sql` and `database/002_seed.sql` build the workload;
- `sql/fs11-*.sql` hold one focused experiment per lesson.

The database listens on `127.0.0.1:55433` and is pinned by digest. It is separate from
the Part 05 lab, so neither can disturb the other.

## Set up

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/postgres-depth-lab/starter .dalt/workspace/fs11-postgres
cd .dalt/workspace/fs11-postgres
docker compose up -d --wait
docker compose exec -T db psql -U dalt -d dalt_depth -v ON_ERROR_STOP=1 -q \
  -f /course/database/001_schema.sql
docker compose exec -T db psql -U dalt -d dalt_depth -v ON_ERROR_STOP=1 -q \
  -f /course/database/002_seed.sql
```

Seeding takes about seven seconds and produces 190,000 closed and 10,000 open issues.

## Run each lesson's experiment, in order

```bash
docker compose exec -T db psql -U dalt -d dalt_depth -v ON_ERROR_STOP=1 \
  -f /course/sql/fs11-1-selectivity.sql
docker compose exec -T db psql -U dalt -d dalt_depth -v ON_ERROR_STOP=1 \
  -f /course/sql/fs11-2-explain-and-indexes.sql
docker compose exec -T db psql -U dalt -d dalt_depth -v ON_ERROR_STOP=1 \
  -f /course/sql/fs11-3-full-text-search.sql
docker compose exec -T db psql -U dalt -d dalt_depth -v ON_ERROR_STOP=1 \
  -f /course/sql/fs11-4-jsonb.sql
```

FS11.5 runs from the host instead, because it needs two independent connections:

```bash
DALT_REPOSITORY_ROOT=/path/to/DALT.PHP php scripts/concurrency.php
```

FS11.6 needs only the schema and seed, so it can run against a freshly set-up database:

```bash
docker compose exec -T db psql -U dalt -d dalt_depth -v ON_ERROR_STOP=1 \
  -f /course/sql/fs11-6-row-level-security.sql
```

It prints two deliberate errors — a refused cross-tenant insert and a bare `''::bigint`
cast. Both are the evidence, not a broken script.

They build on each other: FS11.2 starts from the index FS11.1 created, so run them in
sequence against a freshly seeded database.

## Reset

```bash
docker compose down -v
```

That deletes the container and its data. Repeat the setup for a clean workload.
