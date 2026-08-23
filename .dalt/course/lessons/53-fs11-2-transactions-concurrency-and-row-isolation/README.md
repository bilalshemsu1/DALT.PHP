# FS11.5 — Transactions, concurrency, locks, and isolation

Lesson ID: FS11.5
Lesson format: Concise theory
Part: 11 — PostgreSQL deeper
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Applied
Prerequisites: FS11.4
Last reviewed: 2026-08-23

We will make two sessions collide on the same row, watch an update disappear, and then prevent it in two different ways.

> **Helpful background:** [Transaction boundaries and database failure classification](/learn/lessons/67-fs05-7-transaction-boundaries-and-failures)

## What we will learn

- reproduce a lost update rather than reasoning about one;
- take a row lock with `SELECT … FOR UPDATE`, and see what waiting looks like;
- choose between locking and `SERIALIZABLE`, and handle the retry each implies.

## Read-then-write is where correctness leaks

A transaction makes several statements atomic. It does not stop another transaction from doing the same thing at the same time.

The classic shape is read-modify-write:

```text
A: BEGIN; read priority = 1
B: BEGIN; read priority = 1
A: UPDATE priority = 2; COMMIT
B: UPDATE priority = 2; COMMIT
```

```text
A read 1, B read 1, both added 1. Expected 3, actual 2.
```

Nothing failed. No error was raised, no constraint was violated, and one person's change is simply gone. Both transactions were internally consistent; the pair was not.

PostgreSQL's default isolation level, **read committed**, guarantees that each statement sees only committed data. It does not promise that a value you read is still true when you write.

## A lock makes the second session wait

`SELECT … FOR UPDATE` takes a row lock as part of the read, so nobody else can update that row until the transaction ends:

```sql
BEGIN;
SELECT priority FROM issues WHERE id = 1 FOR UPDATE;
UPDATE issues SET priority = priority + 1 WHERE id = 1;
COMMIT;
```

The second session simply blocks — correct, and invisible. To make the wait observable, give it a deadline:

```sql
SET lock_timeout = '300ms';
```

```text
B could not take the row lock: SQLSTATE 55P03.
B then read the committed 2 and wrote 3. No update was lost.
```

`55P03` is `lock_not_available`. In an application that is a signal to fail fast rather than to hold a request open indefinitely — an unbounded wait is how one slow transaction becomes a queue of stuck requests.

Two rules travel with row locks. Lock the row you are about to change, in the same transaction that changes it. And always lock rows in a consistent order across the codebase: two transactions that lock A-then-B and B-then-A will deadlock, and PostgreSQL will kill one of them.

## `SERIALIZABLE` checks the whole schedule instead

Locking works when you know which row you are protecting. Some invariants are about a *set* — "no more than N high-priority issues in this project" — and there is no single row to lock.

`SERIALIZABLE` makes PostgreSQL verify that the concurrent transactions could have produced their result if run one after another. When they could not, one is refused:

```text
B was rolled back: SQLSTATE 40001. Retry the whole transaction.
Both sessions had counted the same 10000 rows before deciding what to write.
```

`40001` is `serialization_failure`, and it is not an error in the usual sense — it is the mechanism working. Using this level means the application **must** retry the whole transaction, from the first read, because every value it read may now be wrong. Retrying only the failed statement re-uses stale reads and reintroduces the bug.

Choose deliberately:

```text
FOR UPDATE     you know the rows; contention is on specific rows
SERIALIZABLE   the invariant spans a set; you can afford to retry
constraints    the rule can be expressed once, in the schema
```

The third option is the strongest when it applies. A `UNIQUE` constraint or a `CHECK` is enforced under every isolation level and by every client, including the one nobody remembered to update.

## A failed statement aborts the transaction

One more behaviour surprises people the first time:

```text
The insert failed as designed: SQLSTATE 23514.
Every later statement fails too: SQLSTATE 25P02. Roll back first.
```

