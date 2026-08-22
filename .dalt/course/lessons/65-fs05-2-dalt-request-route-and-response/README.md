# FS05.2 — A request through DALT routes, controllers, and responses

Lesson ID: FS05.2
Lesson format: Concise theory
Part: 05 — DALT API and PostgreSQL
Status: Published
Estimated effort: 30–40 minutes
Difficulty: Foundation
Prerequisites: FS05.1
Last reviewed: 2026-08-22

We will follow one request through DALT so routes, controllers, and responses become a connected path rather than unrelated files.

> **Helpful background:** [Modern PHP values, types, arrays, functions, and exceptions](/learn/lessons/64-fs05-1-modern-php-for-web-applications)

## What we will learn

- trace an HTTP request from `public/index.php` to the response;
- distinguish route selection from controller work;
- read query, form, and route values through DALT's `Request` object.

## Every web request enters through one file

A web server points requests at `public/index.php`. This file is the **front controller**: the shared entry point that boots DALT and coordinates the request. The important path is:

```text
browser
  → public/index.php
  → routes/routes.php
  → matching route handler
  → Core\Response
  → browser
```

The front controller starts the session, creates a router, loads application and learning-platform routes, then captures the request:

```php
$router = new Core\Router(App::container());

require base_path('routes/routes.php');
$platform->registerRoutes($router);

$request = Request::capture();
$response = $router->route(
    $request->path(),
    $request->method(),
    $request,
);

$response->send();
```

Application code should normally register routes and write handlers, not repeat this boot sequence.

## A route answers one selection question

A route pairs an HTTP method and path pattern with a handler. The skeleton's first route is deliberately small:

```php
// routes/routes.php
$router->get('/', 'welcome.php');
```

`GET /` selects `app/Http/controllers/welcome.php`. `POST /` does not select it, because the method is part of the route. If no method-and-path pair matches, DALT raises a 404 response.

Dynamic segments capture identifiers without inventing a route for every issue:

```php
$router->get('/api/issues/{id}', function (Request $request): Response {
    return Response::json([
        'id' => $request->route('id'),
    ]);
});
```

For `/api/issues/ISS-41`, `route('id')` is `ISS-41`. The container supplies the current `Request` to the typed closure. Route parameters remain separate from query parameters, so an `?id=wrong` query cannot silently replace the path identity.

## A controller performs the selected operation

DALT also accepts a controller-file path as the handler. The welcome controller currently contains:

```php
<?php

declare(strict_types=1);

view('welcome.view.php');
```

The router resolves that path below `app/Http/controllers`, executes it, and converts its output into a `Response`. A controller is where we coordinate one use case: validate input, call application or database code, and choose the response. It should not decide whether its own URL matched; that is the router's responsibility.

As controllers grow, use folders that mirror resources and operations:

```text
app/Http/controllers/
└── api/
    └── issues/
        ├── index.php
        ├── show.php
        └── store.php
```

This is organization, not magic. Route declarations still point to the files explicitly.

## Request data has separate sources

DALT's `Request` keeps three common input locations distinct:

```php
$request->query('page', 1);     // URL: ?page=2
$request->input('title', '');   // submitted form field
$request->route('id');          // path: /issues/{id}
$request->method();             // GET, POST, PATCH...
$request->path();               // /api/issues/ISS-41
```

`Request::capture()` currently builds form input from PHP's `$_POST`. JSON is different: a request with `Content-Type: application/json` is not decoded into `input()` automatically. In FS05.3 we will deliberately read `php://input`, decode it, and validate its resulting value.

Keeping these sources separate prevents ambiguous code. A page number belongs to the query string, a resource identity normally belongs to the route, and a proposed field value belongs to the request body.

## The handler result becomes one response

The router normalizes supported handler results:

```php
return ['issues' => $issues];             // JSON response, status 200
return Response::json($issue, 201);       // explicit JSON and status
return Response::text('healthy');         // plain text
return Response::redirect('/workspaces'); // redirect
```

Returning an array is convenient for an ordinary 200 JSON result. Use an explicit `Response` when status or headers carry meaning. `Response::json` encodes with `JSON_THROW_ON_ERROR` and sets `Content-Type: application/json; charset=UTF-8`; `send()` writes the status, headers, and body to the real HTTP response.

## Try it

**Workspace:** No workspace copy is needed. This is a read-only observation of the working DALT skeleton.

**Starting state:** run from the repository root with no challenge active. In one terminal, start the application:

```bash
php artisan serve
```

In a second terminal, request the registered route:

```bash
curl -i http://127.0.0.1:8000/
```

The response begins with `HTTP/1.1 200 OK` and includes `Content-Type: text/html; charset=UTF-8`, followed by the welcome HTML. Now request an unregistered application path:

```bash
curl -i http://127.0.0.1:8000/this-route-does-not-exist
```

It begins with `HTTP/1.1 404 Not Found`. Finally, run DALT's focused routing evidence:

```bash
php vendor/bin/pest tests/Unit/RouterTest.php \
  --filter='normalizes closure output|injects the captured request|controller files'
```

The three routing tests pass.

**Expected result:** the real server proves the registered and missing paths, while the focused tests prove array-to-JSON normalization, request injection with route parameters, and controller-file dispatch.

**Reset:** stop `artisan serve` with `Ctrl+C`. No files were changed.

## What to notice

The route is a selector, not the whole feature. The controller coordinates the selected operation, the request carries HTTP input, and the response carries HTTP output. The front controller connects these pieces once for every request.

## Check your understanding

1. Why does DALT use `public/index.php` for unrelated URLs?
2. What two facts must match a route?
3. Where is `{id}` read after a dynamic route matches?
4. When is `Response::json` clearer than returning an array?

<details><summary>Check your answers</summary>

1. It is the shared front controller that boots and dispatches the application.
2. The HTTP method and path pattern.
3. From `$request->route('id')`.
4. When the status or headers must be explicit, such as a 201 creation response.
</details>

## Next

We can now place server code correctly; next we will define and enforce the JSON contract crossing that request path.

<details><summary>Maintainer source record</summary>

- Source dossier: former Part 05 API material and DALT framework contracts.
- Official sources: PHP manual request-variable and output-control material; RFC 9110 HTTP semantics.
- Versions: DALT current repository behavior; PHP 8.2 minimum.
- Consulted: 2026-08-22.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 6, FS05.2.
- DALT files inspected: `public/index.php`, `routes/routes.php`, `app/Http/controllers/welcome.php`, `framework/Core/Request.php`, `Router.php`, `Route.php`, `Response.php`, `Container.php`, `tests/Unit/RouterTest.php`, and `tests/Feature/RequestLifecycleTest.php`.
- Reused material: route, controller, response, and status explanations extracted from former FS05.1.
</details>
