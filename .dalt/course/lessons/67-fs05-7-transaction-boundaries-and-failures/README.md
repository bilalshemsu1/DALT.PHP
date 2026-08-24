# FS05.7 — Transaction boundaries and database failure classification

Lesson ID: FS05.7
Lesson format: Concise theory
Part: 05 — DALT API and PostgreSQL
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Foundation
Prerequisites: FS05.6
Last reviewed: 2026-08-22

We will make a multi-write business event all-or-nothing and classify database failures without leaking implementation details.

> **Helpful background:** [PDO, prepared statements, CRUD, and RETURNING](/learn/lessons/38-fs05-3-crud-queries-and-transaction-boundaries)

## What we will learn

- draw a transaction around one business fact rather than around arbitrary code;
- commit complete work and roll back every partial failure;
- use SQLSTATE for stable failure classification while keeping raw diagnostics private.

## One statement is already atomic

PostgreSQL does not leave half an `UPDATE` behind. A single SQL statement either succeeds or fails as a unit. We need an explicit transaction when several statements together represent one fact.

Suppose creating an issue must also record its first activity entry:

```text
issue row + “created” activity row = one issue-created event
```

If the issue commits but activity fails, the database tells two different stories depending on which table we read. Catching the exception does not repair that inconsistency. The transaction boundary must include both writes.

Do not wrap an entire HTTP request in a transaction by habit. Validation, JSON decoding, and remote network calls do not become safer merely because a database transaction remains open. Begin immediately before the related database work and finish immediately after it.

## PDO controls the boundary

DALT exposes its PDO connection deliberately:

```php
$pdo = $database->getConnection();

$pdo->beginTransaction();

try {
    $issue = $database->query(
        'INSERT INTO issues (project_id, title) VALUES (?, ?) RETURNING id',
        [$projectId, $title],
    )->find();

    $database->query(
        'INSERT INTO issue_activity (issue_id, action) VALUES (?, ?)',
        [$issue['id'], 'created'],
    );

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $exception;
}
```

`beginTransaction()` turns off autocommit for this connection. `commit()` makes both writes durable together. `rollBack()` removes the work since the transaction began.

The `inTransaction()` check matters because the failure might occur before a transaction begins, or another layer might already have ended it. After catching a PostgreSQL statement error, roll back before trying another query on that connection: the transaction is in a failed state until rollback.

Always rethrow an unexpected exception after cleanup. Swallowing it can make the controller return success even though nothing committed.

## Commit only after the complete event succeeds

Do not commit between the two writes:

```php
// Wrong boundary.
$issue = createIssue();
$pdo->commit();
recordActivity($issue['id']);
```

Once committed, the first write cannot be rolled back by a later catch block. The business event, not the number of lines or functions, determines the boundary.

The reverse mistake is an oversized transaction:

```text
begin → write issue → call email service → wait → write activity → commit
```

The database transaction stays open while an unrelated network call waits. That holds database resources and complicates retry behavior. Commit the database event, then arrange external side effects through a separate dependable design when the product needs them.

## Constraint failures have stable codes

PDO throws `PDOException` because DALT enables exception mode. PostgreSQL supplies a five-character SQLSTATE code. Applications should inspect that stable code rather than matching human text that can change or be localized:

```php
catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $sqlState = $exception->errorInfo[0]
        ?? (string) $exception->getCode();

    if ($sqlState === '23505') {
        return Response::json([
            'error' => [
                'code' => 'already_exists',
                'message' => 'That value is already in use.',
            ],
        ], 409);
    }

    throw $exception;
}
```

Useful integrity codes include:

| SQLSTATE | PostgreSQL condition |
|---|---|
| `23502` | not-null violation |
| `23503` | foreign-key violation |
| `23505` | unique violation |
| `23514` | check violation |

Do not automatically expose every constraint failure as a client mistake. Ordinary validation should catch expected field problems first. A foreign-key violation may mean the parent disappeared between validation and insertion, while another error may reveal a server bug. Map only cases whose meaning is clear at this operation; report and rethrow the rest for the application error boundary.

Never return the raw PostgreSQL message to the browser. It can expose table and constraint names, SQL fragments, and internal values. Log diagnostic detail on the server; return the API's stable code and safe message.

## Failed transactions need evidence after rollback

The trustworthy proof is not “an exception was caught.” Query the database after rollback:

```php
$rows = $database->query(
    'SELECT id FROM issues WHERE title = ?',
    ['Rolled back issue'],
)->get();

echo count($rows); // 0
```

The exception proves the second write failed. The zero-row query proves the first write did not leak through. Both pieces are needed to demonstrate atomicity.

Generated identity sequences are not transactional counters: a failed insert may consume a number. Gaps in IDs are normal and are another reason not to treat numeric IDs as consecutive business numbering.

## Try it

**Workspace:** continue in `.dalt/workspace/fs05-postgres`.

**Starting state:** PostgreSQL is healthy. Apply the new activity migration:

```bash
php scripts/migrate.php --through=003
```

It applies `003_create_issue_activity.sql`. Run the transaction experiment:

```bash
php scripts/transaction.php
```

The exact output is:

```text
committed issue: 1
committed activity: 1
failure SQLSTATE: 23514
rolled back issue count: 0
```

The first transaction inserts a valid issue and activity. The second inserts an issue, then deliberately violates `issue_activity_action_allowed`. The catch rolls back before querying for the failed issue.

Run this independent database check:

```bash
docker compose exec -T db psql -U dalt -d dalt_course \
  -c "SELECT i.title, a.action FROM issues i JOIN issue_activity a ON a.issue_id = i.id;"
```

It shows only `Committed issue | created`.

**Expected result:** the valid two-write event commits completely; the invalid event reports check-violation SQLSTATE `23514` and leaves no issue row behind.

**Reset:** run `docker compose down -v`, return to the repository root, and delete `.dalt/workspace/fs05-postgres`.

## What to notice

The transaction is shaped around one business promise. Rollback restores database truth before failure is classified. SQLSTATE supports stable server-side decisions, while the browser receives only the public API contract.

## Check your understanding

1. When does a workflow earn an explicit transaction?
2. Why must rollback happen before another query?
3. Why inspect SQLSTATE instead of exception text?
4. What proves the first write did not survive the failed second write?

<details><summary>Check your answers</summary>

1. When multiple database operations together express one fact that must not be partial.
2. PostgreSQL leaves the transaction failed after a statement error until it is rolled back.
3. Codes are stable and machine-readable; text can change, localize, and expose internals.
4. A post-rollback query finds zero rows for the deliberately failed issue.
</details>

## Next

Part 05 now has an honest API and persistent relational core; Batch 8 begins by testing backend behavior through HTTP.

<details><summary>Maintainer source record</summary>

- Source dossier: PostgreSQL documentation research notes; Full Stack Open Relational Databases research notes.
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: PostgreSQL 18 transaction tutorial, transaction commands, failed-transaction behavior, and SQLSTATE appendix; PHP manual for PDO transactions and exceptions.
- Versions: PostgreSQL 18.4 pinned by the shared lab image digest; PHP PDO from the repository runtime.
- Consulted: 2026-08-22.
- Curriculum authority: DALT Fullstack theory curriculum, Batch 7, FS05.7.
- DALT files inspected: `framework/Core/Database.php`, `Migration.php`, response/error handling, and the existing API behavior transaction tests.
- Reused material: business-event boundaries, PDO begin/commit/rollback flow, rollback evidence, and failure classification split from former FS05.3.
</details>
