# FS05.5 — Migrations, constraints, and foundational indexes

Lesson ID: FS05.5
Lesson format: Concise theory
Part: 05 — DALT API and PostgreSQL
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Foundation
Prerequisites: FS05.4
Last reviewed: 2026-08-22

We will evolve our first relational model through ordered SQL and make important application rules true for every database writer.

> **Helpful background:** [Relational modeling with keys and relationships](/learn/lessons/37-fs05-2-relational-modeling-and-migrations)

## What we will learn

- treat migrations as append-only schema history;
- choose unique and check constraints for application invariants;
- add an index for a known relationship query without guessing at future performance.

## A migration is one recorded schema change

Our first SQL file creates related tables. The next requirement should become a new file:

```text
database/migrations/
├── 001_create_relations.sql
└── 002_add_constraints_and_indexes.sql
```

The numeric prefix establishes order. Once a migration has been shared or applied to a lasting database, do not edit it to produce a nicer history. Add the next migration. An existing database needs only the new step; a clean database can replay the complete sequence.

DALT's migration runner sorts `.sql` files, creates a `migrations` table, skips recorded filenames, and applies each pending file with its record in one transaction. Therefore a failed migration is not marked as successful.

```bash
php artisan migrate
```

The shared lab uses the same `Core\Migration` class with a lab-specific folder. Application migrations normally remain under root `database/migrations/`.

## Constraints turn rules into guarantees

FS05.3 validates requests so a user gets useful field feedback. A constraint has a different audience: every route, script, import, and future service that writes to this database.

Workspace slugs identify one workspace globally:

```sql
ALTER TABLE workspaces
    ADD CONSTRAINT workspaces_slug_unique UNIQUE (slug);
```

A project slug is unique only within its workspace:

```sql
ALTER TABLE projects
    ADD CONSTRAINT projects_workspace_slug_unique
    UNIQUE (workspace_id, slug);
```

The composite constraint allows two workspaces to own a project named `website`, but prevents one workspace from owning it twice. The columns in a uniqueness rule must match the scope in which the product says “one.”

Checks restrict values within a row:

```sql
ALTER TABLE issues
    ADD CONSTRAINT issues_title_not_blank
        CHECK (length(btrim(title)) > 0),
    ADD CONSTRAINT issues_status_allowed
        CHECK (status IN ('todo', 'in_progress', 'done')),
    ADD CONSTRAINT issues_priority_allowed
        CHECK (priority IN ('low', 'medium', 'high'));
```

`NOT NULL` prevents absence, but it does not reject a title containing only spaces. The named check does. Naming constraints makes PostgreSQL diagnostics and later `ALTER TABLE` migrations easier to understand.

Do not use a `CHECK` to search another table. PostgreSQL expects a check expression to depend on the row being inserted or updated. Cross-table existence belongs to a foreign key; cross-row uniqueness belongs to a unique constraint.

## Defaults do not validate explicit values

This column combines three distinct decisions:

```sql
status VARCHAR(30) NOT NULL DEFAULT 'todo'
```

`NOT NULL` rejects absence, `DEFAULT` supplies `todo` when an INSERT omits the column, and the check constraint rejects an explicit unsupported status. A default does not repair `status = 'later'`; it is used only when no value is provided.

The API should usually omit server-owned defaults during creation and return the stored row. That keeps one authority for initial status.

## Constraints and indexes overlap, but not completely

PostgreSQL automatically creates unique B-tree indexes for primary keys and unique constraints. Creating another index on `workspaces.id` or `workspaces.slug` would duplicate existing work.

A foreign key is different. PostgreSQL indexes the referenced key, but it does not automatically index the child column. Our common query lists issues in one project, and a project delete must check whether children exist. Both begin from `issues.project_id`, so this index has a concrete reason:

```sql
CREATE INDEX issues_project_id_idx ON issues (project_id);
```

We are not claiming a measurable speedup with three rows. Part 11 uses real query plans and data scale. Here we establish one foundational access path for a relationship we already know we will query.

Avoid speculative indexes on every column. Each index takes storage and must be updated by writes. Add one because a constraint or known query pattern explains it; later, measure whether more are earned.

## Try it

**Workspace:** continue in `.dalt/workspace/fs05-postgres` or create a fresh copy from FS05.4.

**Starting state:** reset the database so DALT can own the complete migration history:

```bash
docker compose down -v
docker compose up -d --wait
```

Run both pending migrations through DALT:

```bash
php scripts/migrate.php
```

The output names `001_create_relations.sql` and `002_add_constraints_and_indexes.sql`, reports success for each, and ends with `Ran 2 migrations.` Run it again:

```bash
php scripts/migrate.php
```

This time it prints `No migrations to run.` The filenames in the `migrations` table are the evidence that prevents replay.

Now ask two constraints to reject false facts:

```bash
docker compose exec -T db psql -U dalt -d dalt_course \
  -c "INSERT INTO workspaces (name, slug) VALUES ('One', 'same'), ('Two', 'same');"

docker compose exec -T db psql -U dalt -d dalt_course \
  -c "INSERT INTO workspaces (name, slug) VALUES ('Valid', 'valid'); \
      INSERT INTO projects (workspace_id, name, slug) VALUES (1, 'App', 'app'); \
      INSERT INTO issues (project_id, title, status) VALUES (1, 'Bad', 'later');"
```

The first command names `workspaces_slug_unique`; the second names `issues_status_allowed`. Both exit non-zero.

Inspect the deliberate child index:

```bash
docker compose exec -T db psql -U dalt -d dalt_course \
  -c "SELECT indexname FROM pg_indexes WHERE indexname = 'issues_project_id_idx';"
```

It returns exactly that index name.

**Expected result:** migrations run once in filename order; duplicate identity and unsupported status cannot be stored; the known relationship index exists.

**Reset:** keep the migrated service for FS05.6, or run `docker compose down -v` and delete the workspace.

## What to notice

Application validation creates a good conversation with one client. Database constraints create guarantees for every writer. The migration history makes those guarantees reproducible instead of local accidents.

## Check your understanding

1. Why add a new migration instead of editing one already applied?
2. Why is project slug uniqueness composite?
3. What is the difference between a default and a check?
4. Which constraints already create indexes?

<details><summary>Check your answers</summary>

1. Existing databases need a new ordered step, while clean databases must reproduce the same final schema.
2. The slug is unique within a workspace, not across every workspace.
3. A default supplies an omitted value; a check accepts or rejects the resulting row.
4. Primary-key and unique constraints create their enforcing unique indexes.
</details>

## Next

The database can now preserve valid rows; next PHP will read and mutate them safely with PDO, prepared statements, and `RETURNING`.

<details><summary>Maintainer source record</summary>

- Source dossier: `FSO_RELATIONAL_DATABASES.md`; `POSTGRESQL_DOCS.md`.
- Official sources: PostgreSQL 18 documentation for constraints, defaults, `ALTER TABLE`, indexes, and foreign-key indexing guidance.
- Versions: PostgreSQL 18.4 pinned by the shared lab image digest; DALT current migration runner.
- Consulted: 2026-08-22.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 7, FS05.5.
- DALT files inspected: `framework/Core/Migration.php`, `Database.php`, `artisan` migrate commands, root migration tests, and current SQL migrations.
- Reused material: append-only migration history, scoped uniqueness, named checks, defaults, and foreign-key index guidance split from former FS05.2.
</details>
