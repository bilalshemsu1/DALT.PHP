# FS11.1 — Data scale, indexes, and selectivity

Lesson ID: FS11.1
Lesson format: Concise theory
Part: 11 — PostgreSQL deeper
Status: Published
Estimated effort: 30–40 minutes
Difficulty: Applied
Prerequisites: FS10.5
Last reviewed: 2026-08-23

We will find out why the same index is used for one value and ignored for another, by measuring it rather than guessing.

> **Helpful background:** [Migrations, constraints, and foundational indexes](/learn/lessons/66-fs05-5-migrations-constraints-and-indexes)

## What we will learn

- build a workload large enough for the planner's decisions to be visible;
- read a plan well enough to tell a table scan from an index scan;
- explain selectivity, and why it decides whether an index is worth using.

## Small data hides everything

Every query is fast against two hundred rows. PostgreSQL will happily read the whole table, because reading the whole table costs almost nothing. Nothing you learn at that size transfers.

So the first step in any performance question is a **representative workload**: enough rows, and a realistic distribution of values. Our lab seeds two hundred thousand issues where 5% are open and 95% are closed — a shape most issue trackers actually have.

```sql
SELECT status, count(*) FROM issues GROUP BY status ORDER BY status;
```

```text
 status | count
--------+--------
 closed | 190000
 open   |  10000
```

That distribution is not decoration. It is the input to every decision the planner makes next.

## An index is not a guarantee

Ask for the open issues with no index at all, and PostgreSQL reads every row:

```sql
EXPLAIN (ANALYZE, COSTS OFF, TIMING OFF, SUMMARY OFF)
SELECT id, title FROM issues WHERE status = 'open';
```

```text
 Seq Scan on issues (actual rows=10000.00 loops=1)
   Filter: (status = 'open'::text)
   Rows Removed by Filter: 190000
   Buffers: shared hit=3631
```

`Rows Removed by Filter: 190000` is the cost, in plain sight: 95% of the work was thrown away.

Add an index and ask again:

```sql
CREATE INDEX issues_status_idx ON issues (status);
ANALYZE issues;
```

```text
 Index Scan using issues_status_idx on issues (actual rows=10000.00 loops=1)
   Index Cond: (status = 'open'::text)
```

Now ask for the *closed* issues — the same table, the same column, the same index:

```text
 Seq Scan on issues (actual rows=190000.00 loops=1)
   Filter: (status = 'closed'::text)
   Rows Removed by Filter: 10000
```

The planner declined its own index. That is not a bug, and it is the most useful thing in this lesson.

## Selectivity is the reason

**Selectivity** is the fraction of the table a condition matches. An index scan reads the index and then fetches each matching row from the table — cheap when there are few matches, and steadily worse as the count grows, because those fetches jump around the table in index order.

Past roughly a fifth of the table, reading everything in physical order is simply faster. `status = 'closed'` matches 95%, so a scan wins.

That is why "add an index on the column in the WHERE clause" is not advice. An index helps a condition that selects a small share of the rows. A primary-key lookup is the extreme case:

```text
 Index Scan using issues_pkey on issues (actual rows=1.00 loops=1)
   Index Cond: (id = 123456)
   Buffers: shared hit=7
```

Seven buffers instead of 3,631, for one row instead of ten thousand.

## Indexes are not free

Every index is a second structure that every `INSERT`, `UPDATE`, and `DELETE` must maintain, and disk it must occupy. An index that is never chosen is pure cost. Two rules follow:

- add an index for a query shape you have actually measured, not for a column that looks important;
- run `ANALYZE` after a large data change, because the planner chooses using statistics, and stale statistics produce confident wrong decisions.

## Try it

**Workspace:** copy the Part 11 lab. Docker is required.

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

**Starting state:** an empty schema, then two hundred thousand issues across two workspaces and ten projects, seeded in about seven seconds.

```bash
docker compose exec -T db psql -U dalt -d dalt_depth -v ON_ERROR_STOP=1 \
  -f /course/sql/fs11-1-selectivity.sql
```

**Expected result:** five labelled sections. The distribution is 190,000 closed and 10,000 open. Before the index, the open query is a `Seq Scan` with `Rows Removed by Filter: 190000`. After the index, the open query becomes an `Index Scan`, the closed query stays a `Seq Scan`, and the primary-key lookup reads 7 buffers instead of 3,631.

Now change section 4 to `WHERE status = 'open' AND priority = 1` and rerun. The plan still uses the index and then filters — which is the question FS11.2 answers.

**Reset:** `docker compose down -v` deletes the database. Delete the workspace copy when finished with Part 11.

## What to notice

Sections 3 and 4 differ by one literal. Same table, same index, same statistics — and two different plans. Whatever intuition says an index "makes queries fast", the planner is doing arithmetic with the numbers from section 1.

Notice also that `Buffers` is the honest unit. Wall-clock time on a laptop is noise; buffer counts are the work.

## Common mistakes

- Testing performance against a few hundred rows.
- Adding an index because a column appears in a `WHERE` clause.
- Reading a `Seq Scan` as a failure. On a low-selectivity condition it is the right plan.
- Forgetting `ANALYZE` after bulk-loading, then debugging a plan built from stale statistics.

## Check your understanding

1. What does selectivity mean, and why does the planner care?
2. Why is `Rows Removed by Filter: 190000` a useful number?
3. Why did the same index help `status = 'open'` and not `status = 'closed'`?
4. Name two ongoing costs of an index nobody's queries use.

<details><summary>Check your answers</summary>

1. The fraction of rows a condition matches. An index scan pays per matching row, so it wins only when that fraction is small.
2. It is the work that was done and thrown away — the clearest signal that a scan read far more than the query needed.
3. `open` matches 5% of the table, `closed` matches 95%. Above roughly a fifth, reading the table in physical order is cheaper.
4. Extra work on every write that touches the table, and the disk it permanently occupies.
</details>

## Next

Next we will read plans properly, and design indexes for the shape of a query rather than for one column.

<details><summary>Maintainer source record</summary>

- Source dossier: `POSTGRESQL_DOCS.md` and `FSO_RELATIONAL_DATABASES.md`.
- Official sources: PostgreSQL 18 documentation on "Using EXPLAIN", planner cost estimation, index types and their uses, `ANALYZE`, and row-estimation examples.
- Versions: PostgreSQL 18.4 (`postgres@sha256:9a8afca5…`); Docker Compose 5.4.0.
- Consulted: 2026-08-23.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 12, FS11.1.
- DALT files inspected: the new `postgres-depth-lab`, the Part 11 track manifest, and the former FS11.1 page.
- Extracted material: "make the workload representative", "capture a baseline plan", and "design an index for the query shape" from the former FS11.1. Its `EXPLAIN` detail, search, and JSONB material move to FS11.2–FS11.4.
- Verified in the lab: every plan fragment above is real output from the seeded 200,000-row table.
</details>
