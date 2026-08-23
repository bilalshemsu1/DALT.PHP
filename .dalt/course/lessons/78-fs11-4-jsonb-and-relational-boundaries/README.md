# FS11.4 — JSONB and the relational boundary

Lesson ID: FS11.4
Lesson format: Concise theory
Part: 11 — PostgreSQL deeper
Status: Published
Estimated effort: 30–40 minutes
Difficulty: Applied
Prerequisites: FS11.3
Last reviewed: 2026-08-23

We will store genuinely variable data in a `jsonb` column, index it properly, and be specific about what that choice gives up.

> **Helpful background:** [Relational modeling with keys and relationships](/learn/lessons/37-fs05-2-relational-modeling-and-migrations)

## What we will learn

- query JSONB with containment rather than key extraction;
- index it with GIN, and see which operator the index can actually answer;
- recognise when a key has outgrown JSON and belongs in a column.

## What JSONB is for

`jsonb` stores a parsed, binary JSON document. It is the right tool when the shape genuinely varies per row and the application does not know the keys in advance — integration metadata, per-customer custom fields, a captured webhook payload.

It is the wrong tool for data that has a fixed shape and is queried constantly. Every issue has a status; putting it in JSON buys nothing and costs everything below.

```sql
ALTER TABLE issues ADD COLUMN attributes jsonb NOT NULL DEFAULT '{}'::jsonb;
```

```text
 {"tags": ["billing"], "source": "email", "browser": "chrome"}
```

## Containment is the indexable question

There are two ways to ask whether an issue came from the API:

```sql
WHERE attributes ->> 'source' = 'api'      -- extract the key, compare as text
WHERE attributes @> '{"source": "api"}'    -- does the document contain this?
```

They return the same rows. Only the second can use a GIN index:

```sql
CREATE INDEX issues_attributes_idx ON issues USING GIN (attributes jsonb_path_ops);
```

```text
 Bitmap Heap Scan on issues (actual rows=66667.00 loops=1)
   Recheck Cond: (attributes @> '{"source": "api"}'::jsonb)
   ->  Bitmap Index Scan on issues_attributes_idx (actual rows=66667.00 loops=1)
         Buffers: shared hit=13
```

Run the `->>` version again with the index in place, and it is still a parallel sequential scan. The index stores which documents contain which key/value paths; it knows nothing about the result of extracting a key and casting it to text.

`jsonb_path_ops` is a smaller, faster GIN variant that only supports containment. The default operator class also supports key-existence questions such as `attributes ? 'source'`. Choose the one that matches how you query.

## What you give up

The database enforces nothing inside a JSON document. This update succeeds:

```sql
UPDATE issues SET attributes = attributes || '{"sorce": "wbe"}'::jsonb WHERE id = 2;
```

```text
 {"tags": ["infra"], "sorce": "wbe", "source": "api", "browser": "safari"}
 rows_with_a_misspelled_key | 1
```

A misspelled key with a misspelled value is now permanently in the table, and nothing objected. Compare that with the guarantees a column has:

```text
column                          jsonb key
NOT NULL                        no such thing — a key can just be absent
CHECK (status IN (…))           no constraint on a value inside the document
REFERENCES projects (id)        no foreign key to a key inside JSON
one declared type               any JSON type, per row
appears in the schema           discoverable only by reading rows
```

That is the trade. JSONB buys flexibility by giving up every guarantee that made the relational model worth using.

## Promote a key when it stops varying

The moment a key is present on every row and queried by every screen, it is not variable data any more. Give it a column. A generated column does this without a migration of the application's writes:

```sql
ALTER TABLE issues ADD COLUMN source text
    GENERATED ALWAYS AS (attributes ->> 'source') STORED;

CREATE INDEX issues_source_idx ON issues (source) WHERE source IS NOT NULL;
```

```text
 Bitmap Index Scan on issues_source_idx (actual rows=66667.00 loops=1)
   Index Cond: (source = 'api'::text)
```

Now `source` is a typed, indexable, discoverable column that can carry a `CHECK`, while `attributes` keeps holding the keys that really do vary.

## Try it

**Workspace:** continue in `.dalt/workspace/fs11-postgres`. If starting fresh, run the setup in FS11.1, then `fs11-1` through `fs11-3` in order.

```bash
cd .dalt/workspace/fs11-postgres
docker compose exec -T db psql -U dalt -d dalt_depth -v ON_ERROR_STOP=1 \
  -f /course/sql/fs11-4-jsonb.sql
```

**Expected result:** six labelled sections. Section 1 shows one issue's attributes. Section 2 is a parallel sequential scan. Section 3 becomes a bitmap index scan whose index step reads about 13 buffers. Section 4 runs the `->>` form again *with the index present* and is a sequential scan again. Section 5 accepts `"sorce": "wbe"` and reports one row with a misspelled key. Section 6's generated column is served by its own index.

Now try `CREATE INDEX ON issues ((attributes ->> 'source'));` — an expression index — and rerun section 4. It becomes an index scan, because that index stores exactly the expression the query uses.

**Reset:** `docker compose down -v`, or drop the two indexes and the two added columns.

## What to notice

Sections 3 and 4 are the same question, the same data, and the same index. Only the operator differs, and only one of them can be answered by the index. "The column is indexed" is not a property of a column; it is a property of a query.

Section 5 is the one to sit with. Nothing failed. There was no error to catch, no constraint to violate, and no schema to consult later to discover that the key exists.

## Common mistakes

- Reaching for JSONB because the shape is not decided yet, then never deciding.
- Querying with `->>` and expecting a GIN index to help.
- Storing a foreign key inside JSON, where nothing can enforce it.
- Keeping a universal key in JSON long after it stopped varying.

## Check your understanding

1. Why can a GIN index answer `@>` but not `->> … = …`?
2. What does `jsonb_path_ops` trade away, and for what?
3. Name three guarantees a column has that a JSON key does not.
4. When has a key outgrown the JSON document?

<details><summary>Check your answers</summary>

1. The index records which documents contain which key/value paths. Extracting a key and casting it to text is a computed expression the index never stored.
2. It drops support for key-existence operators and keeps containment, in exchange for a smaller and faster index.
3. `NOT NULL`, `CHECK` constraints, and foreign keys — plus a single declared type and visibility in the schema.
4. When it is present on every row and queried by everything, so its variability was the reason for JSON and no longer exists.
</details>

## Next

Next we will look at what happens when two transactions touch the same row at the same time.

<details><summary>Maintainer source record</summary>

- Source dossier: `POSTGRESQL_DOCS.md`.
- Official sources: PostgreSQL 18 documentation on JSON types, `jsonb` containment and existence operators, `jsonb` indexing and `jsonb_path_ops`, generated columns, and expression indexes.
- Versions: PostgreSQL 18.4 (`postgres@sha256:9a8afca5…`).
- Consulted: 2026-08-23.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 12, FS11.4.
- DALT files inspected: `postgres-depth-lab`, the Part 11 track manifest, and the former FS11.1 page.
- Extracted material: "use JSONB only for flexible attributes" from the former FS11.1, expanded into the operator/index distinction and the promotion path.
- Verified in the lab: every plan above is real output, including section 4 remaining a sequential scan with the GIN index present.
</details>
