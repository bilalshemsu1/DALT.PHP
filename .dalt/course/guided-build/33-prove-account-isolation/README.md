Our ownership code works in the browser, but an authorization boundary needs stronger
evidence than one denied request. We will build a reciprocal two-account test: each
account can use its own hierarchy, neither can use the other hierarchy, and every
denied mutation leaves durable state unchanged.

This lesson changes no production behavior. It makes the behavior we just built safe
to improve later.

## Create an isolated authorization test

Create `tests/Feature/WorkspaceAuthorizationTest.php`. Give this file its own helper
names so it can run beside our other feature tests:

```php
function authorizationDatabase(): Database
{
    return App::resolve(Database::class);
}

function authorizationRouter(): Router
{
    $GLOBALS['router'] = new Router(App::container());
    require base_path('routes/routes.php');

    return $GLOBALS['router'];
}

function authorizationAs(int $id, string $email): void
{
    $_SESSION['user'] = ['id' => $id, 'email' => $email];
}
```

Add `authorizationRequest` using the same real `Router`, `Request`, CSRF token, and
method-override input as `IssueApiTest.php`. The point is to dispatch production
middleware and controllers, not call an authorization helper directly.

## Turn a refused request into one reusable assertion

Cross-account requests should look exactly like missing resources. Add this helper:

```php
function expectAuthorizationNotFound(
    Router $router,
    string $method,
    string $uri,
    array $input = [],
): void {
    try {
        authorizationRequest($router, $method, $uri, $input);
        test()->fail("Cross-account request reached {$method} {$uri}.");
    } catch (HttpException $exception) {
        expect($exception->statusCode)->toBe(404);
    }
}
```

This helper does not merely expect an exception. If the request reaches its controller
and returns any response, the test fails with the exact method and URI that crossed
the boundary.

## Capture durable state before hostile requests

A 404 response alone is not enough. A broken controller could mutate first and fail
later. Add a snapshot of every protected row:

```php
function authorizationSnapshot(): array
{
    $database = authorizationDatabase();

    return [
        'workspaces' => $database->query(
            'SELECT id, owner_id, name FROM workspaces ORDER BY id',
        )->get(),
        'projects' => $database->query(
            'SELECT id, workspace_id, name FROM projects ORDER BY id',
        )->get(),
        'issues' => $database->query(
            'SELECT id, project_id, title, description, status
             FROM issues ORDER BY id',
        )->get(),
    ];
}
```

We select the columns the workflows can change and use stable ordering. The final
comparison will catch a rename, insertion, status transition, or deletion anywhere in
the hierarchy.

## Seed two complete account worlds

In `beforeEach`, create an in-memory SQLite connection, bind it into DALT's container,
and run migrations 001 through 005 in order.

Seed two users, one workspace per user, one project per workspace, and one issue per
project:

```php
$database->query(
    "INSERT INTO workspaces (owner_id, name) VALUES
     (1, 'Ada workspace'), (2, 'Grace workspace')",
);
$database->query(
    "INSERT INTO projects (workspace_id, name) VALUES
     (1, 'Ada project'), (2, 'Grace project')",
);
$database->query(
    "INSERT INTO issues
       (project_id, title, description, status) VALUES
     (1, 'Ada issue', 'Ada owns this.', 'open'),
     (2, 'Grace issue', 'Grace owns this.', 'closed')",
);
```

Using complete parallel hierarchies matters. A test with only one account cannot prove
separation; a test where the second account owns no records cannot prove its allowed
path.

## Prove each collection filters in both directions

Use the same router with two session identities:

```php
authorizationAs(1, 'ada@example.com');
$ada = authorizationRequest($router, 'GET', '/api/workspaces');

authorizationAs(2, 'grace@example.com');
$grace = authorizationRequest($router, 'GET', '/api/workspaces');
```

Assert Ada receives only workspace 1 and Grace receives only workspace 2, including
their real project counts. This catches a collection query that forgot its owner
predicate.

## Prove allow and deny together

For Ada, first require 200 from the owned project collection and owned issue:

```php
authorizationAs(1, 'ada@example.com');

expect(authorizationRequest(
    $router, 'GET', '/api/workspaces/1/projects',
)->status())->toBe(200)
    ->and(authorizationRequest(
        $router,
        'GET',
        '/api/workspaces/1/projects/1/issues/1',
    )->status())->toBe(200);
```

Then refuse Grace's matching paths:

```php
expectAuthorizationNotFound(
    $router, 'GET', '/api/workspaces/2/projects',
);
expectAuthorizationNotFound(
    $router, 'GET', '/api/workspaces/2/projects/2/issues/2',
);
```

Switch the session to Grace and repeat with the IDs reversed. The positive cases make
this test fail if a fake implementation simply denies everyone.

## Exercise every foreign mutation shape

Begin with `$before = authorizationSnapshot()`. Use a two-row data set so both actors
attack the other's hierarchy:

```php
foreach ([
    [1, 'ada@example.com', 2, 2, 2],
    [2, 'grace@example.com', 1, 1, 1],
] as [$actorId, $actorEmail, $workspaceId, $projectId, $issueId]) {
    authorizationAs($actorId, $actorEmail);
    $workspace = "/api/workspaces/{$workspaceId}";
    $project = "{$workspace}/projects/{$projectId}";
    $issue = "{$project}/issues/{$issueId}";
```

Inside the loop, require 404 for all of these requests:

```php
expectAuthorizationNotFound($router, 'POST', $workspace, [
    'name' => 'Crossed',
]);
expectAuthorizationNotFound($router, 'POST', "{$workspace}/projects", [
    'name' => 'Crossed',
]);
expectAuthorizationNotFound($router, 'POST', $project, [
    'name' => 'Crossed',
]);
expectAuthorizationNotFound($router, 'POST', "{$project}/issues", [
    'title' => 'Crossed',
    'description' => 'Must not exist.',
]);
expectAuthorizationNotFound($router, 'POST', $issue, [
    'title' => 'Crossed',
    'description' => 'Must not change.',
]);
expectAuthorizationNotFound($router, 'POST', "{$issue}/status", [
    'status' => 'closed',
]);
```

Also send method-overridden DELETE requests for the issue, project, and workspace.
After both actors finish, close with the database proof:

```php
expect(authorizationSnapshot())->toBe($before);
```

The response evidence and row evidence answer different questions. Together they
prove the server refused the operation before any durable effect.

## Prove submitted ownership has no authority

Finally, act as Grace and try to create a workspace while submitting Ada's ID:

```php
authorizationAs(2, 'grace@example.com');

$response = authorizationRequest(
    authorizationRouter(),
    'POST',
    '/api/workspaces',
    [
        'name' => 'New Grace workspace',
        'owner_id' => 1,
    ],
);
```

Load the stored row and assert `owner_id` is 2. The input may contain an attractive
fake identity; the controller ignores it and derives ownership from the authenticated
session.

Run the complete server boundary, then keep the unchanged client green:

```bash
php vendor/bin/pest tests/Feature/AuthenticationTest.php \
  tests/Feature/IssueApiTest.php \
  tests/Feature/WorkspaceAuthorizationTest.php
npm run typecheck
npm run lint
npm test
npm run build
```

PHP should report twenty-five tests and 253 assertions. React should report sixteen
tests. No browser behavior changed in this lesson; the evidence protects the behavior
we already inspected.

```bash
git add tests/Feature/WorkspaceAuthorizationTest.php
git commit -m "Prove workspace authorization"
```

We now have account creation, login/logout, private routes, workspace ownership, and a
reciprocal authorization proof. The next product batch can build on this identity
boundary instead of treating it as an assumption.
