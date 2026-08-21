We can authenticate an account, but nothing requires that identity yet. We will make
the tracker private at the server boundary. Browser pages will redirect guests to
login and remember where they were going; JSON endpoints will answer with a deliberate
401 response that React can recognize.

## Use two responses for two kinds of caller

DALT already has an `auth` middleware. A guest page request receives a 302 redirect to
`/login`, and a safe GET destination is saved for the next successful login. That is
exactly right for HTML navigation.

It is wrong for `fetch`. A JSON client should not receive a login document disguised
behind a successful redirect. We will keep one authentication rule and give each
transport an honest response.

## Add JSON authentication middleware

Create `app/Http/Middleware/ApiAuth.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Core\Authenticator;
use Core\Middleware\MiddlewareInterface;
use Core\Request;
use Core\Response;

final class ApiAuth implements MiddlewareInterface
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ((new Authenticator())->guest()) {
            return Response::json([
                'error' => [
                    'code' => 'unauthenticated',
                    'message' => 'Log in to continue.',
                ],
            ], 401);
        }

        return $next($request);
    }
}
```

The middleware does not inspect an email, cookie, or submitted user ID itself. It asks
the same `Authenticator` used everywhere else. A valid identity reaches `$next`; a
guest never reaches the controller.

DALT routes accept a middleware class name directly, so we do not need to change the
framework's global aliases.

## Protect document routes

Import the application middleware at the top of `routes/routes.php`:

```php
use App\Http\Middleware\ApiAuth;
```

Keep `/` as a public entry:

```php
$router->get('/', 'welcome.php');
```

Update `app/Http/controllers/welcome.php` so a guest receives the login React shell
with the framework's expected public title, while a signed-in account receives the
workspace application:

```php
use Core\Authenticator;

if ((new Authenticator())->guest()) {
    view('auth.view.php', [
        'mode' => 'login',
        'documentTitle' => 'DALT.PHP',
    ]);

    return;
}

view('welcome.view.php');
```

In `auth.view.php`, accept that optional title and escape it in the document head.
The public root can now offer login without starting a private workspace request; once
authenticated, the same URL becomes the workspace home.

Add built-in `auth` to every resource page shell:

```php
$router->get('/workspaces/{workspace}', 'workspaces/show.php')->only('auth');
$router->get('/workspaces/{workspace}/edit', 'workspaces/show.php')->only('auth');
$router->get('/workspaces/{workspace}/delete', 'workspaces/show.php')->only('auth');

$router->get(
    '/workspaces/{workspace}/projects/{project}',
    'projects/show.php',
)->only('auth');
```

Apply the same middleware to the project edit/delete paths and all three issue detail
paths. Also keep the older PHP mutation routes protected with authentication before
CSRF:

```php
$router->post('/workspaces', 'workspaces/store.php')
    ->only(['auth', 'csrf']);
```

That ordering is intentional. An expired session should first mean “log in again,”
not “your CSRF token is invalid.”

Registration and login remain `guest` routes. Protecting `/login` with `auth` would
create a redirect loop.

## Protect every JSON route

Use `ApiAuth::class` on reads:

```php
$router->get('/api/workspaces', 'api/workspaces/index.php')
    ->only(ApiAuth::class);

$router->get(
    '/api/workspaces/{workspace}/projects',
    'api/projects/index.php',
)->only(ApiAuth::class);
```

Use API authentication before CSRF on every mutation:

```php
$router->post('/api/workspaces', 'api/workspaces/store.php')
    ->only([ApiAuth::class, 'csrf']);

$router->post(
    '/api/workspaces/{workspace}/projects/{project}/issues/{issue}/status',
    'api/issues/status.php',
)->only([ApiAuth::class, 'csrf']);
```

Repeat that boundary for workspace, project, issue, and logout endpoints. Do not
protect registration or login with `ApiAuth`: those two requests exist for guests.

