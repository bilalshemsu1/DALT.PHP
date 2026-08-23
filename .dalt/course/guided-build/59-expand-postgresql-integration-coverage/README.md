# Expand PostgreSQL integration coverage

Our backend tests have grown one endpoint at a time, and it shows. Every file opens
with the same wall of `INSERT` statements, and each new route arrived with a test for
the case we happened to think about. We will replace the duplicated setup with
factories that go through the real schema, add one table that describes what every
protected endpoint owes every role, and add the cases a status code cannot see.

> **Helpful background:** PostgreSQL's [constraint documentation](https://www.postgresql.org/docs/18/ddl-constraints.html)
> covers the `CHECK`, `UNIQUE`, and foreign-key rules these tests lean on.

## Replace copied fixtures with factories

Open `tests/Feature/IssueApiTest.php` and look at its `beforeEach`. Five `INSERT`
statements build a world by hand, and `tests/Feature/WorkspaceAuthorizationTest.php`
and `tests/Feature/TransactionTest.php` each build a nearly identical one. When we
added `priority`, three files needed editing. When we add the next column, three files
will need editing again.

Create `tests/Support/Factory.php`:

```php
final class Factory
{
    private int $sequence = 0;

    public function __construct(private readonly Database $database)
    {
    }

    public function user(array $attributes = []): int
    {
        $n = ++$this->sequence;

        return $this->insert(
            'INSERT INTO users (name, email, password) VALUES (:name, :email, :password) RETURNING id',
            [
                'name' => $attributes['name'] ?? "User {$n}",
                'email' => $attributes['email'] ?? "user{$n}@example.test",
                'password' => password_hash($attributes['password'] ?? 'correct-horse', PASSWORD_DEFAULT),
            ],
        );
    }
```

Two decisions in that method matter more than they look.

The counter gives every factory row a unique email without the test having to invent
one. `users.email` is `UNIQUE`, so a factory that hard-coded an address would make the
second call fail for a reason that has nothing to do with what the test is checking.

The password is really hashed. A factory that stored `'unused'` would build a user no
login test could ever authenticate, and we would end up with two kinds of user in our
suite: the kind factories make and the kind that can log in.

The rest of the factory follows the same rule — **every method issues the INSERT a
controller would issue.** A factory that wrote directly past a constraint would let a
test pass against data our application can never actually hold:

```php
public function issue(int $projectId, array $attributes = []): int
{
    $n = ++$this->sequence;

    return $this->insert(
        'INSERT INTO issues (project_id, title, description, status, assignee_id, priority, due_date)
         VALUES (:project_id, :title, :description, :status, :assignee_id, :priority, :due_date)
         RETURNING id',
        [
            'project_id' => $projectId,
            'title' => $attributes['title'] ?? "Issue {$n}",
            'description' => $attributes['description'] ?? '',
            'status' => $attributes['status'] ?? 'open',
            'assignee_id' => $attributes['assignee_id'] ?? null,
            'priority' => $attributes['priority'] ?? 'medium',
            'due_date' => $attributes['due_date'] ?? null,
        ],
    );
}
```

`RETURNING id` matters here. We used `lastInsertId()` in earlier lessons; asking
PostgreSQL to return the row it just wrote is both clearer and safe inside a
transaction that inserts into several tables.

The invitation factory has to do one more thing, because Lesson 45 deliberately stores
only a hash:

```php
public function invitation(int $workspaceId, int $inviterId, array $attributes = []): array
{
    $token = $attributes['token'] ?? bin2hex(random_bytes(32));

    $id = $this->insert(
        "INSERT INTO workspace_invitations
            (workspace_id, email, inviter_id, role, token_hash, expires_at, accepted_at, revoked_at)
         VALUES (:workspace_id, :email, :inviter_id, :role, :token_hash,
                 CURRENT_TIMESTAMP + CAST(:expires_in AS interval), :accepted_at, :revoked_at)
         RETURNING id",
        [/* … */ 'token_hash' => hash('sha256', $token), /* … */],
    );

    return ['id' => $id, 'token' => $token];
}
```

It hashes exactly the way the invitation controller does and hands the plaintext back.
Without the plaintext no test could ever exercise acceptance — the only copy would be
the one we threw away.

Finally, the shape almost every test needs:

```php
public function ownedWorkspace(?int $ownerId = null, array $attributes = []): array
{
    $ownerId ??= $this->user();
    $workspaceId = $this->workspace($attributes);
    $this->membership($workspaceId, $ownerId, 'owner');

    return ['id' => $workspaceId, 'ownerId' => $ownerId];
}
```

A workspace with no owner is not a state our application can produce, so the factory
does not offer one by accident.

`tests/Feature/TransactionTest.php` now reads as what it is testing rather than how it
built its data:

```php
$workspace = $factory->ownedWorkspace(null, ['name' => 'Studio']);
$projectId = $factory->project($workspace['id'], ['name' => 'Launch']);
$factory->issue($projectId, ['title' => 'Keep me']);
```

## Describe the policy in one table

Our authorization tests grew one endpoint at a time, which means an endpoint added
later could ship with no authorization test at all and nothing would notice. A matrix
cannot forget an endpoint, because a missing row is a missing row.

Create `tests/Feature/PolicyMatrixTest.php`. The world is built once per test:

```php
$owner = $factory->user(['name' => 'Ada', 'email' => 'ada@example.test']);
$member = $factory->user(['name' => 'Grace', 'email' => 'grace@example.test']);
$outsider = $factory->user(['name' => 'Alan', 'email' => 'alan@example.test']);

$workspace = $factory->ownedWorkspace($owner, ['name' => 'Studio']);
$factory->membership($workspace['id'], $member, 'member');
```

Each row names a method, a description, a target, an input, and the status each role
must receive:

```php
['GET', 'workspace members', static fn (array $w) => $workspace($w) . '/members', [], 200, 200, 404],
['POST', 'create issue', static fn (array $w) => $project($w) . '/issues', ['title' => 'Another issue'], 201, 201, 404],
['POST', 'rename workspace', $workspace, ['name' => 'Renamed'], 200, 403, 404],
```

Read the last two columns carefully, because they encode a security decision we made
back in Lesson 43 and have never written down in one place.

A **member** who tries an owner-only action gets **403**: we know who they are, and
they lack one capability. An **outsider** gets **404**, not 403 — because
`WorkspaceAccess::findOrFail` joins through memberships, a workspace they cannot see
does not exist as far as the response is concerned. Answering 403 would confirm that
the workspace is real, which is information an outsider has not earned.

The test walks the roles from least to most privileged:

```php
foreach ($attempts as [$role, $userId, $email, $expected]) {
    $uri = $target($world);
    matrixAs($userId, $email);

    expect(matrixStatus($method, $uri, $input))
        ->toBe($expected, "{$method} {$name} must answer a {$role} with {$expected}.");
}
```

Notice that `$target` is a closure and it runs **again before every role**. The first
version of this test used a fixed URL, and it reported a permission bug that did not
exist: on `DELETE delete issue` the member's perfectly legitimate 200 destroyed the
issue, so the owner's turn arrived at a 404. A matrix has to give every role an equal
starting point, so destructive rows create a fresh target each time:

```php
[
    'DELETE',
    'delete issue',
    static function (array $w): string {
        $issue = $w['factory']->issue($w['project'], ['title' => 'Disposable issue']);

        return "/api/workspaces/{$w['workspace']}/projects/{$w['project']}/issues/{$issue}";
    },
    [], 200, 200, 404,
],
```

`abort()` throws rather than returning, so the helper catches it and reads the status
off the exception:

```php
try {
    return matrixRouter()->route($uri, $request->method(), $request)->status();
} catch (HttpException $exception) {
    return $exception->statusCode;
}
```

## Make the matrix unable to miss a route

A table only helps if it stays complete. Add a second test that reads our own route
file and insists every workspace-scoped API route has a row:

```php
preg_match_all(
    '/\$router->(get|post|delete)\(\x27(\/api\/workspaces[^\x27]*)\x27/',
    (string) $routes,
    $matches,
    PREG_SET_ORDER,
);
```

Then compare that list with the matrix, by running each row's target against a
symbolic world whose factory returns placeholders instead of real ids. One route is
deliberately exempt and says so in the code — leaving a workspace is self-service, and
the member the other rows treat as the denied caller is exactly the person allowed to
do it.

Run it, and it earns its place immediately:

```text
These workspace API routes have no policy-matrix row:
GET /api/workspaces, POST /api/workspaces,
POST /api/workspaces/{workspace}/members/{member},
DELETE /api/workspaces/{workspace}/members/{member},
DELETE /api/workspaces/{workspace},
POST /api/workspaces/{workspace}/projects/{project},
DELETE /api/workspaces/{workspace}/projects/{project},
POST /api/workspaces/{workspace}/projects/{project}/issues/{issue},
DELETE /api/workspaces/{workspace}/projects/{project}/issues/{issue},
DELETE /api/workspaces/{workspace}/projects/{project}/issues/{issue}/comments/{comment}
```

Ten endpoints we had shipped with no role comparison at all. Fill them in, and all
twenty-six rows pass — our authorization was already right, we simply had no record of
it.

## Prove the matrix would notice a shortcut

A test that passes tells us nothing until we have seen it fail for the right reason.
The shortcut worth checking is the one a tired afternoon produces: dropping the
membership join from `WorkspaceAccess::findOrFail` and looking the workspace up
directly, because "we check the role afterwards anyway".