After any error, PostgreSQL marks the transaction aborted and refuses everything until `ROLLBACK`. Catching an exception and continuing to issue writes does not work — every one of them fails with `25P02`, `in_failed_sql_transaction`. Roll back, then decide what to do.

## Try it

**Workspace:** continue in `.dalt/workspace/fs11-postgres`. If starting fresh, run the setup in FS11.1, then `fs11-1` through `fs11-4` in order.

```bash
cd .dalt/workspace/fs11-postgres
DALT_REPOSITORY_ROOT=/path/to/DALT.PHP php scripts/concurrency.php
```

**Starting state:** the script opens two PDO connections through DALT's own `Database` class and sequences every step by hand, so nothing depends on timing luck.

**Expected result:** four labelled sections.

```text
A read 1, B read 1, both added 1. Expected 3, actual 2.
B could not take the row lock: SQLSTATE 55P03.
B then read the committed 2 and wrote 3. No update was lost.
B was rolled back: SQLSTATE 40001. Retry the whole transaction.
The insert failed as designed: SQLSTATE 23514.
Every later statement fails too: SQLSTATE 25P02. Roll back first.
```

Now delete `FOR UPDATE` from section 2's two `SELECT` statements and rerun. The lock timeout never fires, and the final priority is 2 instead of 3 — the lost update is back.

**Reset:** `docker compose down -v` and re-seed, or simply rerun the script; it resets the row it uses.

## What to notice

Section 1 produced a wrong number with no error anywhere. That is the whole reason concurrency is hard: the failure mode is silence.

Sections 2 and 3 fix the same class of problem in opposite directions. Locking makes the other session wait; serialisable lets both proceed and refuses one afterwards. The first costs latency, the second costs a retry loop, and neither is free.

## Common mistakes

- Assuming a transaction is a lock. It is atomicity, not exclusivity.
- `SELECT` then `UPDATE` in the same transaction with no `FOR UPDATE`.
- Waiting on a lock with no `lock_timeout`, so one slow transaction stalls everything behind it.
- Using `SERIALIZABLE` without a retry loop, which converts a rare conflict into a user-visible error.
- Catching a database exception and continuing to write on the aborted transaction.

## Check your understanding

1. What does read committed guarantee, and what does it not?
2. Why does `SELECT … FOR UPDATE` belong in the same transaction as its `UPDATE`?
3. What must an application do when it receives `40001`, and why is retrying one statement wrong?
4. Why does every statement after a failed one return `25P02`?

<details><summary>Check your answers</summary>

1. Each statement sees only committed data. It does not promise a value you read is still current when you write it.
2. The lock lasts only until the transaction ends, so locking in a separate transaction protects nothing.
3. Retry the entire transaction from its first read. Every value it read may have been invalidated by the transaction that won.
4. PostgreSQL marks the transaction aborted after any error and refuses further work until it is rolled back.
</details>

## Next

Next we will push tenant isolation into the database itself, so a forgotten `WHERE` clause cannot leak another workspace's rows.

<details><summary>Maintainer source record</summary>

- Source dossier: `POSTGRESQL_DOCS.md` and `FSO_RELATIONAL_DATABASES.md`.
- Official sources: PostgreSQL 18 documentation on transaction isolation, explicit locking and row-level locks, `lock_timeout`, and the SQLSTATE error-code appendix (`55P03`, `40001`, `23514`, `25P02`).
- Versions: PostgreSQL 18.4 (`postgres@sha256:9a8afca5…`); PHP 8.4 with PDO pgsql.
- Consulted: 2026-08-23.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 12, FS11.5.
- DALT files inspected: `framework/Core/Database.php`, `postgres-depth-lab`, the Part 11 track manifest, and the former FS11.2 page.
- Extracted material: "make one operation atomic", "reproduce a concurrent decision", "protect the invariant deliberately", "isolation is a contract, not a magic switch", and "constraints are concurrent correctness tools" from the former FS11.2. Its RLS material moves to FS11.6.
- Verified in the lab: every line of output above is real, produced by two PDO connections opened through DALT's own `Database` class.
</details>
