# FS06.4 — CSRF and cookie-authenticated writes

Lesson ID: FS06.4
Lesson format: Concise theory
Part: 06 — Testing, users, and authorization
Status: Published
Estimated effort: 30–40 minutes
Difficulty: Foundation
Prerequisites: FS06.3
Last reviewed: 2026-08-22

We will require separate proof of intent before a cookie-authenticated request may change application state.

> **Helpful background:** [Login, logout, and server-side sessions](/learn/lessons/68-fs06-3-login-logout-and-server-sessions)

## What we will learn

- explain why a valid session does not prove the user intended a request;
- send DALT's session-bound CSRF token from forms and JSON clients;
- prove rejection happens before a mutation and valid requests still succeed.

## Cookies create the need for CSRF protection

The browser attaches cookies for the destination automatically. That convenience keeps a user logged in, but it also means another site can cause the browser to send an authenticated request.

```text
hostile page → POST our application
browser      → automatically adds our session cookie
server       → sees a real logged-in session
```

The same-origin policy can prevent the hostile page from reading the response. It does not necessarily prevent the request or undo a state change already made. **Cross-site request forgery**, or CSRF, is another site tricking a logged-in browser into performing an unwanted action.

Authentication answers “who does this session identify?” CSRF protection answers “did our application deliberately submit this unsafe request?” We need both.

## The session holds a second unpredictable value

DALT implements the synchronizer-token pattern. `csrf_token()` creates 32 random bytes, encodes them as 64 hexadecimal characters, and stores the result in the server session:

```php
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}
```

The browser session cookie and CSRF token are different values. The cookie travels automatically; the request must deliberately copy the CSRF token into a form field or header. A hostile origin cannot read the token from our page or server session under ordinary same-origin protections.

Do not place the token in a URL. URLs enter histories, logs, analytics, and referrer data. Do not log either the CSRF token or session ID while debugging.

## Forms and JSON carry the token differently

For a server-rendered form, DALT generates a hidden field:

```php
<form method="POST" action="/issues">
    <?= csrf_field() ?>
    <!-- visible fields -->
</form>
```

The submitted `_token` becomes ordinary form input.

A JSON request does not populate PHP's `$_POST`. React sends the token in a header instead:

```ts
await fetch('/api/issues', {
  method: 'POST',
  credentials: 'same-origin',
  headers: {
    'Content-Type': 'application/json',
    'X-CSRF-Token': csrfToken,
  },
  body: JSON.stringify(input),
})
```

The application can render the current session's token into a `<meta>` element in the initial page or expose a deliberate same-origin bootstrap response. JavaScript reads the CSRF token; it never reads the HttpOnly session cookie.

## Middleware stops the request before the handler

DALT's CSRF middleware skips safe methods and checks every other request:

```php
private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

$sessionToken = $_SESSION['_csrf'] ?? null;
$requestToken = $request->input('_token')
    ?? $request->server('HTTP_X_CSRF_TOKEN');

if (!is_string($sessionToken)
    || !is_string($requestToken)
    || !hash_equals($sessionToken, $requestToken)) {
    return Response::text('CSRF token mismatch', 419);
}
```

`hash_equals()` performs the comparison without revealing useful prefix timing. Empty and non-string values are rejected before it runs.

Attach middleware to every browser route that changes state:

```php
$router->post('/api/issues', 'api/issues/store.php')->only('csrf');
$router->patch('/api/issues/{id}', 'api/issues/update.php')->only('csrf');
$router->delete('/api/issues/{id}', 'api/issues/destroy.php')->only('csrf');
```

Registration, login, and logout are also state-changing browser requests and need an intentional policy. A single forgotten unsafe route remains a working bypass.

GET, HEAD, and OPTIONS are skipped because they must be read-only. If a GET route deletes or updates data, changing the CSRF list is not the fix—the route violates the safe-method contract.

