# FS05.2 — Relational modeling and migrations

Lesson ID: FS05.2  
Title: Relational modeling and migrations  
Part: 05 — DALT API and PostgreSQL  
Order: 2  
Status: Published  
Estimated effort: 110–140 minutes  
Difficulty: Integration  
Prerequisites: FS05.1 — Designing the application API  
Project milestone: B05 — Persistent application  
Primary source dossier: `FSO_RELATIONAL_DATABASES.md`  
Last reviewed: 2026-08-19

## Why this matters

An API contract says what an issue means to a client. PostgreSQL has a different job: it
keeps durable facts coherent when a client is buggy, when another program writes data, or
when an old server is still running. A table isn't persistence by itself. Nullability, keys,
foreign keys, uniqueness, checks, and delete behaviour decide which facts can exist together.
Those decisions are product rules, expressed at the one place every writer must pass through.

The tracker already has a relationship story. A workspace contains projects, and a project
contains issues. Repeating the workspace name inside every issue looks easy until a rename,
a join, or an invalid project reference arrives. A relational model gives each fact one home,
and keys make the connection enforceable. That lets a handler give a friendly response while
the database preserves truth even when the handler isn't the writer.

Migrations change this shared truth. They're ordered SQL history, not files we rewrite
until one local database looks right. A clean checkout needs the same schema, and an existing
database needs a record of which steps ran.

## Before you start

Required: FS05.1 and a running PostgreSQL. Configure the connection in `.env` — read
`config/database.php` rather than copying SQLite defaults:

```sh
DB_DRIVER=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=dalt_php_app
DB_USERNAME=postgres
DB_PASSWORD=
DB_CHARSET=utf8
```

Prove the database is reachable *before* you write a migration, so that a later failure has
one possible cause instead of two:

```sh
psql "postgresql://postgres@127.0.0.1:5432/dalt_php_app" -c 'SELECT version();'
```

Then read two framework files. `framework/Core/Database.php` gives you
`query($sql, $params)` returning `find()`, `findOrFail()`, or `get()`, over PDO with
exceptions enabled and emulated prepares turned off. `framework/Core/Migration.php` sorts
`.sql` files in `database/migrations/` by filename, runs the pending ones, and records each
in a `migrations` table with a batch number.

Going deeper in DALT Core — optional:

- [Database fundamentals](/learn/lessons/05-database) and [PostgreSQL first contact](/learn/lessons/09-postgres-first-contact) are reference, not prerequisites.

## By the end

You should be able to:

- model workspaces, projects, and issues as normalized related tables;
- choose primary, foreign, unique, and check constraints for real rules;
- decide nullability and deletion behaviour explicitly;
- run ordered DALT SQL migrations against PostgreSQL;
- distinguish application validation from database-enforced truth;
- inspect schema and rows independently of the browser.

## Predict before reading

Write answers down before reading on.

1. If `issues.project_id` is merely an integer, what prevents a nonexistent project?
2. Is a project slug globally unique or unique only within a workspace?
3. What should deletion do to a project that has issues?
4. Can a direct HTTP request create a status React never offered?

## Mental model

```text
workspace 1 ───< project 1 ───< issue
 primary key       foreign key       foreign key
     │                 │                 │
 unique slug      unique parent/slug  checked status and priority
```

A primary key identifies a row. A foreign key requires its parent to exist. A unique
constraint prevents two rows claiming one domain identity. A check restricts a stored
vocabulary. Application validation offers clear recovery before SQL runs; constraints remain
true after every route, import, console session, and future application version. They
cooperate — and the division of labour is that validation produces a good message, while a
constraint produces a guarantee.

## 1. One fact, one home

The temptation in a first schema is to keep everything within reach:

```sql
-- Do not do this.
CREATE TABLE issues (
  id BIGSERIAL PRIMARY KEY,
  workspace_name VARCHAR(120) NOT NULL,   -- repeated in every row
  project_name   VARCHAR(120) NOT NULL,   -- repeated in every row
  title          VARCHAR(240) NOT NULL
);
```