Try it. Replace the join with a lookup that defaults a non-member to `member`:

```php
'SELECT workspaces.id, workspaces.name, workspaces.created_at,
        COALESCE((SELECT role FROM workspace_memberships
                  WHERE workspace_id = workspaces.id
                    AND user_id = :user_id), \'member\') AS role
 FROM workspaces
 WHERE workspaces.id = :id'
```

Every owner-only row still passes — the role check still runs. But every row with an
outsider fails at once:

```text
GET workspace projects must answer a outsider with 404.
GET workspace members must answer a outsider with 404.
GET workspace labels must answer a outsider with 404.
GET project issues must answer a outsider with 404.
```

That is the shape of a real breach: an authenticated stranger reading another team's
work. Put the join back and the suite returns to green.

## Test the rules the database enforces alone

Create `tests/Feature/DataIntegrityTest.php`. Our constraints have accumulated across
ten lessons and nothing checks that they still exist:

```php
$refusals = [
    'a priority outside the allowed set' => fn () => $factory->issue($project, ['priority' => 'catastrophic']),
    'a label name of one character' => fn () => $factory->label($workspace['id'], ['name' => 'x']),
    'a label colour outside the palette' => fn () => $factory->label($workspace['id'], ['color' => 'chartreuse']),
    'a comment with a blank body' => fn () => $factory->comment($issue, $workspace['ownerId'], ['body' => '   ']),
    'an activity event nobody defined' => fn () => $factory->activity($issue, $workspace['ownerId'], 'issue.vanished'),
    'an issue in a project that does not exist' => fn () => $factory->issue(999_999),
];

foreach ($refusals as $description => $attempt) {
    expect($attempt)->toThrow(PDOException::class, message: "The database accepted {$description}.");
}
```

This is where the "factories use the real schema" rule pays for itself. Because
`Factory::issue()` writes through the same `INSERT` a controller does, a factory call
is a perfectly good way to ask the database what it will refuse.

The label rule is worth its own two assertions, because it is a rule about *scope*:

```php
$factory->label($workspace['id'], ['name' => 'Backend']);
expect(fn () => $factory->label($workspace['id'], ['name' => 'backend']))->toThrow(PDOException::class);

$other = $factory->ownedWorkspace();
expect($factory->label($other['id'], ['name' => 'Backend']))->toBeGreaterThan(0);
```

Two workspaces may both have a "Backend" label; one workspace may not have it twice,
whatever the casing.

## Test that a failure leaves nothing behind

Lesson 40 put multi-row changes inside transactions. Nothing yet proves the rollback:

```php
$connection->beginTransaction();
try {
    $factory->comment($issue, $workspace['ownerId'], ['body' => 'This one is fine.']);
    $factory->activity($issue, $workspace['ownerId'], 'not.a.real.event');
    $connection->commit();
} catch (PDOException) {
    $connection->rollBack();
}

expect($after)->toBe($before, 'The valid half of a failed transaction was committed.')
    ->and($connection->inTransaction())->toBeFalse();
```

The first write is valid and the second is refused. If the boundary is wrong, the
comment survives and the count moves.

Then the product-level version — deleting a project must take its issues, comments,
activity, and label attachments with it, while leaving the workspace's labels alone:

```php
expect($response->status())->toBe(200)
    ->and($remaining('projects'))->toBe(0)
    ->and($remaining('issues'))->toBe(0)
    ->and($remaining('comments'))->toBe(0)
    ->and($remaining('issue_activity'))->toBe(0)
    ->and($remaining('issue_labels'))->toBe(0)
    ->and($remaining('labels'))->toBe(1);
```

That last line is the interesting one. A label belongs to the workspace, not to the
project that happened to use it.

## Test that pages do not overlap

Lesson 54 added pagination under a deterministic order. Seed twenty-five issues and
check the pages are actually disjoint:

```php
expect($firstIds)->toHaveCount(10)
    ->and($secondIds)->toHaveCount(10)
    ->and($thirdIds)->toHaveCount(5)
    ->and(array_intersect($firstIds, $secondIds))->toBe([])
    ->and(array_intersect($secondIds, $thirdIds))->toBe([])
    ->and(count(array_unique([...$firstIds, ...$secondIds, ...$thirdIds])))->toBe(25);
```

Then ask for page two a second time and require the same ids. That is what
"deterministic" means, and it is the assertion that would fail if someone removed the
tie-breaking column from the `ORDER BY`.

## Count the queries a list makes

Here is the case no status code can see. Add `tests/Support/CountingDatabase.php`:

```php
final class CountingDatabase extends Database
{
    public function query(string $query, array $params = []): static
    {
        if ($this->recording) {
            $this->statements[] = $query;
        }

        return parent::query($query, $params);
    }
}
```

