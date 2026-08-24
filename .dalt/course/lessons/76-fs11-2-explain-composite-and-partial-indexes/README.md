# FS11.2 — `EXPLAIN`, composite indexes, and partial indexes

Lesson ID: FS11.2
Lesson format: Concise theory
Part: 11 — PostgreSQL deeper
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Applied
Prerequisites: FS11.1
Last reviewed: 2026-08-23

We will read a query plan closely enough to see the work it is doing, then design an index for the whole query — its filters *and* its ordering.

> **Helpful background:** [Data scale, indexes, and selectivity](/learn/lessons/52-fs11-1-query-performance-and-postgresql-capabilities)

## What we will learn

- read the parts of a plan that describe work rather than guesses;
- order the columns of a composite index by how the query uses them;
- use a partial index when only a slice of the table is ever queried.

## Read the plan from the inside out

A plan is a tree, and it executes from the innermost node outwards. Four things in it are worth reading every time:

```text
the node type      Seq Scan, Index Scan, Sort, Limit — what work is being done
actual rows        how many rows really came out of that node
Buffers            how many 8kB pages were touched — the honest unit of work
Sort Method        present only when PostgreSQL had to sort, which is work an index can remove
```

Here is a query a real issue list makes — one workspace, open issues, newest first:

```sql
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF, TIMING OFF, SUMMARY OFF)
SELECT id, title, created_at
FROM issues
WHERE workspace_id = 1 AND status = 'open'
ORDER BY created_at DESC
LIMIT 20;
```

```text
 Limit (actual rows=20.00 loops=1)
   Buffers: shared hit=3644
   ->  Sort (actual rows=20.00 loops=1)
         Sort Key: created_at DESC
         Sort Method: top-N heapsort  Memory: 26kB
         ->  Index Scan using issues_status_idx on issues (actual rows=10000.00 loops=1)
               Index Cond: (status = 'open'::text)
               Filter: (workspace_id = 1)
```

Twenty rows were asked for. Ten thousand were read, filtered, and sorted to produce them, across 3,644 buffers. The `Index Cond` used the index; the `Filter` did not — `workspace_id` was checked row by row after the fact.

## Column order is the whole design

The obvious reaction is "add an index on status and workspace_id". Try it in that order and the plan does not change at all — the planner does not even choose the new index. Column order in a composite index is not a detail; it decides what the index can answer.

An index is sorted by its first column, then its second within that, and so on. So the order to use is:

```text
1. columns compared with =        (workspace_id, status)
2. the column you ORDER BY        (created_at DESC)
3. columns only used for ranges   (last, if at all)
```

Shaped that way, the index can satisfy the filters *and* hand back rows already in the required order:

```sql
CREATE INDEX issues_workspace_status_created_idx
    ON issues (workspace_id, status, created_at DESC);
```

```text
 Limit (actual rows=20.00 loops=1)
   Buffers: shared hit=11 read=3
   ->  Index Scan using issues_workspace_status_created_idx on issues (actual rows=20.00 loops=1)
         Index Cond: ((workspace_id = 1) AND (status = 'open'::text))
```

The `Sort` node is gone, `actual rows` at the scan is 20 instead of 10,000, and the buffer count fell from 3,644 to 14. That is what "an index for the query shape" means, and it is also why `LIMIT` and `ORDER BY` belong in the index design rather than being treated as afterthoughts.

## A partial index indexes less

If a query only ever asks for open issues, an index over all 200,000 rows is mostly storage for rows it will never return. A partial index has a `WHERE` clause of its own:

```sql
CREATE INDEX issues_open_recent_idx
    ON issues (workspace_id, created_at DESC)
    WHERE status = 'open';
```

Because every row in it is already open, `status` does not need to be a column — it is the index's precondition. The plan gets slightly cheaper, and the index gets dramatically smaller:

```text
 issues_workspace_status_created_idx | 7960 kB
 issues_pkey                         | 4408 kB
 issues_status_workspace_idx         | 1400 kB
 issues_status_idx                   | 1368 kB
 issues_open_recent_idx              |  328 kB
```

Twenty-four times smaller than the full composite index, for the query we actually run. Smaller means less to keep in memory and less to maintain on every write.

