# FS11.6 — Roles, row-level security, and tenant isolation

Lesson ID: FS11.6
Lesson format: Concise theory
Part: 11 — PostgreSQL deeper
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Advanced
Prerequisites: FS11.5
Last reviewed: 2026-08-23

We will move tenant isolation into the database, so that a query which forgets its `WHERE workspace_id = …` returns nothing instead of someone else's data.

> **Helpful background:** [Membership, roles, ownership, and authorization](/learn/lessons/41-fs06-3-authorization-and-ownership)

## What we will learn

- create a restricted application role, and why the order matters;
- write `USING` and `WITH CHECK` policies against a transaction-local tenant setting;
- prove isolation as the right role, and avoid the two traps that make a proof meaningless.

## A backstop, not a replacement

FS06.5 put authorization in the application: the server decides whether this user may act on this resource. That is still where authorization belongs. Row-level security is a second wall behind it, and it answers a narrower question: *may this connection see this row at all?*

The value is that it holds even when application code is wrong. One missing `WHERE` clause in one query is a data breach; with RLS it is an empty result.

## Create the role before the policies

A policy's `TO issue_app` clause names a role that must already exist. So the role comes first — and it is deliberately not the table owner:

```sql
CREATE ROLE issue_app LOGIN NOINHERIT NOBYPASSRLS PASSWORD 'local-development-only';
GRANT USAGE ON SCHEMA public TO issue_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON issues TO issue_app;
```

Table owners and superusers bypass RLS by default, which is exactly why the application must not connect as either. `FORCE ROW LEVEL SECURITY` closes the owner's bypass as well, so a mistake in a migration script does not quietly exempt itself.

One privilege detail is worth knowing because it depends on how the key column was declared. A `BIGSERIAL` column is backed by a sequence the role also needs (`GRANT USAGE ON SEQUENCE issues_id_seq`), and without it an `INSERT` fails on *permission denied for sequence* — a failure easily misread as the policy working when the policy never ran. Our table uses `GENERATED ALWAYS AS IDENTITY`, whose sequence is managed internally, so the table grant above is sufficient. Check which one your schema uses rather than copying either answer.

## `USING` filters, `WITH CHECK` guards

```sql
ALTER TABLE issues ENABLE ROW LEVEL SECURITY;
ALTER TABLE issues FORCE ROW LEVEL SECURITY;

CREATE POLICY issues_tenant_select ON issues FOR SELECT TO issue_app
    USING (workspace_id = NULLIF(current_setting('app.workspace_id', true), '')::bigint);

CREATE POLICY issues_tenant_insert ON issues FOR INSERT TO issue_app
    WITH CHECK (workspace_id = NULLIF(current_setting('app.workspace_id', true), '')::bigint);

CREATE POLICY issues_tenant_update ON issues FOR UPDATE TO issue_app
    USING (workspace_id = NULLIF(current_setting('app.workspace_id', true), '')::bigint)
    WITH CHECK (workspace_id = NULLIF(current_setting('app.workspace_id', true), '')::bigint);
```

`USING` decides which existing rows a command can see or target. `WITH CHECK` decides which new row values are allowed. Both are needed: a policy that filters reads but lets an insert name another workspace is not tenant isolation. `UPDATE` needs both, or a row could be moved out of its tenant.

## The context must be transaction-local

The tenant comes from a setting the application establishes per request:

```sql
BEGIN;
SELECT set_config('app.workspace_id', '1', true);
-- … the request's queries …
COMMIT;
```

That third argument, `true`, means **transaction-local**: the setting is discarded at commit or rollback. This matters because connections are pooled and reused. A session-level setting would still be there when the next, unrelated request picked up the same connection.

## The `NULLIF` is not decoration

`current_setting('app.workspace_id', true)` looks like it returns `NULL` when no context has been set. Once *any* transaction on that connection has called `set_config` for the key, it returns an **empty string** instead:

```text
 looks_like_null | is_really_empty
-----------------+-----------------
 f               | t
```

Casting that directly raises an error rather than failing the comparison safely:

```text
ERROR:  invalid input syntax for type bigint: ""
```

`NULLIF(…, '')` turns the empty string back into `NULL`, and `workspace_id = NULL` is never true — so a request with no tenant context sees nothing, which is the behaviour we want.

## Prove it as the restricted role

