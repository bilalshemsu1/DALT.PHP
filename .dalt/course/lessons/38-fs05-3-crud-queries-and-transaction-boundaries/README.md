# FS05.6 — PDO, prepared statements, CRUD, and `RETURNING`

Lesson ID: FS05.6
Lesson format: Concise theory
Part: 05 — DALT API and PostgreSQL
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Foundation
Prerequisites: FS05.5
Last reviewed: 2026-08-22

We will connect PHP to PostgreSQL with prepared SQL and return the rows the database actually created or changed.

> **Helpful background:** [Migrations, constraints, and foundational indexes](/learn/lessons/66-fs05-5-migrations-constraints-and-indexes)

## What we will learn

- use DALT's PDO-backed database layer for create, read, update, and delete;
- keep request values separate from SQL structure;
- use PostgreSQL `RETURNING` to classify and expose mutations honestly.

## DALT keeps PDO visible

`Core\Database` creates a PDO connection with exception mode, associative fetches, stringified fetches disabled, and native prepared statements. Its small query surface is:

```php
$database->query($sql, $parameters)->find(); // one row or false
$database->query($sql, $parameters)->get();  // a list, possibly empty
$database->getConnection();                  // PDO for transaction control
```

There is no ORM translating application method names into unknown SQL. We write the query and can run the same SQL in `psql` when debugging.

## Values belong in parameters

Never join request data into SQL text:

```php
// Unsafe: title becomes part of SQL structure.
$sql = "INSERT INTO issues (project_id, title) VALUES ($projectId, '$title')";
```

A perfectly valid title can contain an apostrophe. Validation does not make interpolation safe, and a future validation change should not alter query safety.

Use placeholders and a separate values array:

```php
$issue = $database->query(
    'INSERT INTO issues (project_id, title) VALUES (?, ?)
     RETURNING id, project_id, title, status, priority',
    [$projectId, $title],
)->find();
```

PDO prepares the SQL structure, then supplies the values. `Don't interpolate me` remains one title instead of ending a quoted SQL string.

Placeholders represent values, not identifiers or keywords. `ORDER BY ?` cannot safely select a column. If a request chooses sorting, map its small public vocabulary to SQL written by the server:

```php
$columns = [
    'created' => 'i.created_at',
    'title' => 'i.title',
];

$orderBy = $columns[$request->query('sort')] ?? 'i.created_at';
$sql .= " ORDER BY {$orderBy} DESC";
```

Only strings from our map enter the SQL structure. The request never does.

## Create and return the stored row

PostgreSQL can return columns from rows changed by `INSERT`, `UPDATE`, and `DELETE`. On creation, this avoids a second lookup and exposes server defaults:

```php
$created = $database->query(
    'INSERT INTO issues (project_id, title, priority)
     VALUES (?, ?, ?)
     RETURNING id, project_id, title, status, priority, created_at, updated_at',
    [$projectId, $title, $priority],
)->find();

return Response::json(issueResponse($created), 201);
```

The INSERT omits `status`; PostgreSQL supplies its `todo` default. Returning the stored row means React renders that authority rather than guessing the same default locally.

## Read relations with a join

List the issues for one project and attach the project identity the API needs:

```php
$rows = $database->query(
    'SELECT i.id, i.project_id, i.title, i.status, i.priority,
            i.created_at, i.updated_at, p.slug AS project_slug
     FROM issues AS i
     JOIN projects AS p ON p.id = i.project_id
     WHERE i.project_id = ?
     ORDER BY i.id',
    [$projectId],
)->get();
```

An empty result is an empty collection, not an exception. `get()` returns `[]`, which the API can map to a 200 JSON list.

A single-resource lookup uses `find()` and classifies absence explicitly:

```php
$row = $database->query(
    'SELECT id, project_id, title, status, priority
     FROM issues WHERE id = ?',
    [$issueId],
)->find();

if ($row === false) {
    return Response::json([
        'error' => ['code' => 'not_found', 'message' => 'Issue not found.'],
    ], 404);
}
```

