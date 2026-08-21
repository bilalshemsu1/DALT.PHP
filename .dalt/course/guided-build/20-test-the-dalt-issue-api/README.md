The React tests prove that the screen sends and handles the right network conversation.
MSW cannot prove that a DALT route is registered, that CSRF middleware runs, or that a
controller changes SQL. We will now dispatch the real routes against a fresh in-memory
SQLite database and inspect both the response and the stored row.

## Choose the boundary deliberately

Create `tests/Feature/IssueApiTest.php`. These tests run the `Router`, middleware, request,
controllers, validator, and database together. They do not start a web server, render
React, or touch `database/app.sqlite`.

Import the framework pieces this boundary needs:

```php
<?php

declare(strict_types=1);

use Core\App;
use Core\Container;
use Core\Database;
use Core\DatabaseManager;
use Core\HttpException;
use Core\Platform;
use Core\Request;
use Core\Response;
use Core\Router;
```

DALT's existing `tests/Pest.php` gives feature tests the shared `Tests\TestCase`, which
resets superglobals, session state, response headers, and the service container around
each test. We will add database isolation inside this file.

## Give tests the application routes

Add a database accessor and load the real route file into a real router:

```php
function issueApiDatabase(): Database
{
    /** @var Database $database */
    $database = App::resolve(Database::class);

    return $database;
}

function issueApiRouter(): Router
{
    $GLOBALS['router'] = new Router(App::container());
    require base_path('routes/routes.php');

    return $GLOBALS['router'];
}
```

`routes/routes.php` declares `global $router`, so the helper deliberately supplies that
global. Its handlers remain the controller files under
`app/Http/controllers/api/issues`; nothing is copied into the test.

Add one request helper:

```php
/** @param array<string, mixed> $input */
function issueApiRequest(
    Router $router,
    string $method,
    string $uri,
    array $input = [],
    bool $withCsrf = true,
): Response {
    $_SESSION['_csrf'] = 'known-token';

    if ($withCsrf) {
        $input['_token'] = 'known-token';
    }

    $request = new Request(
        input: $input,
        server: [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
        ],
    );

    return $router->route($uri, $request->method(), $request);
}
```

Calling `$request->method()` matters when `_method=DELETE` is present. The request follows
DALT's real method-override policy before the router chooses a route. CSRF is the normal
case; a test must opt out when it wants to prove rejection.

## Rebuild a known database before every test

Use Pest's `beforeEach` hook to install an isolated application container:

```php
beforeEach(function () {
    $container = new Container();
    $database = DatabaseManager::create([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $container->instance(Database::class, $database);
    $container->instance(Platform::class, Platform::discover(base_path()));
    App::setContainer($container);

    foreach ([
        '002_create_workspaces_table.sql',
        '003_create_projects_table.sql',
        '004_create_issues_table.sql',
    ] as $migration) {
        $sql = file_get_contents(base_path("database/migrations/{$migration}"));
        expect($sql)->not->toBeFalse();
        $database->getConnection()->exec($sql);
    }
```

SQLite's `:memory:` database exists only for this connection. Every test receives a new
connection, so one test cannot leave an issue for another. Finish the hook with two
ownership branches and three issues:

```php
    $database->query("INSERT INTO workspaces (name) VALUES ('Studio'), ('Archive')");
    $database->query(
        "INSERT INTO projects (workspace_id, name) VALUES (1, 'Launch'), (2, 'History')",
    );
    $database->query(
        "INSERT INTO issues (project_id, title, description, status) VALUES
         (1, 'First issue', 'Created first.', 'open'),
         (1, 'Second issue', 'Created second.', 'closed'),
         (2, 'Archived issue', '', 'open')",
    );
});
```

The second workspace and project are not decoration. They let us cross the IDs and prove
that two real records do not automatically form a valid ownership path.

## Pin the collection contract

Dispatch the real index route and assert its complete public shape:

```php
test('the issue collection returns typed JSON in newest-first order', function () {
    $response = issueApiRequest(
        issueApiRouter(),
        'GET',
        '/api/workspaces/1/projects/1/issues',
    );

    expect($response->status())->toBe(200)
        ->and($response->headers()['Content-Type'])
        ->toBe('application/json; charset=UTF-8')
        ->and(json_decode(
            $response->content(),
            true,
            flags: JSON_THROW_ON_ERROR,
        ))->toBe([
            'issues' => [
                [
                    'id' => 2,
                    'title' => 'Second issue',
                    'description' => 'Created second.',
                    'status' => 'closed',
                ],
                [
                    'id' => 1,
                    'title' => 'First issue',
                    'description' => 'Created first.',
                    'status' => 'open',
                ],
            ],
        ]);
});
```

A controller that returns every issue, reverses the order, or serializes IDs incorrectly
now fails exactly where React depends on it.

## Prove protection happens before persistence

Send the create route once without a token and once with two invalid fields:

```php
test('creation requires csrf and rejected input never reaches the database', function () {
    $router = issueApiRouter();
    $uri = '/api/workspaces/1/projects/1/issues';

    $missingToken = issueApiRequest(
        $router,
        'POST',
        $uri,
        ['title' => 'Protected issue', 'description' => 'Should not be saved.'],
        withCsrf: false,
    );
    $invalid = issueApiRequest(
        $router,
        'POST',
        $uri,
        ['title' => 'x', 'description' => str_repeat('a', 1001)],
    );
    $count = issueApiDatabase()
        ->query('SELECT COUNT(*) AS aggregate FROM issues WHERE project_id = 1')
        ->find();

    expect($missingToken->status())->toBe(419)
        ->and($missingToken->content())->toBe('CSRF token mismatch')
        ->and($invalid->status())->toBe(422)
        ->and(json_decode($invalid->content(), true, flags: JSON_THROW_ON_ERROR))
        ->toBe([
            'errors' => [
                'title' => 'Use between 2 and 100 characters.',
                'description' => 'Keep the description under 1,000 characters.',
            ],
        ])
        ->and((int) $count['aggregate'])->toBe(2);
});
```