At this point authentication answers one question: “Is there a valid session
identity?” It does not yet answer “Does this account own workspace 7?” Ownership is
the next lesson.

## Recover when a live session expires

A private page was authenticated when DALT served its React shell, but its session can
expire while the page remains open. A later API request then receives our 401.

Create `resources/app/api-authentication.ts`:

```ts
export function requireAuthenticatedResponse(response: Response): void {
  if (response.status !== 401) return

  window.location.reload()
  throw new Error('The session has expired.')
}
```

Reloading the current private URL is useful here. The document request reaches DALT's
page middleware, which remembers that safe GET path and redirects to login. After a
successful login, `Authenticator::intended()` returns the person to the same screen.

The thrown error stops the old request function from trying to parse a response as its
normal success shape.

Import this helper into:

```text
resources/app/workspace-data.ts
resources/app/workspace-detail-data.ts
resources/app/project-page-data.ts
resources/app/app-shell-data.ts
```

Call it immediately after every private `fetch` and before reading JSON:

```ts
const response = await fetch('/api/workspaces', {
  headers: { Accept: 'application/json' },
  signal,
})
requireAuthenticatedResponse(response)

if (!response.ok) {
  throw new Error(
    'The workspaces request failed with status ' + response.status + '.',
  )
}
```

Do not add it to registration or login: a rejection there is an expected guest-facing
form result, not an expired private session.

## Make authenticated tests explicit

All existing API feature tests now need the identity that a real route requires. Add
this to the `IssueApiTest.php` setup:

```php
$_SESSION['user'] = [
    'id' => 1,
    'email' => 'ada@example.com',
];
```

This is not bypassing authorization. The tests are setting the request's session
boundary, then still dispatching the real router, middleware, controller, and database
queries.

Add two focused tests to `AuthenticationTest.php`. First prove page behavior:

```php
$response = authenticationRequest('GET', '/workspaces/7');

expect($response->status())->toBe(302)
    ->and($response->headers()['Location'])->toBe('/login')
    ->and($_SESSION['auth.intended'])->toBe('/workspaces/7');
```

Then begin a fresh guest session and prove the API boundary:

```php
$response = authenticationRequest('GET', '/api/workspaces');

expect($response->status())->toBe(401)
    ->and($response->headers()['Content-Type'])
    ->toBe('application/json; charset=UTF-8')
    ->and(json_decode(
        $response->content(), true, flags: JSON_THROW_ON_ERROR,
    ))->toBe([
        'error' => [
            'code' => 'unauthenticated',
            'message' => 'Log in to continue.',
        ],
    ])
    ->and($_SESSION)->not->toHaveKey('auth.intended');
```

The absence of `auth.intended` matters. We never replay an API request after login;
only safe document navigation gets that behavior.

Also prove `GET /` returns the public authentication shell with status 200 and does not
create an intended destination. This protects the framework's public-root contract
without making any workspace resource public.

Run the complete affected boundary:

```bash
php vendor/bin/pest tests/Feature/AuthenticationTest.php \
  tests/Feature/IssueApiTest.php
npm run typecheck
npm run lint
npm test
npm run build
```

PHP should report twenty-one tests. React still reports sixteen tests.
In a private browser window, `/workspaces/7` should go to login, then return to that
path after valid credentials. A direct guest request to `/api/workspaces` should return
the JSON 401 envelope, not HTML.

```bash
git add routes/routes.php app/Http/Middleware/ApiAuth.php \
  app/Http/controllers/welcome.php resources/views/auth.view.php \
  resources/app/api-authentication.ts resources/app/app-shell-data.ts \
  resources/app/workspace-data.ts resources/app/workspace-detail-data.ts \
  resources/app/project-page-data.ts tests/Feature/AuthenticationTest.php \
  tests/Feature/IssueApiTest.php
git commit -m "Protect application routes"
```

The tracker now knows that every request has an authenticated actor. Next we will add
an owner to each workspace and include that actor in every workspace lookup, so one
account cannot read or mutate another account's nested data.
