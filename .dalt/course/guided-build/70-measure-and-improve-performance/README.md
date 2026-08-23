# Measure and improve performance

The temptation at this point is to add indexes, split the bundle, and declare the
application fast. We are going to do the opposite: measure first, and then change only
what the measurements justify. One of the two changes this lesson considers turns out
not to be worth making, and saying so is the point.

> **Helpful background:** PostgreSQL's [Using EXPLAIN](https://www.postgresql.org/docs/18/using-explain.html)
> explains how to read the plans below.

## Build a workload worth measuring

Every query is fast against twelve rows. `scripts/measure-performance.php` seeds a
realistic one into the **test** database — never the one we develop against:

```php
$issueCount = (int) ($argv[1] ?? 20000);
$database = PostgresTestDatabase::fresh();
```

The distribution matters as much as the size:

```php
// Ten projects, not one. With a single project every issue matches the filter, and
// the planner's shortcuts look better than they would in a real workspace.
$projects = [];
for ($n = 1; $n <= 10; $n++) {
    $projects[] = $factory->project($workspace['id'], ["name" => "Project {$n}"]);
}
```

Our first attempt used one project, and the plans looked excellent — because every row
matched. That measurement described a workspace nobody has.

The rows go in with one statement:

```php
// generate_series keeps the seed to one statement; inserting row by row through the
// factory would take minutes and measure PHP rather than PostgreSQL.
$database->query(
    "INSERT INTO issues (project_id, title, description, status, assignee_id, priority, due_date)
     SELECT CAST(:first_project AS bigint) + (n % 10), …
     FROM generate_series(1, CAST(:issue_count AS integer)) AS n",
    …
);
$database->getConnection()->exec('ANALYZE');
```

`ANALYZE` is not optional. The planner chooses using statistics, and statistics from
before a bulk load produce confident, wrong decisions.

## The baseline

**The default project page** — newest ten issues:

```text
Limit (actual rows=10.00 loops=1)
  Buffers: shared hit=4
  ->  Index Scan Backward using issues_pkey on issues (actual rows=10.00 loops=1)
        Filter: (project_id = '1'::bigint)
        Rows Removed by Filter: 81
```

Four buffers. The planner walks the primary key backwards and discards the 81 rows
belonging to other projects before it has ten. At this ratio that is cheaper than
seeking through a project index, and it is the right choice.

**A filtered page** — open and urgent:

```text
Limit (actual rows=0.00 loops=1)
  Buffers: shared hit=371
  ->  Index Scan Backward using issues_pkey on issues (actual rows=0.00 loops=1)
        Filter: ((project_id = '1'::bigint) AND ((status)::text = 'open'::text) AND ((priority)::text = 'urgent'::text))
        Rows Removed by Filter: 20000
```

**Twenty thousand rows examined to return nothing.** This is the shape to look for:
`Rows Removed by Filter` equal to the whole table. The combination genuinely has no
matches in this project, and a filter with no supporting index has to read everything
to find that out.

**A deep page** — page 200:

```text
Limit (actual rows=10.00 loops=1)
  Buffers: shared hit=329
  ->  Sort (actual rows=2000.00 loops=1)
        Sort Key: id DESC
        Sort Method: quicksort  Memory: 189kB
```

Two thousand rows sorted to return ten. `OFFSET` is not free: the database must produce
and discard every skipped row.

**The dashboard:**

```text
Limit (actual rows=20.00 loops=1)
  Buffers: shared hit=15
  ->  Nested Loop
        ->  Index Scan using issues_assignee_open_index on issues (actual rows=20.00)
```

Fifteen buffers. `issues_assignee_open_index` — the partial index from Lesson 48 —
is doing exactly its job. No change needed, and it is worth noticing when a
previous decision holds up.

## Test the obvious index — and read the result honestly

The filtered query suggests a composite index. Add it and measure again rather than
assuming:

```sql
CREATE INDEX issues_project_status_id_index ON issues (project_id, status, id DESC);
```

```text
--- issue list, open + urgent (after) ---
  Buffers: shared hit=315 read=11
  ->  Index Scan using issues_project_status_id_index on issues (actual rows=0.00 loops=1)
        Index Cond: ((project_id = '1'::bigint) AND ((status)::text = 'open'::text))
        Filter: ((priority)::text = 'urgent'::text)
        Rows Removed by Filter: 2000
```