The trade is exactness: PostgreSQL uses a partial index only when it can prove the query's conditions imply the index's `WHERE`. Ask for `status = 'closed'`, or for all statuses, and this index is unusable.

## Try it

**Workspace:** continue in the Part 11 lab, or set it up:

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/postgres-depth-lab/starter .dalt/workspace/fs11-postgres
cd .dalt/workspace/fs11-postgres
docker compose up -d --wait
docker compose exec -T db psql -U dalt -d dalt_depth -v ON_ERROR_STOP=1 -q \
  -f /course/database/001_schema.sql
docker compose exec -T db psql -U dalt -d dalt_depth -v ON_ERROR_STOP=1 -q \
  -f /course/database/002_seed.sql
docker compose exec -T db psql -U dalt -d dalt_depth -v ON_ERROR_STOP=1 -q \
  -f /course/sql/fs11-1-selectivity.sql
```

**Starting state:** the seeded workload, with FS11.1's single-column index on `status` already present.

```bash
docker compose exec -T db psql -U dalt -d dalt_depth -v ON_ERROR_STOP=1 \
  -f /course/sql/fs11-2-explain-and-indexes.sql
```

**Expected result:** five labelled sections. Section 1 shows a `Sort` above an index scan returning 10,000 rows and about 3,644 buffers. Section 2 adds `(status, workspace_id)` and the plan is unchanged. Section 3 adds `(workspace_id, status, created_at DESC)`, the `Sort` disappears, and buffers drop to about 14. Section 4's partial index is chosen instead. Section 5 lists index sizes, with the partial index at about 328 kB against the composite index's 7,960 kB.

Now add `AND priority = 1` to section 4's query and rerun. The partial index is still used, with `priority` appearing as a `Filter` — the index narrows, then the scan checks the rest.

**Reset:** `docker compose down -v`, or drop the four indexes by name.

## What to notice

Section 2 is the point of the lesson. Adding an index changed nothing, because the index could not answer the question the query was asking. "There is an index on that column" and "the query is indexed" are different statements.

Section 3's disappearing `Sort` is the second point. An index is an ordering, so an index in the right order can pay for the `ORDER BY` as well as the `WHERE`.

## Common mistakes

- Reading `EXPLAIN` without `ANALYZE`, and taking estimated rows for real ones.
- Putting the `ORDER BY` column before the equality columns.
- Creating one index per column instead of one index per query shape.
- A partial index whose `WHERE` does not match how the application actually queries, so nothing can use it.

## Check your understanding

1. What is the difference between an `Index Cond` and a `Filter` in a plan?
2. Why did the `(status, workspace_id)` index change nothing?
3. How can an index remove a `Sort` node?
4. When is a partial index unusable?

<details><summary>Check your answers</summary>

1. `Index Cond` is applied by the index and limits what is read; `Filter` is applied to rows after they are read, so that work still happened.
2. It answers the same question as the existing `status` index and still leaves `created_at` unordered, so the planner had no reason to prefer it.
3. An index is stored in order, so if its ordering matches the `ORDER BY`, rows arrive already sorted.
4. When the query's conditions do not imply the index's `WHERE` — asking for closed issues, or for all statuses.
</details>

## Next

Next we will search the text of an issue, which no `LIKE` pattern does well.

<details><summary>Maintainer source record</summary>

- Source dossier: PostgreSQL documentation research notes.
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: PostgreSQL 18 documentation on "Using EXPLAIN", multicolumn indexes, indexes and `ORDER BY`, partial indexes, and `pg_stat_user_indexes`.
- Versions: PostgreSQL 18.4 (`postgres@sha256:9a8afca5…`).
- Consulted: 2026-08-23.
- Curriculum authority: DALT Fullstack theory curriculum, Batch 12, FS11.2.
- DALT files inspected: `postgres-depth-lab`, the Part 11 track manifest, and the former FS11.1 page.
- Extracted material: "capture a baseline plan", "design an index for the query shape", "read estimates, buffers, and scan choices together", and "pagination needs an order the index can serve" from the former FS11.1.
- Verified in the lab: every plan and every index size above is real output. The wrong-order index is genuinely not chosen — the planner keeps using FS11.1's `status` index.
</details>
