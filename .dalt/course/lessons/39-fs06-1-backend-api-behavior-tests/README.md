# FS06.1 — Test backend behavior through HTTP

Lesson ID: FS06.1
Lesson format: Concise theory
Part: 06 — Testing, users, and authorization
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Foundation
Prerequisites: FS05.7
Last reviewed: 2026-08-22

We will turn an API promise into repeatable evidence by observing both its response and its database effect.

> **Helpful background:** [Transaction boundaries and database failures](/learn/lessons/67-fs05-7-transaction-boundaries-and-failures)

## What we will learn

- test behavior at the request boundary instead of testing implementation details;
- keep every test's data deterministic and isolated;
- prove that a test detects the defect it claims to detect.

## A behavior test starts with a promise

“The store method works” describes code. “A valid issue request returns `201`, publishes the agreed JSON fields, and stores one issue” describes behavior a client depends on.

That promise has two observable results:

```text
known state → request → route and application → response
                                      └──────→ stored state
```

Checking only the response leaves a hole: a handler can return a convincing `201` without committing a row. Checking only PostgreSQL leaves a different hole: the row can be correct while the route returns the wrong status or leaks an internal column.

A useful create test observes both:

```php
test('a valid issue is created, returned, and stored', function () {
    $response = $this->api->handle('POST', '/api/issues', [
        'project_id' => $this->projectId,
        'title' => 'Write API tests',
        'priority' => 'high',
    ]);

    expect($response['status'])->toBe(201);
    expect($response['body']['data']['title'])->toBe('Write API tests');
    expect(countRows($this->api->pdo(), 'issues'))->toBe(1);
    expect(countRows($this->api->pdo(), 'activity'))->toBe(1);
});
```

The lab uses a compact method-and-path request harness so we can see the complete mechanism. In the application, important API tests should drive DALT's request boundary so routing and middleware are included too. Calling a small parser or controller directly is still useful when that unit is the subject; it is not evidence that the HTTP route works.

## Negative behavior must be exact

This assertion is weak:

```php
expect($response['status'])->not->toBe(200);
```

A `500` caused by a misspelled column satisfies it. The test passes while the application is broken in an entirely different way.

State the actual contract and prove the rejected request changed nothing:

```php
expect($response['status'])->toBe(422);
expect($response['body']['error']['code'])->toBe('validation_failed');
expect($response['body']['error']['fields']['title'])->toBe('Required');
expect(countRows($this->api->pdo(), 'issues'))->toBe(0);
```

The final assertion matters. Returning a beautiful validation error after inserting the row is still a corrupt implementation.

We can also pin the public field set without comparing a whole response containing generated IDs or timestamps:

```php
expect(array_keys($response['body']['data']))
    ->toBe(['id', 'projectId', 'title', 'status', 'priority']);
```

This catches an accidental column leak while allowing values that legitimately change.

## Every test owns its starting state

Tests become order-dependent when they share rows, generated IDs, or yesterday's manual data. The lab creates a fresh in-memory database before every test:

```php
beforeEach(function () {
    $this->api = IssueApi::withSchema();
    $this->projectId = $this->api->seedProject('Website');
});
```

Each case creates the records it needs and keeps returned IDs. It does not assume issue `1` exists because another test created it.

SQLite makes this small experiment portable; it is not permission to test PostgreSQL-specific application behavior on a different engine. A real project should use an explicitly separate PostgreSQL test database or disposable schema. Its reset strategy must make a test pass alone, after another test, and on repeated suite runs.

A transaction wrapper is often a fast reset, but it is the wrong wrapper for a test whose subject is commit, rollback, multiple connections, or locking. Use a deliberate reset for those cases.

## Prove the test can become red

A green test is useful only if losing the promised behavior makes it fail. The lab contains a two-write event. Its failure test checks database state after the exception:

```php
expect(fn () => $this->api->handle('POST', '/api/issues', [
    'project_id' => $this->projectId,
    'title' => str_repeat('long ', 12),
]))->toThrow(PDOException::class);

expect(countRows($this->api->pdo(), 'issues'))->toBe(0);
expect(countRows($this->api->pdo(), 'activity'))->toBe(0);
```

Catching an exception is not evidence of rollback. The two zero counts rule out the plausible fake: the first insert committed before the second one failed.

Temporarily replacing `rollBack()` with `commit()` should make this test red. Bypassing validation should make the validation tests red. Read the first failure before restoring the implementation: a syntax error or broken setup is not evidence that the intended behavior was covered.

During development, run the smallest relevant test for fast feedback. Before finishing, run the complete suite so shared-state leaks and accidental focus markers cannot hide.

## Try it

**Workspace:** create a disposable copy:

```bash
mkdir -p .dalt/workspace
cp -r .dalt/course/fullstack/api-behavior-tests-lab \
  .dalt/workspace/fs06-behavior-tests
```

**Starting state:** do not edit the copied files yet. From the repository root, run:

```bash
php vendor/bin/pest .dalt/workspace/fs06-behavior-tests/tests \
  --bootstrap=.dalt/workspace/fs06-behavior-tests/bootstrap.php
```

The result is `10 passed`.

Now open `.dalt/workspace/fs06-behavior-tests/src/IssueApi.php`, replace the rollback inside the `PDOException` catch with `commit()`, and rerun the command. The suite must fail at `a failed second write rolls back the first`. Restore `rollBack()` and confirm all ten tests pass again.

**Expected result:** the clean implementation passes; the partial-commit fake fails because an issue row survives.

**Reset:** delete `.dalt/workspace/fs06-behavior-tests`.

## What to notice

The test name describes a public promise. Response assertions prove what the client receives; database assertions prove the lasting effect. Isolation makes the result repeatable, and the deliberate sabotage proves the test is sensitive to the defect it names.

## Check your understanding

1. Why are a `201` assertion and a row-count assertion different evidence?
2. Why is “not 200” too weak for invalid input?
3. What makes test data deterministic?
4. Why must we watch an important test fail deliberately?

<details><summary>Check your answers</summary>

1. The response proves the public HTTP contract; the row count proves the write actually persisted.
2. An unrelated server error also passes it. Exact status, error code, and unchanged state describe the required behavior.
3. Every test owns known setup, uses returned identifiers, and resets independently of development data and test order.
4. It demonstrates that removing the promised behavior really makes the test red, rather than merely exercising code.
</details>

## Next

With backend behavior pinned, we can add users and password hashes without guessing which contracts changed.

<details><summary>Maintainer source record</summary>

- Source dossier: `FSO_PART_04.md`.
- Official sources: Pest writing-tests and expectations documentation; PostgreSQL transaction documentation.
- Versions: PHP 8.4; Pest 3.8 from the repository; SQLite only for the bounded experiment.
- Consulted: 2026-08-22.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 8, FS06.1.
- DALT files inspected: `tests/Support/ApplicationTestClient.php`, `tests/Feature/RequestLifecycleTest.php`, `framework/Core/Database.php`, and the existing FS06.1 lab.
- Reused material: response-plus-state assertions, isolated fixtures, exact negative behavior, transaction evidence, and deliberate sabotage from the former FS06.1.
</details>