Every query is now a single table read, which feels fast and simple. Three problems arrive
in order. Renaming a workspace means updating every issue, and if the update fails halfway
you have two names for one workspace with no way to tell which is right. Nothing stops two
rows disagreeing about which project belongs to which workspace. And a project with no issues
cannot exist at all, because projects only exist as text inside issues.

Those are not performance problems, they are *truth* problems, and the fix is to give each
fact one home and point at it:

```sql
CREATE TABLE workspaces (
  id BIGSERIAL PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(80) NOT NULL UNIQUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE projects (
  id BIGSERIAL PRIMARY KEY,
  workspace_id BIGINT NOT NULL REFERENCES workspaces(id) ON DELETE RESTRICT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (workspace_id, slug)
);
```

The global workspace slug is a product choice, and it is the right one if the slug appears in
a URL — two workspaces called `acme` cannot both own `/acme`. Project slugs are different:
`website` in one workspace has nothing to do with `website` in another, so
`UNIQUE (workspace_id, slug)` captures the actual rule. That is prediction 2, and the general
lesson is that a uniqueness constraint should name the scope in which the thing is unique.
Getting this wrong in either direction is expensive: too narrow allows real duplicates, too
wide rejects legitimate data and users find out by being unable to do their job.

The issue table owns its own present facts:

```sql
CREATE TABLE issues (
  id BIGSERIAL PRIMARY KEY,
  project_id BIGINT NOT NULL REFERENCES projects(id) ON DELETE RESTRICT,
  title VARCHAR(240) NOT NULL CHECK (length(trim(title)) > 0),
  description TEXT NOT NULL DEFAULT '',
  status VARCHAR(30) NOT NULL DEFAULT 'todo' CHECK (status IN ('todo','in_progress','done')),
  priority VARCHAR(20) NOT NULL DEFAULT 'medium' CHECK (priority IN ('low','medium','high')),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

There is no `workspace_id` on `issues`, deliberately. It is reachable through the project, and
storing it twice creates the possibility of the two disagreeing. Denormalise later, if a real
query is too slow and you have measured it — and when you do, understand that you are buying
speed with a new invariant you must maintain.

`NOT NULL` means absence is not allowed. It does not mean every description must have content:
an empty string is a deliberate known value, whereas NULL means "we do not know", and those are
genuinely different facts. Reach for NULL when *unknown* is a state the product needs to
represent, not to avoid deciding what empty means.

Use `BIGSERIAL` here. SQLite's `INTEGER PRIMARY KEY AUTOINCREMENT` is a different dialect, and
although DALT's migration runner rewrites a few SQLite spellings when it detects PostgreSQL,
relying on that translation means writing SQL you have not actually read.

## 2. Constraints are behaviour you can observe

A constraint you have never seen fire is a constraint you are trusting on faith. Make each one
reject something, in `psql`:

```sql
-- Foreign key: the parent must exist.
INSERT INTO issues (project_id, title) VALUES (99999, 'Orphan');
-- ERROR:  insert or update on table "issues" violates foreign key constraint
-- DETAIL:  Key (project_id)=(99999) is not present in table "projects".

-- Check: the vocabulary is closed.
INSERT INTO issues (project_id, title, status) VALUES (1, 'Bad status', 'later');
-- ERROR:  new row for relation "issues" violates check constraint "issues_status_check"

-- Check: a title of spaces is not a title.
INSERT INTO issues (project_id, title) VALUES (1, '   ');
-- ERROR:  new row for relation "issues" violates check constraint "issues_title_check"

-- Composite unique: same slug, same workspace.
INSERT INTO projects (workspace_id, name, slug) VALUES (1, 'Site', 'website');
INSERT INTO projects (workspace_id, name, slug) VALUES (1, 'Site again', 'website');
-- ERROR:  duplicate key value violates unique constraint "projects_workspace_id_slug_key"