The built-in DALT middleware returns plain text with status `419`. If the API promises one JSON error envelope, create an API-specific CSRF middleware that preserves the same validation while returning that envelope. Do not discover the content-type difference accidentally in React.

## SameSite is defense in depth

`SameSite=Lax` prevents many cross-site cookie sends, and it is worth keeping. It does not replace a server-validated token: browser behavior, same-site subdomains, and future request patterns create boundaries broader than one cookie flag.

Likewise, checking `Origin` or `Sec-Fetch-Site` can add evidence, but a course application should not replace its session-bound token with a fragile `Referer` string check.

## Test the locked door and the working key

The negative test proves a missing token cannot reach the mutation:

```php
$before = countIssues();
$response = request('POST', '/api/issues', withoutToken: true);

expect($response->statusCode)->toBe(419);
expect(countIssues())->toBe($before);
```

The state assertion is essential. A handler that inserts first and reports `419` later is still vulnerable.

Pair it with the positive case:

```php
$response = request('POST', '/api/issues', token: csrf_token());

expect($response->statusCode)->toBe(201);
expect(countIssues())->toBe($before + 1);
```

Without the positive test, middleware that rejects every request passes the negative test perfectly. Security evidence needs both “wrong key stays out” and “right key gets in.”

## Try it

**Workspace:** continue with `.dalt/workspace/fs06-auth-boundaries`. If needed, recreate it:

```bash
mkdir -p .dalt/workspace
cp -r .dalt/course/fullstack/auth-boundaries-lab/starter \
  .dalt/workspace/fs06-auth-boundaries
```

**Starting state:** `scripts/csrf.php` creates one session token and passes synthetic GET and POST requests through DALT's real middleware. Run:

```bash
php .dalt/workspace/fs06-auth-boundaries/scripts/csrf.php
```

The exact output is:

```text
token characters: 64
missing token status: 419
writes after missing token: 0
matching header status: 200
writes after matching header: 1
safe GET status: 200
```

**Expected result:** a tokenless POST never reaches the write callback, the matching header permits exactly one write, and a read-only GET needs no token.

**Reset:** keep the workspace for FS06.5, or delete `.dalt/workspace/fs06-auth-boundaries`.

## What to notice

The cookie authenticates the session automatically; the separate header proves the page had access to our session-bound token. Middleware rejects the request before the handler, and the paired outcomes prove it is selective rather than simply broken.

## Check your understanding

1. Why can a request be authenticated but still forged?
2. Why are the session cookie and CSRF token separate?
3. How does a JSON client send the token to DALT?
4. Why do we need both negative and positive middleware tests?

<details><summary>Check your answers</summary>

1. The browser attaches destination cookies even when another site caused the request.
2. The cookie travels automatically; the separate secret must be read deliberately from our application context.
3. In the `X-CSRF-Token` header, which DALT receives as `HTTP_X_CSRF_TOKEN`.
4. One proves missing proof is rejected without mutation; the other proves valid requests are not rejected unconditionally.
</details>

## Next

The server can now trust identity and request intent; next it must decide what that user may do to each resource.

<details><summary>Maintainer source record</summary>

- Source dossier: Full Stack Open Part 4 research notes.
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: OWASP CSRF Prevention Cheat Sheet; PHP `random_bytes()` and `hash_equals()` manuals.
- Versions: PHP 8.4.1; DALT's synchronizer-token middleware as inspected on 2026-08-22.
- Consulted: 2026-08-22.
- Curriculum authority: DALT Fullstack theory curriculum, Batch 8, FS06.4.
- DALT files inspected: `framework/Core/functions.php`, `Request.php`, `Middleware/Csrf.php`, `Middleware.php`, `Router.php`, and `MiddlewareTest.php`.
- Reused material: cookie-driven threat, form/header transports, safe methods, `hash_equals()`, route coverage, 419 behavior, and paired mutation tests from the former FS06.2.
</details>
