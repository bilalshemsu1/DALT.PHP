# FS05.4 — Relational modeling with keys and relationships

Lesson ID: FS05.4
Lesson format: Concise theory
Part: 05 — DALT API and PostgreSQL
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Foundation
Prerequisites: FS05.3
Last reviewed: 2026-08-22

We will model workspaces, projects, and issues so each fact has one home and every relationship names real rows.

> **Helpful background:** [JSON contracts, validation, and error responses](/learn/lessons/36-fs05-1-designing-the-application-api)

## What we will learn

- turn application entities into related tables;
- use primary and foreign keys to express identity and parenthood;
- choose nullability and deletion behavior as product decisions.

## Rows hold facts; keys connect them

A relational database stores rows in tables. Each table should represent one kind of fact. Repeating workspace and project names inside every issue looks convenient:

```sql
CREATE TABLE issues (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    workspace_name VARCHAR(120) NOT NULL,
    project_name VARCHAR(120) NOT NULL,
    title VARCHAR(240) NOT NULL
);
```

But a workspace rename now touches many issue rows. Two issues can disagree about a project's workspace, and a project with no issues cannot exist. These are truth problems, not styling problems.

Give each entity one table instead:

```text
workspace 1 ───< many projects
project   1 ───< many issues
```

The “one” side owns one row. The “many” side stores the parent's key.

## A primary key gives each row an identity

The workspace table has a generated numeric identity:

```sql
CREATE TABLE workspaces (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

`PRIMARY KEY` means the value is unique and not null. PostgreSQL also creates the unique B-tree index needed to enforce it. `GENERATED ALWAYS AS IDENTITY` asks PostgreSQL to generate values; our application does not invent the next number by reading the current maximum.

The numeric ID is stable even if the name or slug changes. Human-readable fields describe a workspace; the primary key identifies its row.

## A foreign key makes a relationship enforceable

A project stores the workspace it belongs to:

```sql
CREATE TABLE projects (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    workspace_id BIGINT NOT NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    CONSTRAINT projects_workspace_fk
        FOREIGN KEY (workspace_id)
        REFERENCES workspaces (id)
        ON DELETE RESTRICT
);
```

`workspace_id` alone would merely be a number with a suggestive name. The foreign key requires that number to match an existing `workspaces.id`. An issue uses the same pattern for its project:

```sql
project_id BIGINT NOT NULL,
CONSTRAINT issues_project_fk
    FOREIGN KEY (project_id)
    REFERENCES projects (id)
    ON DELETE RESTRICT
```

We do not also store `workspace_id` on the issue. The issue reaches its workspace through its project. Storing both would create two answers that can disagree.

## Nullability states whether absence is meaningful

`NOT NULL` says every row must carry that fact. Every issue in our current product belongs to a project, so `project_id` is not nullable. If unassigned issues later become a genuine feature, we can change the model deliberately rather than using null as “we have not decided.”

Null and an empty string are different. Null means the value is absent or unknown. An empty description may be a known value: the issue currently has no description. Choose the representation from product meaning, not convenience.

## Deletion behavior is part of the model

When a parent is deleted, PostgreSQL needs an explicit policy for children:

| Choice | Result |
|---|---|
| `RESTRICT` | refuse while children exist |
| `CASCADE` | delete the children too |
| `SET NULL` | keep children without that parent; the key must allow null |

`RESTRICT` is the safe starting point for projects and issues because project deletion has not yet been designed. A mistaken delete fails visibly instead of destroying an issue history. `CASCADE` is appropriate only when children truly have no meaning without the parent, such as a short-lived session or some join-table rows.

## Joins reconstruct the connected view

Normalization separates facts for storage; a join brings the needed view together for a query:

```sql
SELECT w.name AS workspace, p.slug AS project, i.title AS issue
FROM issues AS i
JOIN projects AS p ON p.id = i.project_id
JOIN workspaces AS w ON w.id = p.workspace_id;
```

The conditions after `ON` state how rows correspond. This is not duplicating the data again: the result is a temporary view assembled from one authoritative copy of each fact.

## Try it

**Workspace:** `.dalt/workspace/fs05-postgres`.

**Starting state:** copy the shared lab and start its pinned PostgreSQL service:

```bash
rm -rf .dalt/workspace/fs05-postgres
cp -R .dalt/course/fullstack/postgres-php-lab/starter .dalt/workspace/fs05-postgres
cd .dalt/workspace/fs05-postgres
docker compose up -d --wait
```

This command uses Docker only to launch an isolated PostgreSQL 18.4 process. Part 10 explains containers; for now, the observable subject is the database.

Create the three related tables, then seed and join one chain of rows:

```bash
docker compose exec -T db psql -U dalt -d dalt_course \
  -v ON_ERROR_STOP=1 -f /course/database/migrations/001_create_relations.sql

docker compose exec -T db psql -U dalt -d dalt_course \
  -v ON_ERROR_STOP=1 -f /course/database/observe-relations.sql
```

The final table contains:

```text
  workspace  | project |      issue
-------------+---------+-----------------
 DALT Course | web-app | Trace a request
```

Now ask PostgreSQL to create an orphan:

```bash
docker compose exec -T db psql -U dalt -d dalt_course \
  -c "INSERT INTO issues (project_id, title) VALUES (999, 'Orphan');"
```

The command fails and names `issues_project_fk`. The numeric value was valid SQL, but the relationship was false.

**Expected result:** the join reconstructs one workspace → project → issue path, while the foreign key rejects an issue whose parent does not exist.

**Reset:** keep the service running for FS05.5, or run `docker compose down -v` and delete `.dalt/workspace/fs05-postgres` for a complete reset.

## What to notice

The application can validate `project_id` for a friendly API message, but the foreign key is the guarantee every writer must obey. Tables separate facts; keys preserve their connections; joins produce the view a screen needs.

## Check your understanding

1. What does a primary key guarantee?
2. Why is a named `project_id` column not yet a relationship?
3. Why does the issue table omit `workspace_id`?
4. What product statement does `ON DELETE RESTRICT` make?

<details><summary>Check your answers</summary>

1. It uniquely identifies a row and cannot be null.
2. Only a foreign-key constraint requires the value to identify a real parent row.
3. Its project already determines the workspace; a second stored answer could disagree.
4. The parent cannot be removed until its children are handled deliberately.
</details>

## Next

The relationships are explicit; next we will evolve this first schema with ordered migrations, stronger constraints, and useful indexes.

<details><summary>Maintainer source record</summary>

- Source dossier: `FSO_RELATIONAL_DATABASES.md`; `POSTGRESQL_DOCS.md`.
- Official sources: PostgreSQL 18 documentation for table basics, identity columns, primary keys, foreign keys, nullability, referential actions, and joins; Docker Official Image tag listing.
- Versions: PostgreSQL 18.4, official image digest `sha256:9a8afca54e7861fd90fab5fdf4c42477a6b1cb7d293595148e674e0a3181de15`; PHP 8.2 minimum.
- Consulted: 2026-08-22.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 7, FS05.4.
- DALT files inspected: `framework/Core/Database.php`, `Migration.php`, `config/database.php`, `artisan`, and migration tests.
- Reused material: normalization, key relationships, nullability, delete-policy, and join explanations from former FS05.2.
</details>