-- ...but a different workspace is fine, which is the point of the composite key.
INSERT INTO projects (workspace_id, name, slug) VALUES (2, 'Other site', 'website');
-- INSERT 0 1
```

That last pair is the one to run deliberately. A composite unique constraint that you have only
seen reject things has not been shown to permit the case it exists to permit.

This also answers prediction 4. React's `<select>` offers three statuses; a curl request, an old
deployed client, a data import, or a colleague in `psql` offers whatever it likes. The check
constraint is what makes `status = 'later'` impossible rather than merely unlikely. Note the
division of labour: the handler should still return the friendly 422 from FS05.1 for ordinary
input, because a raw PostgreSQL error message is not an API contract and must never be forwarded
to a client — it names your tables.

## 3. Deletion is a product decision

`ON DELETE` is not a technical detail to be filled in by habit. It is the answer to "what should
happen to the children?", and each option is a different product:

| Clause | Behaviour | Says |
|---|---|---|
| `RESTRICT` | Refuses the delete while children exist | "Deal with the issues first" |
| `CASCADE` | Deletes the children too | "The children have no meaning alone" |
| `SET NULL` | Orphans the children, key must be nullable | "An unassigned issue is meaningful" |

`RESTRICT` is the right initial choice here precisely because project deletion has not been
designed yet. It fails loudly and reversibly. `CASCADE` on a project would silently destroy
every issue in it the first time someone deletes the wrong row, and there is no undo. Choose
`CASCADE` when the child genuinely cannot exist without the parent — a session row, a join-table
membership — and not because it makes an error message go away.

Changing your mind later is a new migration, not an edit:

```sql
ALTER TABLE issues DROP CONSTRAINT issues_project_id_fkey;
ALTER TABLE issues ADD CONSTRAINT issues_project_id_fkey
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE;
```

## 4. The indexes you already have, and the one you need

Two of your constraints created indexes as a side effect, because PostgreSQL enforces uniqueness
by building one: `workspaces.slug` and `projects (workspace_id, slug)`. Primary keys likewise.
You did not ask for those and you get them.

Foreign keys are the trap. PostgreSQL indexes the *referenced* column, because it is a primary
key, but **not** the referencing one. So `issues.project_id` — the column every single listing
query filters on — has no index at all:

```sql
CREATE INDEX idx_issues_project_id ON issues (project_id);
```

With a few dozen seeded rows you will not measure any difference, and it would be dishonest to
pretend otherwise. Add it anyway, for a reason you can defend now: `ON DELETE RESTRICT` makes
PostgreSQL scan `issues` on every project delete to check for children, and every "issues in
this project" query does the same scan. Part 11 returns with `EXPLAIN` and teaches you to
measure rather than guess; this one is a well-understood default, not a guess.

Resist adding more. An index costs write time and storage on every insert, and an index nobody
queries is pure overhead. One per foreign key you filter on is a reasonable starting rule.

## 5. Migrations are append-only history

Create one initial migration holding the parent-first schema above. A single initial file is
easier to run cleanly than three generator calls: DALT names files with a second-resolution
timestamp, so calls issued in the same second can collide.

```sh
php artisan make:migration create_issue_tracker_tables
# edit database/migrations/<timestamp>_create_issue_tracker_tables.sql
php artisan migrate
```

```text
Running migration: 20260814213000_create_issue_tracker_tables.sql
✓ Success