Then teach `PostgresTestDatabase` to build one, seed twenty labelled issues, and record
what a single list request costs:

```php
$database->startRecording();
$twenty = integrityGet($base, ['perPage' => '50']);
$statements = $database->stopRecording();

expect(count($statements))->toBeLessThanOrEqual(8, /* … the statements … */);
```

Run it. **Twenty-five statements for twenty issues**, and the failure message shows
exactly why:

```text
- SELECT workspaces.id … INNER JOIN workspace_memberships …
- SELECT id FROM projects WHERE id = :id AND workspace_id = :workspace_id
- SELECT COUNT(*) AS aggregate FROM issues WHERE issues.project_id = :project_id
- SELECT issues.id, issues.title … LIMIT :limit OFFSET :offset
- SELECT COUNT(*) AS aggregate FROM issues WHERE project_id = :project_id
- SELECT labels.id, labels.name, labels.color … WHERE issue_labels.issue_id = :issue_id
- SELECT labels.id, labels.name, labels.color … WHERE issue_labels.issue_id = :issue_id
- SELECT labels.id, labels.name, labels.color … WHERE issue_labels.issue_id = :issue_id
…
```

We wrote that in Lesson 50 and it has been there ever since. `IssueData::present()`
fetches an issue's labels, the list endpoint calls it once per row, and a page of fifty
issues costs fifty extra round trips. The response was always correct, which is
precisely why nothing caught it.

## Fix the N+1 the counter found

In `app/Http/IssueData.php`, add a loader that fetches every label for a whole page at
once and groups them by issue:

```php
public static function labelsFor(Database $database, array $issueIds): array
{
    if ($issueIds === []) {
        return [];
    }

    $placeholders = [];
    $bindings = [];
    foreach (array_values($issueIds) as $index => $issueId) {
        $placeholders[] = ':issue_' . $index;
        $bindings['issue_' . $index] = $issueId;
    }

    $rows = $database->query(
        'SELECT issue_labels.issue_id, labels.id, labels.name, labels.color
         FROM issue_labels
         JOIN labels ON labels.id = issue_labels.label_id
         WHERE issue_labels.issue_id IN (' . implode(', ', $placeholders) . ')
         ORDER BY lower(labels.name), labels.id',
        $bindings,
    )->get();

    $grouped = [];
    foreach ($rows as $row) {
        $grouped[(int) $row['issue_id']][] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'color' => (string) $row['color'],
        ];
    }

    return $grouped;
}
```

The placeholders are generated, never interpolated. The issue ids come from a row we
just read, but building `IN (…)` by string concatenation is a habit that eventually
meets a value that did not.

Give `present()` an optional third argument so a list can hand labels in while a single
issue keeps working exactly as before:

```php
public static function present(array $issue, ?Database $database = null, ?array $labels = null): array
{
    $assigneeId = $issue['assignee_id'] ?? null;

    $labels ??= $database === null ? [] : array_map(/* the per-issue query */);
```

`??=` is doing the work: pass labels and they are used; pass `null` and the old path
runs. `show.php` and `status.php` present one issue and need no change at all.

Now in `app/Http/controllers/api/issues/index.php`, load once before presenting:

```php
// One label query for the whole page, instead of one per issue.
$labelsByIssue = IssueData::labelsFor(
    $database,
    array_map(static fn (array $issue): int => (int) $issue['id'], $issues),
);

return Response::json([
    'issues' => array_map(
        static fn (array $issue): array => IssueData::present(
            $issue,
            $database,
            $labelsByIssue[(int) $issue['id']] ?? [],
        ),
        $issues,
    ),
```

`?? []` matters: an issue with no labels has no key in the grouped result, and it must
present as an empty list rather than fall through to the per-issue query.

Twenty-five statements become five, and they stay five when the page grows.

## Run the gate

```bash
php vendor/bin/pest tests/Feature/PolicyMatrixTest.php \
  tests/Feature/DataIntegrityTest.php \
  tests/Feature/TransactionTest.php
npm run typecheck
npm run lint
npm test
npm run build
```

Thirty-five PHP tests pass with 122 assertions, all 42 frontend tests pass, and Vite
produces the production bundle. The complete PHP suite reports 324 passing tests.

Through real HTTP, a fresh account creates a workspace, a project, a `Bug` label, and
three issues; attaching the label to two of them returns exactly:

```text
3 Issue 3 -> []
2 Issue 2 -> ['Bug']
1 Issue 1 -> ['Bug']
```

Same output as before the change, one query instead of four.

We now have a suite that describes our authorization in one readable table, refuses to
let a new endpoint arrive untested, checks the constraints we spent ten lessons adding,
and can see a performance regression that returns the right answer. The next lesson
drives the assembled application through a real browser.
