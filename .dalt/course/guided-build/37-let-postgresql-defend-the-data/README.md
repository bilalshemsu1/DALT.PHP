Our controllers validate normal browser input, but the database currently accepts an
orphan project, an ownerless workspace, or an issue whose status is `blocked`. A real
integrity boundary must hold even when data arrives from a script, a future controller,
or a bug. We will append one migration that makes PostgreSQL defend the rules our
product already uses.

## Add checks for current user data

Create `database/migrations/006_protect_current_invariants.sql`. Start with the rules
registration already enforces:

```sql
ALTER TABLE users
    ADD CONSTRAINT users_name_length_check
        CHECK (char_length(btrim(name)) BETWEEN 2 AND 60),
    ADD CONSTRAINT users_email_normalized_check
        CHECK (
            email = lower(email)
            AND email = btrim(email)
            AND char_length(email) BETWEEN 3 AND 254
        ),
    ADD CONSTRAINT users_password_present_check
        CHECK (password <> '');
```

PHP still performs friendly validation and returns field errors. These checks answer a
different question: may an invalid row exist at all? Email is normalized to lowercase
before registration, so the existing unique constraint plus this normalization check
makes differently cased forms unable to bypass uniqueness.

We name every constraint. A failure mentioning
`users_email_normalized_check` is much easier to diagnose than an anonymous Boolean
expression.

## Make every relationship real

Continue the migration with workspace ownership:

```sql
ALTER TABLE workspaces
    ALTER COLUMN owner_id SET NOT NULL,
    ADD CONSTRAINT workspaces_owner_foreign
        FOREIGN KEY (owner_id)
        REFERENCES users(id)
        ON DELETE RESTRICT,
    ADD CONSTRAINT workspaces_name_length_check
        CHECK (char_length(btrim(name)) BETWEEN 2 AND 50);
```

`RESTRICT` refuses to delete an account while its workspaces still name it as owner.
We have not built account deletion, so silent ownership deletion would be the wrong
policy.

Connect projects and issues to their parents:

```sql
ALTER TABLE projects
    ADD CONSTRAINT projects_workspace_foreign
        FOREIGN KEY (workspace_id)
        REFERENCES workspaces(id)
        ON DELETE CASCADE,
    ADD CONSTRAINT projects_name_length_check
        CHECK (char_length(btrim(name)) BETWEEN 2 AND 60);

ALTER TABLE issues
    ADD CONSTRAINT issues_project_foreign
        FOREIGN KEY (project_id)
        REFERENCES projects(id)
        ON DELETE CASCADE,
    ADD CONSTRAINT issues_title_length_check
        CHECK (char_length(btrim(title)) BETWEEN 2 AND 100),
    ADD CONSTRAINT issues_description_length_check
        CHECK (char_length(description) <= 1000),
    ADD CONSTRAINT issues_status_check
        CHECK (status IN ('open', 'closed'));
```

Our reviewed deletion controllers remain valuable: they determine whether deletion is
allowed and return the right response. `CASCADE` is the last database defense against
orphaned rows if a parent is removed.

Checks reference values on the row being changed. Cross-table rules—such as “an
assignee must belong to this workspace”—will remain authorization queries later;
PostgreSQL does not treat a cross-table `CHECK` as a safe invariant.

## Shape indexes for the queries we already run

PostgreSQL creates indexes for primary keys and unique constraints, but it does not
automatically index the referencing side of every foreign key. Replace our single
relationship-column indexes with the equality-and-order shape used by the application:

```sql
DROP INDEX workspaces_owner_id_index;
DROP INDEX projects_workspace_id_index;
DROP INDEX issues_project_id_index;

CREATE INDEX workspaces_owner_id_index
    ON workspaces (owner_id, id DESC);

CREATE INDEX projects_workspace_id_index
    ON projects (workspace_id, id DESC);

CREATE INDEX issues_project_id_index
    ON issues (project_id, id DESC);
```

Each collection filters on its parent/owner and returns newest IDs first. A composite
index can support both parts without adding a second overlapping index.

Apply the migration:

```bash
php artisan migrate
```

DALT should report only `006_protect_current_invariants.sql` and finish successfully.

## Try to bypass PHP

Send an orphan project directly to PostgreSQL:

```bash
docker compose exec -T db \
  psql -U dalt -d dalt_issue_tracker \
  -v ON_ERROR_STOP=1 \
  -c "INSERT INTO projects (workspace_id, name)
      VALUES (999, 'Orphan project')"
```

The command exits non-zero and names `projects_workspace_foreign`. Test normalized
email in the same way:

```bash
docker compose exec -T db \
  psql -U dalt -d dalt_issue_tracker \
  -v ON_ERROR_STOP=1 \
  -c "INSERT INTO users (name, email, password)
      VALUES ('Valid Name', 'UPPER@example.com', 'hash')"
```

PostgreSQL refuses it with `users_email_normalized_check`. Failed statements leave no
rows behind.

Inspect a table and its named defenses:

```bash
docker compose exec -T db \
  psql -U dalt -d dalt_issue_tracker -c '\d issues'
```

You should see the project foreign key, three checks, and the composite project/ID
index.

On an empty table PostgreSQL may correctly prefer a sequential scan. To prove the
index can satisfy our current workspace query, temporarily disable that cheaper plan
for one explanation only:

```bash
docker compose exec -T db \
  psql -U dalt -d dalt_issue_tracker \
  -c "SET enable_seqscan = off;
      EXPLAIN (COSTS OFF)
      SELECT id, name FROM workspaces
      WHERE owner_id = 1 ORDER BY id DESC;"
```

The plan includes `workspaces_owner_id_index`. This is capability evidence, not a
performance benchmark; Lesson 70 will measure plans with representative data.

```bash
git add database/migrations/006_protect_current_invariants.sql
git commit -m "Protect PostgreSQL invariants"
```

PostgreSQL is ready to reject broken rows. Next we will copy the real SQLite data in
dependency order and require every source row to pass these new defenses.