Both errors protect accumulated validation. The row count proves the controller did not
return a convincing 422 after accidentally inserting.

## Cross the ownership boundary

Workspace 2 exists and project 1 exists, but project 1 belongs to workspace 1:

```php
test('crossed workspace and project ids are not treated as ownership', function () {
    try {
        issueApiRequest(
            issueApiRouter(),
            'GET',
            '/api/workspaces/2/projects/1/issues',
        );
        $this->fail('A project from another workspace must not be returned.');
    } catch (HttpException $exception) {
        expect($exception->statusCode)->toBe(404);
    }
});
```

The direct router boundary exposes DALT's `HttpException`. The production exception
handler turns that same exception into the public 404 response.

## Exercise the complete mutation lifecycle

Start one test with a genuine create request:

```php
test('the real issue endpoints create update change status and delete a row', function () {
    $router = issueApiRouter();
    $collection = '/api/workspaces/1/projects/1/issues';

    $created = issueApiRequest($router, 'POST', $collection, [
        'title' => '  Ship API tests  ',
        'description' => '  Exercise real SQL.  ',
    ]);
    $createdBody = json_decode(
        $created->content(),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $issueId = $createdBody['issue']['id'];
    $issueUri = "{$collection}/{$issueId}";

    expect($created->status())->toBe(201)
        ->and($createdBody['issue'])->toMatchArray([
            'title' => 'Ship API tests',
            'description' => 'Exercise real SQL.',
            'status' => 'open',
        ])
        ->and(issueApiDatabase()->query(
            'SELECT title FROM issues WHERE id = :id',
            ['id' => $issueId],
        )->find()['title'])->toBe('Ship API tests');
```

The response proves trimming and the 201 contract. The independent query proves the row
exists. Continue with the status endpoint:

```php
    $closed = issueApiRequest($router, 'POST', "{$issueUri}/status", [
        'status' => 'closed',
    ]);

    expect(json_decode($closed->content(), true, flags: JSON_THROW_ON_ERROR))
        ->toMatchArray([
            'issue' => [
                'id' => $issueId,
                'title' => 'Ship API tests',
                'description' => 'Exercise real SQL.',
                'status' => 'closed',
            ],
            'message' => 'Ship API tests was closed.',
        ])
        ->and(issueApiDatabase()->query(
            'SELECT status FROM issues WHERE id = :id',
            ['id' => $issueId],
        )->find()['status'])->toBe('closed');
```

Edit the same closed row. The response must preserve its status:

```php
    $updated = issueApiRequest($router, 'POST', $issueUri, [
        'title' => 'Tested API',
        'description' => 'The database agrees.',
    ]);

    expect(json_decode($updated->content(), true, flags: JSON_THROW_ON_ERROR))
        ->toMatchArray([
            'issue' => [
                'id' => $issueId,
                'title' => 'Tested API',
                'description' => 'The database agrees.',
                'status' => 'closed',
            ],
            'message' => 'Tested API was updated.',
        ]);
```

Finish through the same POST method override the browser uses:

```php
    $beforeDelete = issueApiDatabase()
        ->query(
            'SELECT COUNT(*) AS aggregate FROM issues WHERE id = :id',
            ['id' => $issueId],
        )->find();
    $deleted = issueApiRequest(
        $router,
        'POST',
        $issueUri,
        ['_method' => 'DELETE'],
    );
    $remaining = issueApiDatabase()
        ->query(
            'SELECT COUNT(*) AS aggregate FROM issues WHERE id = :id',
            ['id' => $issueId],
        )->find();

    expect((int) $beforeDelete['aggregate'])->toBe(1)
        ->and($deleted->status())->toBe(200)
        ->and(json_decode($deleted->content(), true, flags: JSON_THROW_ON_ERROR))
        ->toBe(['message' => 'Tested API was deleted.'])
        ->and((int) $remaining['aggregate'])->toBe(0);
});
```

The before-and-after counts matter. A fake controller that returns “was deleted” without
running DELETE still looks correct to React, but this test fails because the row remains.

## Run the real backend boundary

Run the focused feature file:

```bash
php vendor/bin/pest tests/Feature/IssueApiTest.php
```

It should report four passing tests and 42 assertions. The count includes the migration
file checks performed before each test.

Prove the deletion assertion is earned: briefly replace the controller's `DELETE FROM`
query with a harmless `SELECT`. Re-run the lifecycle test:

```bash
php vendor/bin/pest tests/Feature/IssueApiTest.php \
  --filter='real issue endpoints'
```

It must fail because one row remains. Restore the DELETE query and run the whole feature
file again.

Then run the complete application checks:

```bash
php artisan test
npm test
npm run typecheck
npm run lint
npm run build
```

If Git is available, save the backend test:

```bash
git add tests/Feature/IssueApiTest.php
git commit -m "Test the DALT issue API"
```

We now have complementary safety nets. React tests protect the routed experience at the
network boundary; DALT feature tests protect the real HTTP and persistence behavior below
it. Next we can migrate the remaining workspace and project screens into React without
guessing whether the issue workflow survived.