Read that carefully, because it is easy to mistake for a win.

Rows examined fell from **20,000 to 2,000** — a tenfold improvement, and the index is
genuinely being used. But buffers fell only from **371 to 326**, because the remaining
cost is fetching those 2,000 rows from the table to check `priority`, which the index
does not carry.

And the other two queries did not improve at all:

```text
--- issue list, default page (after) ---
  Buffers: shared hit=4          (unchanged — the planner still prefers the primary key)

--- issue list, page 200 (after) ---
  Buffers: shared hit=326        (unchanged — still sorting 2,000 rows)
```

Now the cost side:

```text
issues_project_id_index                  1064 kB
issues_project_status_id_index            808 kB
issues_pkey                               456 kB
```

808 KB, maintained on **every** insert, update, and delete of an issue, forever.

**We are not shipping it.** A 12% reduction in buffers on one filter combination, at
twenty thousand issues, does not pay for another index on the hottest write path in the
application. The measurement was worth taking precisely because it said no.

Record the threshold rather than the decision, so the next person does not have to
rediscover this:

```text
Revisit issues_project_status_id_index when a single project exceeds roughly 100k
issues, or when the filtered query appears in slow-query logs. Re-run:
  php scripts/measure-performance.php 200000 --with-candidate-index
```

The same discipline applies to the two other tempting changes. Full-text search with a
GIN index would beat `ILIKE` — at a corpus where `ILIKE` is actually slow, which ours is
not. Keyset pagination would beat `OFFSET` — at page 200, which nobody has visited. Both
are correct techniques applied to a problem we do not have yet.

## The change the measurement does justify

The frontend bundle is a different story, because its cost is paid by every visitor on
every first load:

```text
public/build/assets/main-Dxok4Xq5.css   21.87 kB │ gzip:   5.19 kB
public/build/assets/main-B6z5D5l1.js   438.21 kB │ gzip: 121.33 kB
```

121 KB gzipped for a React application with a router and a query cache is reasonable.
The risk is not today's number; it is the dependency added in three months that doubles
it and nobody notices. So the useful change is not an optimisation — it is a budget,
added to `scripts/verify-build.php`:

```php
// A budget, set just above what Lesson 70 measured. It is not a target to optimise
// toward; it is a tripwire, so a dependency that doubles the bundle is noticed in the
// pull request that adds it rather than six months later.
const JS_BUDGET_BYTES = 480 * 1024;
const CSS_BUDGET_BYTES = 32 * 1024;
```

```php
if ($size > $budget) {
    $problems[] = sprintf(
        '%s is %d KB, over its %d KB budget. Find out what grew before raising the budget.',
        $file,
        (int) round($size / 1024),
        (int) round($budget / 1024),
    );
}
```

The message says what to do, because the wrong reaction to this failure is to raise the
number.

Prove it bites:

```bash
python3 -c "open('public/build/assets/main-B6z5D5l1.js','a').write('//' + 'x'*60000)"
php scripts/verify-build.php
```

```text
- assets/main-B6z5D5l1.js is 487 KB, over its 480 KB budget. Find out what grew before raising the budget.
```

Rebuild and it passes again. Because `verify-build.php` already runs inside
`ci-gate.sh`, the budget is enforced on every release with no new step.

## Why no lazy loading yet

Route-level code splitting is the standard next move, and our measurements do not
support it. The application loads one bundle and every screen a signed-in person uses
comes from it; splitting would trade a single 121 KB download for several smaller ones
plus a request on each navigation. The budget above is what will tell us when that
trade starts to make sense.

## Run the gate

```bash
./scripts/ci-gate.sh
```

```text
Tests:  1 skipped, 345 passed (1077 assertions)
Tests   47 passed (47)
12 passed (33.1s)
All release checks passed.
```

We now have a baseline anyone can reproduce with one command, a written record of which
optimisations were considered and rejected with the numbers that rejected them, and a
budget that will notice the next regression. The final lesson rehearses a complete
release and recovery from a clean checkout.