Ran 1 migrations.
```

Run it a second time and read the output carefully:

```text
No migrations to run.
```

That sentence is the whole idea. DALT recorded the filename in its `migrations` table, so the
file will never run twice — which is what makes it safe to run `php artisan migrate` on a server
that is already up to date.

```sql
SELECT migration, batch, created_at FROM migrations ORDER BY id;
```

Each migration also runs inside a transaction: `Migration::runOne` opens one, executes the SQL,
records the filename, and commits — rolling back everything if any statement throws. PostgreSQL
supports transactional DDL, so a migration that creates three tables and fails on the third
leaves you with zero tables rather than two. Not every database can do this; MySQL cannot, and
partial migrations there are a genuine operational hazard. It is a real reason to appreciate
PostgreSQL, and a reason to write one migration per coherent change rather than batching
unrelated work into one file.

**Never edit a migration that has run anywhere but your own machine.** Editing history creates a
false green state: the existing database has the filename recorded as run and will skip your
change forever, while a fresh database executes different SQL under the same name. The two
environments now differ, and nothing reports it. Add a new migration instead — that is what the
append-only rule buys you.

While a schema is still local and unshared, `php artisan migrate:fresh` is the honest way to
iterate: it drops everything and replays history from empty, which is also the only real proof
that your history builds a correct schema from nothing.

## 6. Look at the schema, not at your intentions

Ask PostgreSQL what it actually built:

```sh
psql "$DATABASE_URL" -c '\d issues'
```

Read the constraint list at the bottom of that output against the SQL you wrote. A `CHECK` you
misspelled, a foreign key you forgot, a column that is nullable when you meant it not to be —
all of them appear here and none of them appear in your migration's success message. "The
migration ran" and "the schema is what I intended" are different claims.

Then seed one real relationship, using returned ids rather than assuming `1`:

```sql
WITH w AS (
  INSERT INTO workspaces (name, slug) VALUES ('Acme', 'acme') RETURNING id
), p AS (
  INSERT INTO projects (workspace_id, name, slug)
  SELECT id, 'Website', 'website' FROM w RETURNING id
)
INSERT INTO issues (project_id, title, status)
SELECT id, 'Fix the login redirect', 'todo' FROM p;
```

```sql
SELECT w.name AS workspace, p.name AS project, i.id, i.title, i.status
FROM issues i
JOIN projects p ON p.id = i.project_id
JOIN workspaces w ON w.id = p.workspace_id
ORDER BY i.id;
```

This join is relational evidence, not yet an API response. FS05.3 maps rows like these into the
FS05.1 contract without exposing every database column.

The `WITH ... RETURNING` form above is worth a second look, because it is doing something the
obvious three-statement version cannot. `RETURNING id` hands back the id PostgreSQL generated,
so each step feeds the next without anybody guessing. The alternative — insert the workspace,
read its id in a separate query, then insert the project — has a gap between the read and the
write during which another connection can act. It also breaks the moment your development
database is not empty and the ids are not 1, 2, 3.

Assuming `id = 1` is the single most common way a seed script becomes untrustworthy. It works
the first time, and it silently attaches rows to the wrong parent every time after that. Get
into the habit of never typing a literal id.

Also notice `created_at` filling itself in from `DEFAULT CURRENT_TIMESTAMP`. Defaults live in
the schema for the same reason constraints do: a value that every writer needs should not
depend on every writer remembering it.

## Try it

Draw the three-table diagram and label every key, nullability rule, uniqueness rule, and delete
decision. Then write the migration, run it, and run `\d` on all three tables. Fire every
constraint from §2 and record the exact error text. Attempt to delete a project that has issues
and read the `RESTRICT` refusal. Finally run `php artisan migrate:fresh` and confirm the whole
schema rebuilds from nothing.

## Common mistakes

### Duplicating `workspace_id` in issues without a current requirement

A fact stored in two places can disagree. `issues` reaches its workspace through `projects`; add the shortcut only once a real query has felt the pain, not in advance of it.

### Calling a slug unique when it's only unique within its parent

`UNIQUE (slug)` on `projects` would reject `website` in a second workspace that has nothing to do with the first. Name the actual scope: `UNIQUE (workspace_id, slug)`.

### Using NULL to avoid deciding what an empty field means

NULL means "we don't know." An empty string is a deliberate known value. Reach for NULL only when *unknown* is a state the product genuinely needs, not as a shortcut past a decision.

### Choosing CASCADE because it makes an error go away

`CASCADE` on a project silently destroys every issue in it the first time someone deletes the wrong row, with no undo. Choose it only when the child genuinely cannot exist without the parent.

### Forgetting the index on a foreign key you filter and delete against

PostgreSQL indexes the referenced column automatically because it's a primary key — it does not index the referencing column. `issues.project_id`, the column every listing query filters on, gets nothing unless you add it yourself.

### Editing an applied migration instead of adding a new one

The existing database has the old filename recorded as run and will skip your edit forever, while a fresh database executes the new SQL under the same name. The two environments now differ silently.

### Believing TypeScript, or a React `<select>`, enforces database truth

A typed form only constrains the browser you wrote. A curl request, an old deployed client, or a colleague in `psql` is bound by none of it — only a database constraint is.

### Forwarding a raw PostgreSQL error to an API client

A raw error message names your tables and columns. Log it; return the FS05.1 envelope instead.

## When this goes wrong

Read the exact PostgreSQL message before changing anything. It names the constraint, and the
constraint name tells you which rule you broke — `issues_status_check` is a different problem
from `issues_project_id_fkey`.

A foreign-key error on a fresh migration usually means table ordering (a child created before
its parent) or a type mismatch (`INTEGER` referencing `BIGSERIAL`). A migration that seems to
have been skipped means inspecting its filename and the `migrations` table, not rerunning
things at random. A connection failure means verifying your pgsql environment values and
testing PostgreSQL independently with `psql` — do not change framework code to work around
learner configuration.

## Exercise

### Goal

Make the issue tracker's minimum domain PostgreSQL-enforced.

### Starting state

FS05.1 has a written contract; B04's fixture is still the source of issue data.

### Requirements

- Add a parent-first migration for workspaces, projects, and issues.
- Include primary and foreign keys, intentional nullability, composite uniqueness, status and priority checks, and timestamps.
- Choose and justify deliberate delete behaviour on each foreign key.
- Add an index on the issues foreign key.
- Seed one real relationship using `RETURNING`, not a literal id.

### Constraints

- Model the present product only — not Part 06's users or Part 11's features you haven't built yet.
- Every constraint failure should agree with FS05.1's validation about what's invalid, and disagree only on how politely it says so.
- Do not edit an applied migration to fix a mistake. Add a new one.

### Verification

**Mode: manual PostgreSQL evidence and migration command output.** The database proves these rules; the course does not auto-grade your chosen schema.

Build a fresh schema with `migrate:fresh`, rerun unchanged migrations and see "No migrations to run," inspect a three-table join, and capture the rejection text for a missing parent, an invalid status, a blank title, and a duplicate project slug — plus the acceptance of that same slug in another workspace.

### Hints

<details>
<summary>Hint 1 — build order</summary>

Write tables parent-first: workspaces, then projects, then issues. A foreign key referencing a table that doesn't exist yet fails the migration before you learn anything about your actual design.
</details>

<details>
<summary>Hint 2 — proving a constraint exists</summary>

A constraint you've never watched reject something is a constraint you're trusting on faith. For each one, write the `INSERT` that should fail, run it in `psql`, and read the exact error before moving on.
</details>

<details>
<summary>Hint 3 — the delete-behaviour decision</summary>

Ask, for each foreign key: can the child mean anything without the parent? If you're unsure, `RESTRICT` is the safer default — it fails loudly and reversibly, where `CASCADE` fails silently and permanently.
</details>

<details>
<summary>Reference explanation — read after an honest attempt</summary>

The working shape is §1's three tables (`BIGSERIAL` primary keys, `workspace_id`/`project_id` foreign keys, `UNIQUE (workspace_id, slug)`, status/priority `CHECK`s), §4's index on `issues.project_id`, and §6's `WITH ... RETURNING` seed. The proof isn't that the migration runs without error — it's that `\d issues` shows the constraints you intended, and that each one visibly rejects the row it exists to reject.
</details>

## In the project

The application now owns durable issue facts. The React client doesn't need to learn PostgreSQL; it
continues to consume the contract from FS05.1. FS05.3 replaces fixture operations with real
queries and then proves behaviour through both HTTP and rows — because a passing screen and a
correct database are, once again, two different claims.

## Closed-book checkpoint

Close the lesson first.

1. What separate rule is expressed by primary key, foreign key, unique, and check?
2. Why is `UNIQUE(workspace_id, slug)` not the same as global uniqueness?
3. When is RESTRICT more honest than CASCADE?
4. Which index does a foreign key not give you, and why does it matter?
5. Why must migration history be ordered and append-only?
6. Which invalid states must PostgreSQL reject even after request validation exists?

<details>
<summary>Reveal comparison answers</summary>

1. Primary key identifies a row; foreign key requires its parent to exist; unique prevents two rows claiming one domain identity; check restricts a stored vocabulary. Four different, independent facts.
2. `UNIQUE(workspace_id, slug)` scopes the rule to one workspace — `website` can exist once per workspace, but the same slug is fine in a different one. Global uniqueness would reject the second workspace's legitimate use of the same slug.
3. When the deletion behaviour hasn't been designed yet, or the child genuinely has meaning on its own. RESTRICT fails loudly and reversibly; CASCADE silently destroys data with no undo.
4. The index on the *referencing* column — `issues.project_id` here. PostgreSQL only automatically indexes the referenced column (because it's a primary key), so every "issues in this project" query and every `ON DELETE RESTRICT` check scans the table without it.
5. Because a database that already ran a migration must never be told to run different SQL under the same recorded name — that silently produces two environments with different schemas and no way to detect it.
6. Anything a client can reach directly with a crafted request, a script, or `psql` — a nonexistent parent, an out-of-vocabulary status, a blank title. Request validation only holds for requests that pass through the validated path.
</details>

## Resources

### Read

- [PostgreSQL: CREATE TABLE](https://www.postgresql.org/docs/current/sql-createtable.html)
- [PostgreSQL: constraints](https://www.postgresql.org/docs/current/ddl-constraints.html)
- [Full Stack Open Relational Databases](https://fullstackopen.com/en/relational_databases)

### Go deeper

- [PostgreSQL: indexes and foreign keys](https://www.postgresql.org/docs/current/ddl-constraints.html#DDL-CONSTRAINTS-FK)
- [Laravel: migrations](https://laravel.com/docs/12.x/migrations)
- [Laravel: Eloquent relationships](https://laravel.com/docs/12.x/eloquent-relationships)

## You are done when

- [ ] I can explain every initial table and relationship.
- [ ] PostgreSQL rejects missing parents, blank titles, and invalid status/priority values.
- [ ] A duplicate project slug is rejected in one workspace and accepted in another.
- [ ] `migrate:fresh` builds my schema from an empty database.
- [ ] A second `migrate` reports "No migrations to run."
- [ ] `\d issues` shows the constraints I intended, not the ones I assumed.
- [ ] I can defend my uniqueness, index, and delete decisions as product behaviour.
- [ ] A three-table join proves my seed relation is correct.
- [ ] I know to add a migration rather than revise applied history.

---

## Maintainer source record

- Source dossier: `docs/dalt-fullstack/sources/FSO_RELATIONAL_DATABASES.md`; `docs/dalt-fullstack/sources/POSTGRESQL_DOCS.md`
- Official sources: PostgreSQL CREATE TABLE, constraints, and foreign-key indexing documentation; Laravel migrations and relationships for comparison
- Versions: PostgreSQL official current documentation consulted 2026-08-14; PHP 8.4; DALT current migration implementation
- Consulted: 2026-08-14
- DALT files inspected: `framework/Core/Database.php`; `framework/Core/Migration.php`; `config/database.php`; `database/migrations/`
- Curriculum authority: `CURRICULUM.md` §15 FS05.2
- Laravel bridge: Laravel expresses these same constraints through a schema builder DSL; DALT uses the SQL directly so the constraint and its enforcement are the same text.
- Follow-up pass: 2026-08-19 — verified the PDO/migration claims (`ERRMODE_EXCEPTION`/`EMULATE_PREPARES`, transactional `runOne`, the SQLite→PostgreSQL spelling rewrite) against the actual `framework/Core/Migration.php` and `Database.php` source, no discrepancies found; restructured Exercise into LESSON_STANDARD.md §97's subsections with a hint ladder and reference explanation; split Common mistakes into explained subsections; added a Closed-book checkpoint answer reveal; light voice pass toward first-person-plural framing to match Parts 00–04
