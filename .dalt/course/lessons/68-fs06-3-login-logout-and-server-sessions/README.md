# FS06.3 — Login, logout, and server-side sessions

Lesson ID: FS06.3
Lesson format: Concise theory
Part: 06 — Testing, users, and authorization
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Foundation
Prerequisites: FS06.2
Last reviewed: 2026-08-22

We will turn valid credentials into a rotated server-side session and make logout invalidate that identity.

> **Helpful background:** [Users and password hashing](/learn/lessons/40-fs06-2-users-passwords-sessions-and-csrf)

## What we will learn

- distinguish a password check, an authenticated identity, and a session cookie;
- rotate the session identifier when privilege changes;
- expose login, logout, and current-user behavior without publishing credentials.

## The password is not the session

A login request presents an email and password once. If they match, the server records a safe identity in its session store:

```text
email + password → verify stored hash → rotate session ID
                                      → session { user: { id, email } }
browser cookie ← opaque session ID ─────────────────────────────┘
```

The cookie is an opaque lookup key. It does not contain user claims for React to decode. PHP uses it to locate session data on the server, and DALT reads the canonical `id` and `email` stored there.

This is why we do not add JWT, put a credential in `localStorage`, or copy a raw session ID into application state. The browser carries the HttpOnly cookie automatically; the frontend later asks `GET /api/me` which user the server recognizes.

## DALT verifies, rotates, then stores identity

DALT's `Authenticator::attempt()` fetches one user by a bound email value and passes the submitted password to `password_verify()`. Unknown email and wrong password both return `false`.

The successful path calls `login()`:

```php
public function login(array $user): void
{
    $identity = $this->identityFrom($user);

    Session::regenerate();
    Session::put('user', $identity);
}
```

Rotation happens before authenticated state is stored. This prevents **session fixation**: if an attacker knew the anonymous session ID before login, that identifier does not become the authenticated one afterward.

The session should hold the minimum stable identity needed on later requests, not the password hash or a complete database row. DALT accepts only a positive integer ID and non-empty email. Authorization will use the ID to load current resource facts from PostgreSQL.

## Login gives both failures the same public shape

The login endpoint should not reveal whether an email exists:

```php
$auth = App::resolve(Authenticator::class);

if (!$auth->attempt($email, $password)) {
    return Response::json(['error' => [
        'code' => 'invalid_credentials',
        'message' => 'Those credentials do not match our records.',
    ]], 401);
}

return Response::json(['data' => [
    'id' => (string) $auth->id(),
    'email' => $auth->user()['email'],
]]);
```

The database query may distinguish “no row” from “wrong verifier.” The public response deliberately does not. Otherwise repeated login attempts become an account-discovery tool.

Do not log the submitted password, stored hash, session ID, or cookie header while diagnosing this flow. A safe log can name the operation, outcome, and an internal request identifier without recording credentials.

## Current user restores application identity

React state disappears on refresh; the server session does not. A current-user endpoint lets the next page load recover identity:

```php
$user = $auth->user();

return $user === null
    ? Response::json(['error' => [
        'code' => 'unauthenticated',
        'message' => 'Sign in to continue.',
    ]], 401)
    : Response::json(['data' => [
        'id' => (string) $user['id'],
        'email' => $user['email'],
    ]]);
```

Authentication answers who the server recognizes. It does not yet say whether that user may open a workspace or edit an issue.

## Cookie flags have narrow jobs

DALT configures native PHP sessions to use cookies only, strict session IDs, and intentional cookie attributes:

| Attribute | What it does |
|---|---|
| `HttpOnly` | prevents browser JavaScript from reading the session cookie |
| `Secure` | sends the cookie only over HTTPS when enabled |
| `SameSite=Lax` | reduces many cross-site cookie sends |
| `Path` | limits which paths receive the cookie |

These attributes complement one another. `HttpOnly` cannot stop malicious script already running in our page from making requests as the user. `SameSite` is useful defense in depth, but the next lesson will still require explicit CSRF proof for unsafe operations.

The configured lifetime is policy, not a precise timer guaranteeing a session file disappears at one exact second. Expired or otherwise missing session state should produce ordinary unauthenticated behavior, not a server error.

## Logout must destroy server state

DALT logout calls `Session::destroy()`. It clears session data, destroys the native server session, expires the cookie with the same scope, and removes the cookie from the current request state.

```php
$auth->logout();

return Response::json(null, 204);
```

Deleting a React variable is not logout. Expiring only the browser cookie is also incomplete: anyone who retained the old identifier could still present it while the server session exists.

The behavior proof is a sequence:

```text
anonymous /api/me → 401
login → success and rotated ID
/api/me → safe user
logout → 204
replay old cookie at /api/me → 401
```

Checking that a local variable became `null` does not prove invalidation. Replaying the old credential through the server boundary does.

## Try it

**Workspace:** continue with `.dalt/workspace/fs06-auth-boundaries`. If it is absent:

```bash
mkdir -p .dalt/workspace
cp -r .dalt/course/fullstack/auth-boundaries-lab/starter \
  .dalt/workspace/fs06-auth-boundaries
```

**Starting state:** the copied `scripts/sessions.php` uses DALT's real `Database`, `Authenticator`, and `Session` with isolated in-memory user data and a temporary native session directory. Run:

```bash
php .dalt/workspace/fs06-auth-boundaries/scripts/sessions.php
```

The exact output is:

```text
wrong credentials accepted: no
correct credentials accepted: yes
session rotated on login: yes
current user: alice@example.com
old session authenticates after logout: no
```

**Expected result:** only the correct password establishes identity, login changes the session identifier, the session returns a safe current user, and replay after logout finds no identity.

**Reset:** keep the workspace for FS06.4, or delete `.dalt/workspace/fs06-auth-boundaries`.

## What to notice

Password verification is a moment; the server-side session carries its result across requests. Rotation protects the privilege transition. The browser stores only an opaque identifier, and logout is proven at the server by replaying that old identifier.

## Check your understanding

1. What information does the browser's session cookie contain in this design?
2. Why does DALT rotate before storing the user?
3. Why does React call a current-user endpoint after refresh?
4. What is the strongest proof that logout worked?

<details><summary>Check your answers</summary>

1. Only an opaque session identifier; user meaning remains in server-side session data.
2. So an anonymous identifier known before login never acquires authenticated privilege.
3. React memory was lost, while the cookie and server session may still identify the user.
4. Replay the old cookie through a protected server request and receive the unauthenticated outcome.
</details>

## Next

The browser now sends identity automatically; next we will prevent another site from abusing that convenience for unwanted writes.

<details><summary>Maintainer source record</summary>

- Source dossier: `FSO_PART_04.md`.
- Official sources: PHP session security and session ID regeneration manuals; OWASP Session Management Cheat Sheet.
- Versions: PHP 8.4.1 native file sessions; DALT session configuration inspected on 2026-08-22.
- Consulted: 2026-08-22.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 8, FS06.3.
- DALT files inspected: `framework/Core/Authenticator.php`, `framework/Core/Session.php`, `config/session.php`, `NativeSessionTest.php`, and `AuthenticatorTest.php`.
- Reused material: verification, uniform login failure, regenerate-before-put ordering, opaque cookie model, current-user endpoint, cookie flags, and server-side logout from the former FS06.2.
</details>