`findOrFail()` is useful for HTML routes using DALT's general 404 renderer. A JSON controller often uses `find()` so it can preserve the API's error shape.

## Update and delete without a second query

An update can return its new state:

```php
$updated = $database->query(
    'UPDATE issues
     SET status = ?, updated_at = CURRENT_TIMESTAMP
     WHERE id = ?
     RETURNING id, project_id, title, status, priority, updated_at',
    [$status, $issueId],
)->find();
```

If no row matches, `RETURNING` produces no row and `find()` is `false`: that becomes the same 404 contract as a missing read.

Delete can make absence equally visible:

```php
$deleted = $database->query(
    'DELETE FROM issues WHERE id = ? RETURNING id',
    [$issueId],
)->find();

if ($deleted === false) {
    return Response::json([
        'error' => ['code' => 'not_found', 'message' => 'Issue not found.'],
    ], 404);
}

return new Response(status: 204);
```

The successful HTTP response has no body, even though the controller used the returned ID internally as proof.

## Map database rows into the API shape

PostgreSQL columns use snake case and numeric IDs may require deliberate conversion for the public contract. Keep that mapping in one function:

```php
function issueResponse(array $row): array
{
    return [
        'id' => (string) $row['id'],
        'projectId' => (string) $row['project_id'],
        'title' => $row['title'],
        'status' => $row['status'],
        'priority' => $row['priority'],
    ];
}
```

Database rows are an internal representation, not automatically our JSON contract.

## Try it

**Workspace:** continue in `.dalt/workspace/fs05-postgres` with both migrations applied.

**Starting state:** PostgreSQL is healthy and `php scripts/migrate.php --through=002` reports no pending migrations. Run:

```bash
php scripts/crud.php
```

The exact output is:

```text
created: 1 Don't interpolate me [todo]
listed: 1
updated: 1 [done]
deleted: 1
remaining: 0
```

Run the script again. It prints the same result because it deliberately truncates and reseeds its three application tables before the CRUD sequence.

**Expected result:** the apostrophe survives prepared creation, the stored default returns as `todo`, the relational read finds one row, update returns `done`, and deletion leaves zero issues.

**Reset:** keep the migrated service for FS05.7, or run `docker compose down -v` and delete the workspace.

## What to notice

Each query has one observable job. Parameters carry values, SQL text carries structure, and `RETURNING` removes guesses about what a mutation changed. HTTP classification can now follow database evidence.

## Check your understanding

1. Why is validated text still unsafe to interpolate?
2. Can a placeholder choose an `ORDER BY` column?
3. What creation fact does `RETURNING` reveal here?
4. How does an update prove that its target was missing?

<details><summary>Check your answers</summary>

1. Valid application text can still contain SQL syntax characters, and validation rules can change.
2. No. Identifiers are SQL structure; choose them through a server-owned allowlist.
3. It returns the generated ID and stored defaults without a second query.
4. `UPDATE ... RETURNING` returns no row, so `find()` yields `false`.
</details>

## Next

Single statements are atomic already; next we will group multiple writes that represent one business event and classify database failures safely.

<details><summary>Maintainer source record</summary>

- Source dossier: PostgreSQL documentation research notes; Full Stack Open Relational Databases research notes.
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: PostgreSQL 18 documentation for DML, joins, and `RETURNING`; PHP manual for PDO prepared statements and exception mode.
- Versions: PostgreSQL 18.4 pinned by the shared lab image digest; PHP PDO from the repository runtime.
- Consulted: 2026-08-22.
- Curriculum authority: DALT Fullstack theory curriculum, Batch 7, FS05.6.
- DALT files inspected: `framework/Core/Database.php`, `Response.php`, API fixture mapping, and database unit tests.
- Reused material: parameter/structure separation, relation-aware reads, row mapping, CRUD, and `RETURNING` material split from former FS05.3.
</details>
