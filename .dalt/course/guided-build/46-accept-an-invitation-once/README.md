# Accept an invitation once

We can create a secret link; now it must survive login or registration and grant
membership only to the invited account. We will hash the presented token, lock its
database row, and finish membership plus acceptance in one retry-safe transaction.

## Give the link a public landing page

Add these routes in `routes/routes.php`:

```php
$router->get(
    '/invitations/{token}',
    'invitations/show.php',
);
$router->post(
    '/api/invitations/{token}/accept',
    'api/invitations/accept.php',
)->only([ApiAuth::class, 'csrf']);
```

The landing page is public because a person may need to log in or create an account.
Acceptance is authenticated and CSRF-protected because it changes workspace access.

Create `app/Http/controllers/invitations/show.php`. Require the exact token format
our generator produces:

```php
if (
    !is_string($token)
    || preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1
) {
    abort(404);
}
```

If the visitor is a guest, ask DALT to remember this safe GET URL:

```php
$auth = new Authenticator();

if ($auth->guest()) {
    $auth->rememberIntended($request);
}
```

Hash the presented token before querying. Join the workspace name and derive pending,
expired, revoked, or accepted status from timestamps. A missing hash returns 404. Pass
the public invitation, current user, raw URL token, and CSRF token to a new
`resources/views/invitation.view.php` shell with `data-page="invitation"`.

The raw token appears in the URL already; putting it in this page's JSON lets React
submit it without adding another secret. Do not log it or store it elsewhere.

## Preserve registration as well as login

Login already returns `$auth->intended('/')`. Registration still always returns `/`.
In `api/auth/register.php`, reuse one authenticator:

```php
$auth = new Authenticator();
$auth->login($user);

return Response::json([
    'user' => $user,
    'message' => "Welcome, {$name}. Your account is ready.",
    'redirectTo' => $auth->intended('/'),
], 201);
```

Now both authentication paths consume the remembered invitation once. DALT's
`rememberIntended` accepts only local safe GET URLs, so an attacker cannot turn this
into an external redirect.

## Lock before deciding

Create `app/Http/controllers/api/invitations/accept.php`. Validate the token, require
the current user, and begin `Transaction::run`. The first query must lock the matching
row:

```php
$invitation = $database->query(
    'SELECT id, workspace_id, email, role,
            expires_at, accepted_at, revoked_at
     FROM workspace_invitations
     WHERE token_hash = :token_hash
     FOR UPDATE',
    ['token_hash' => hash('sha256', $token)],
)->find();
```

`FOR UPDATE` makes a concurrent acceptance wait. When it wakes, it sees the first
transaction's accepted timestamp instead of independently making the same decision.

Reject the states in a deliberate order:

```php
if ($invitation === false) abort(404);

if (strtolower($user['email']) !== $invitation['email']) {
    abort(403);
}

if ($invitation['revoked_at'] !== null) abort(410);

if (
    $invitation['accepted_at'] === null
    && strtotime((string) $invitation['expires_at']) <= time()
) {
    abort(410);
}
```

The authenticated email must match the normalized invited email. HTTP 410 tells a
holder that a once-valid link is no longer usable. An accepted invitation may be
retried even after its original expiry; the retry does not grant anything new.

## Make membership and acceptance one operation

Look for an existing membership under the same transaction. If none exists, insert
the invitation's role:

```php
if ($existing === false) {
    $database->query(
        'INSERT INTO workspace_memberships
            (workspace_id, user_id, role)
         VALUES (:workspace_id, :user_id, :role)',
        [
            'workspace_id' => $invitation['workspace_id'],
            'user_id' => $user['id'],
            'role' => $invitation['role'],
        ],
    );
}
```

Then mark unresolved invitations accepted:

```php
if ($invitation['accepted_at'] === null) {
    $database->query(
        'UPDATE workspace_invitations
         SET accepted_at = CURRENT_TIMESTAMP
         WHERE id = :id',
        ['id' => $invitation['id']],
    );
}
```

Return the workspace ID plus whether membership or acceptance already existed. The
JSON response includes `/workspaces/{id}` as its server-confirmed destination. A
repeated click returns success with “You already belong to this workspace” and never
creates a duplicate because the membership primary key and row lock agree with the
PHP logic.

## Build the invitation page

Create `resources/app/InvitationPage.tsx`. Read and runtime-check the page JSON:
workspace name, invited email, role, status, current user, token, and CSRF token.

Render the correct action for each state:

- expired, revoked, or accepted: a visible resolved-state message;
- pending guest: links to `/login` and `/register`;
- pending wrong account: name the invited email and show no acceptance button;
- pending matching account: show `Accept invitation`.

The acceptance request is focused:

```tsx
const response = await fetch(
  `/api/invitations/${data.token}/accept`,
  {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams({ _token: data.csrfToken }),
  },
)
```

Run the shared 401 handler, require the response's `redirectTo`, and navigate only
after success. On a 410 or other failure, keep the page in place with a message that
the invitation may have expired or been revoked.

In `main.tsx`, recognize `data-page="invitation"` before the auth and workspace
branches. Render this public screen without `SessionProvider`; its server bootstrap
already describes the exact invitation-time user and CSRF state.

## Prove the failure paths, not only success

Extend `WorkspaceAuthorizationTest.php` with a complete lifecycle:

1. an owner creates an invitation for Grace;
2. a guest opens the landing page and DALT remembers its URL;
3. Grace accepts it;
4. Grace repeats the same request;
5. PostgreSQL contains one membership and one accepted timestamp.

Add separate token rows and prove the wrong account receives 403, revoked and expired
tokens receive 410, and an unknown valid-looking token receives 404. The row lock is
the concurrency mechanism; the repeat test proves the observable idempotent result.

Add an authentication test requiring registration to return the remembered invitation
and clear it from the session.

Create `invitation-acceptance.test.tsx`: a guest sees both authentication choices, a
matching account submits the CSRF token and follows the server destination, and a
different account cannot see the acceptance control.

Run:

```bash
php artisan test \
  tests/Feature/AuthenticationTest.php \
  tests/Feature/WorkspaceAuthorizationTest.php
npm run typecheck
npm run lint
npm test -- --run
npm run build
```

The backend passes twenty focused tests with 121 assertions. React passes 27 tests
across eight files. Invitations now survive authentication and resolve safely. The
last collaboration lesson will let owners change or remove people—and will defend
the final owner under concurrent administration.