A policy listed in `pg_policies` proves the syntax parsed. It proves nothing about protection. Run the evidence as `issue_app`:

```text
visible_rows                   100000
visible_from_workspace_2            0
distinct_workspaces_visible         1
rows_updated_in_other_tenant        0
visible_without_context             0
ERROR:  new row violates row-level security policy for table "issues"
```

Half the table is invisible. A `WHERE workspace_id = 2` returns nothing rather than data. An update aimed at the other tenant changes zero rows. An insert claiming workspace 2 is refused by the database. And a connection with no context set sees nothing at all.

## Try it

**Workspace:** continue in `.dalt/workspace/fs11-postgres`. This experiment needs only the schema and seed, so a freshly set-up database is fine.

```bash
cd .dalt/workspace/fs11-postgres
docker compose exec -T db psql -U dalt -d dalt_depth -v ON_ERROR_STOP=1 \
  -f /course/sql/fs11-6-row-level-security.sql
```

**Starting state:** two workspaces of 100,000 issues each, no role and no policies yet.

**Expected result:** eight labelled sections producing the numbers above, plus two deliberate errors — the refused cross-tenant insert in section 5, and the bare `''::bigint` cast in section 8. Both are evidence, not failures.

Now remove `NULLIF(…, '')` from the select policy, leaving `current_setting('app.workspace_id', true)::bigint`, and rerun section 7. Instead of returning zero rows, the query fails with `invalid input syntax for type bigint`.

**Reset:** `docker compose down -v`. The role is created inside the database, so dropping the volume removes it with everything else.

## What to notice

Section 7 is the one that matters most. No context, no rows — the safe default. A policy that returned everything when the context was missing would be worse than no policy, because it would look like protection.

Notice also what RLS did not do. It never authenticated anybody. The application still has to establish that the signed-in user is allowed to select workspace 1 before setting that context; RLS only ensures that, having chosen a tenant, the connection cannot escape it.

## Common mistakes

- Connecting the application as the table owner or a superuser, so every policy is bypassed.
- `ENABLE` without `FORCE`, leaving the owner exempt.
- A session-level `set_config`, which leaks the previous request's tenant into a pooled connection.
- Casting `current_setting(...)` without `NULLIF`, turning a missing context into an error instead of an empty result.
- Testing the policies as the superuser and concluding they work.

## Check your understanding

1. Why must the restricted role be created before the first `CREATE POLICY`?
2. What does `WITH CHECK` add that `USING` does not?
3. Why is the third argument to `set_config` `true`?
4. Why does the policy wrap `current_setting` in `NULLIF(…, '')`?

<details><summary>Check your answers</summary>

1. The policy's `TO` clause names the role, so the role has to exist when the policy is created.
2. `USING` limits which existing rows a command can see or target; `WITH CHECK` limits the values a new or updated row may have.
3. It makes the setting transaction-local, so it cannot survive into the next request that reuses the pooled connection.
4. Once `set_config` has run on a connection, a later `current_setting(..., true)` returns an empty string rather than `NULL`, and casting that raises instead of comparing to nothing.
</details>

## Next

That completes Part 11 and the theory track. The independent guided course at `/learn/build` applies all of it to one real application.

<details><summary>Maintainer source record</summary>

- Source dossier: PostgreSQL documentation research notes.
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: PostgreSQL 18 documentation on row security policies, `CREATE POLICY`, `ALTER TABLE … FORCE ROW LEVEL SECURITY`, `CREATE ROLE`, privileges, `set_config` / `current_setting`, and identity columns.
- Versions: PostgreSQL 18.4 (`postgres@sha256:9a8afca5…`).
- Consulted: 2026-08-23.
- Curriculum authority: DALT Fullstack theory curriculum, Batch 12, FS11.6.
- DALT files inspected: `postgres-depth-lab`, the Part 11 track manifest, and the former FS11.2 page.
- Extracted material: "add RLS as a database backstop", "prove RLS under the right role", and "policy lifecycle and connection safety" from the former FS11.2, which already carried the fixes recorded as F24–F26 in the internal Fullstack verification log.
- Verified in the lab: every number and both errors above are real output. The sequence-grant guidance was re-tested against this schema — `GENERATED ALWAYS AS IDENTITY` needs no sequence grant, unlike `BIGSERIAL`, and the lesson now says which is which instead of repeating one case as universal.
</details>
