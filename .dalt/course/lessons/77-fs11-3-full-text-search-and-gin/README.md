# FS11.3 — Full-text search and GIN

Lesson ID: FS11.3
Lesson format: Concise theory
Part: 11 — PostgreSQL deeper
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Applied
Prerequisites: FS11.2
Last reviewed: 2026-08-23

We will search the words in an issue rather than the characters, and index those words so the search stays fast.

> **Helpful background:** [`EXPLAIN`, composite indexes, and partial indexes](/learn/lessons/76-fs11-2-explain-composite-and-partial-indexes)

## What we will learn

- see why `LIKE '%term%'` is the wrong tool for searching prose;
- turn text into a searchable document with `to_tsvector`;
- index that document with GIN, and rank the matches.

## `LIKE` searches characters, not language

`WHERE body LIKE '%deadlock%'` has two problems, and only one of them is speed.

The speed problem is that a leading `%` makes a B-tree index useless — there is no prefix to seek to — so every row is read:

```text
 Parallel Seq Scan on issues (actual rows=13333.33 loops=3)
   Filter: (body ~~ '%deadlock%'::text)
   Buffers: shared hit=3602
```

The worse problem is that it does not know what a word is:

```text
 like_deploys | 0
 like_deploy  | 66666
```

Sixty-six thousand issues mention deploying. Searching for "deploys" finds none of them. `LIKE` also happily matches inside other words, so searching "cat" finds "catalogue".

## A document is words reduced to stems

PostgreSQL's answer is to convert text into a `tsvector`: a normalised list of stems with positions, built by a language configuration that knows about stemming and stop words.

```sql
SELECT to_tsvector('english', 'The export job reported a deadlock after the deploys.');
```

```text
 'deadlock':6 'deploy':9 'export':2 'job':3 'report':4
```

"The", "a", and "after" are gone — they carry no meaning for search. "reported" became `report` and "deploys" became `deploy`. Queries go through the same reduction with `plainto_tsquery`, so both sides meet in the middle, and one query form finds every form of the word.

The match operator is `@@`:

```sql
SELECT count(*) FROM issues
WHERE search_document @@ plainto_tsquery('english', 'deadlock export');
```

## Store the document, then index it

Computing `to_tsvector` for every row on every search is still a full scan. Store it once as a generated column, so PostgreSQL keeps it in step with the source text automatically:

```sql
ALTER TABLE issues
    ADD COLUMN search_document tsvector
    GENERATED ALWAYS AS (
        to_tsvector('english', title || ' ' || body)
    ) STORED;
```

Then index it with **GIN** — an inverted index, which maps each stem to the rows containing it. That is the right structure here: a B-tree indexes one value per row, while a document has many.

```sql
CREATE INDEX issues_search_idx ON issues USING GIN (search_document);
```

The plan changes shape:

```text
 Bitmap Heap Scan on issues (actual rows=5714.00 loops=1)
   Recheck Cond: (search_document @@ '''deadlock'' & ''export'''::tsquery)
   ->  Bitmap Index Scan on issues_search_idx (actual rows=5714.00 loops=1)
         Buffers: shared hit=24
```

Read that honestly. The index found all 5,714 matching rows in **24 buffers** — the scan needed thousands. The remaining cost is fetching those 5,714 rows from the table, which no index removes. This is why a real search screen has a `LIMIT`: the expensive part is the rows you return, not finding them.

## Rank, do not just filter

Matching is a yes/no answer. People expect an order:

```sql
SELECT id, round(ts_rank(search_document, query)::numeric, 4) AS rank
FROM issues, plainto_tsquery('english', 'deadlock export') AS query
WHERE search_document @@ query
ORDER BY rank DESC, id
LIMIT 5;
```

`ts_rank` scores a document against a query using term frequency and position. Two notes matter in practice. It is computed *after* matching, so it cannot use the index — always pair it with a `LIMIT`. And a second sort key (`id` above) makes the order deterministic when scores tie, which they often do.

## Try it

**Workspace:** continue in `.dalt/workspace/fs11-postgres`. If starting fresh, run the setup in FS11.1, then `fs11-1` and `fs11-2` in order.

```bash
cd .dalt/workspace/fs11-postgres
docker compose exec -T db psql -U dalt -d dalt_depth -v ON_ERROR_STOP=1 \
  -f /course/sql/fs11-3-full-text-search.sql
```

**Expected result:** seven labelled sections. `LIKE '%deadlock%'` is a parallel sequential scan; `'%deploys%'` matches 0 rows while `'%deploy%'` matches 66,666. The `to_tsvector` output is the five stems above. Section 4 answers the text-search question with a sequential scan; section 5 adds the GIN index and the plan becomes a bitmap index scan whose index step reads 24 buffers. Searching `deploys` through the document matches 66,666 rows, and the ranked query returns five ids with a rank of `0.1844`.

Now change section 7's query to `plainto_tsquery('english', 'catalogue')`. It matches nothing — while `LIKE '%cat%'` would have matched every "catalogue" *and* every "category".

**Reset:** `docker compose down -v`, or `DROP INDEX issues_search_idx;` and
`ALTER TABLE issues DROP COLUMN search_document;`.

## What to notice

Section 2 is the argument. Zero versus sixty-six thousand is not a performance difference; it is a correctness one. A search that cannot find "deploys" in a corpus about deploying is broken however fast it runs.

Section 5's 24 buffers, next to section 4's thousands, is the performance difference — and the fact that the totals stay close is worth keeping. An index makes *finding* cheap; returning ten thousand rows is expensive no matter how they were found.

## Common mistakes

- Shipping `LIKE '%term%'` as search and calling the missing results an edge case.
- Recomputing `to_tsvector` in the `WHERE` clause instead of storing it.
- Using a B-tree index on a `tsvector` column, which cannot answer `@@`.
- `ORDER BY ts_rank(...)` with no `LIMIT`, which ranks the entire match set.
- Using different text-search configurations for the document and the query.

## Check your understanding

1. Give two reasons `LIKE '%deploy%'` is a poor search implementation.
2. What does `to_tsvector('english', …)` remove, and what does it change?
3. Why GIN rather than B-tree for a `tsvector` column?
4. Why must a `ts_rank` query have a `LIMIT`?

<details><summary>Check your answers</summary>

1. A leading `%` prevents index use, and character matching has no idea what a word is — it misses "deploys" and matches inside unrelated words.
2. It removes stop words and reduces the rest to stems, keeping their positions.
3. A B-tree indexes one value per row; a document contains many terms, and GIN is an inverted index built for exactly that.
4. Ranking happens after matching and cannot use the index, so without a `LIMIT` every matching row is scored.
</details>

## Next

Next we will look at JSONB, and where the flexible column should stop.

<details><summary>Maintainer source record</summary>

- Source dossier: PostgreSQL documentation research notes.
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: PostgreSQL 18 documentation on text search types, controlling text search, text-search indexes (GIN and GiST), `ts_rank`, generated columns, and pattern matching.
- Versions: PostgreSQL 18.4 (`postgres@sha256:9a8afca5…`).
- Consulted: 2026-08-23.
- Curriculum authority: DALT Fullstack theory curriculum, Batch 12, FS11.3.
- DALT files inspected: `postgres-depth-lab`, the Part 11 track manifest, and the former FS11.1 page.
- Extracted material: "search is language-aware matching" and "keep search documents current and inspect their semantics" from the former FS11.1.
- Verified in the lab: every count, plan, and rank above is real output. The seed was diversified during this batch so the search terms co-occur realistically rather than always in one fixed sentence.
</details>
