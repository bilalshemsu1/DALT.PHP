Registration can begin a session, but an existing account still has no way back in.
We will add login and logout, show the current identity in our shared application
header, and keep the server—not React—as the owner of authentication state.

## Add the session routes

Place the next three routes beside registration in `routes/routes.php`:

```php
$router->get('/login', 'auth/show.php')->only('guest');
$router->post('/api/session', 'api/auth/login.php')
    ->only(['guest', 'csrf']);
$router->delete('/api/session', 'api/auth/logout.php')
    ->only(['auth', 'csrf']);
```

A successful login creates an authenticated session resource. Logout deletes that
resource. Both mutations require a CSRF token, and the `guest`/`auth` middleware keep
them available only in the state where they make sense.

## Reuse one authentication document

Both `/register` and `/login` can use the shell we already built. Update
`app/Http/controllers/auth/show.php` to select the mode from the matched request path:

```php
use Core\App;
use Core\Request;

$request = App::resolve(Request::class);

view('auth.view.php', [
    'mode' => $request->path() === '/login' ? 'login' : 'register',
]);
```

In `resources/views/auth.view.php`, normalize that value and send it to React:

```php
$mode = ($mode ?? null) === 'login' ? 'login' : 'register';

$pageData = json_encode(
    ['mode' => $mode, 'csrfToken' => csrf_token()],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        | JSON_THROW_ON_ERROR,
);
```

Use the mode in the document title too. The page still has one Vite entry and one
React root; only its product state changes.

## Verify credentials through DALT

Create `app/Http/controllers/api/auth/login.php`. Normalize the email, preserve the
password exactly, and reject malformed input before asking the database:

```php
$email = is_string($emailInput) ? strtolower(trim($emailInput)) : '';
$password = is_string($passwordInput) ? $passwordInput : '';

if (!Validator::email($email) || !Validator::string($password, 1, 72)) {
    return Response::json([
        'errors' => ['email' => 'Enter your email and password.'],
    ], 422);
}
```

Then let `Authenticator::attempt()` perform the parameterized lookup and
`password_verify` check:

```php
$auth = new Authenticator();

if (!$auth->attempt($email, $password)) {
    return Response::json([
        'errors' => [
            'email' => 'Those credentials do not match our records.',
        ],
    ], 422);
}

return Response::json([
    'user' => $auth->user(),
    'redirectTo' => $auth->intended('/'),
]);
```

An unknown email and a wrong password deliberately receive the same public message.
The response does not become an account-discovery tool.

`attempt()` also calls the same rotation-before-identity logic registration used.
`intended('/')` consumes a safe local URL remembered by DALT's page middleware and
falls back home. We will make that behavior visible when we protect pages in the next
lesson.

## Destroy the server session on logout

Create `app/Http/controllers/api/auth/logout.php`:

```php
use Core\Authenticator;
use Core\Response;

(new Authenticator())->logout();

return Response::json([
    'message' => 'You are logged out.',
    'redirectTo' => '/login',
]);
```

This is more than clearing a React variable. DALT destroys the native session data,
empties `$_SESSION`, and expires the browser cookie with the same attributes used to
create it.

## Teach the React client both modes

Change `AuthPageData` in `resources/app/auth-data.ts`:

```ts
export type AuthPageData = {
  mode: 'register' | 'login'
  csrfToken: string
}
```

Accept either exact value in the runtime parser. Then add `loginAccount`:

```ts
export async function loginAccount(
  data: AuthPageData,
  fields: { email: string; password: string },
): Promise<{ redirectTo: string }> {
  const response = await fetch('/api/session', {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams({
      _token: data.csrfToken,
      email: fields.email,
      password: fields.password,
    }),
  })
```

For 422, require `errors.email` to be a string and throw the same field-aware error
class the form already understands. On success, return only the checked
`redirectTo`.

In `AuthPage.tsx`, render name and confirmation only for registration. The password
input changes its browser meaning with the mode:

```tsx
<input
  id="password"
  name="password"
  type="password"
  autoComplete={data.mode === 'register'
    ? 'new-password'
    : 'current-password'}
  minLength={data.mode === 'register' ? 8 : 1}
  maxLength={72}
  // existing controlled value and error attributes
/>
```

Select the request during submit:

```tsx
const result = data.mode === 'register'
  ? await registerAccount(data, {
      name, email, password, passwordConfirmation,
    })
  : await loginAccount(data, { email, password })

window.location.assign(result.redirectTo)
```

After a validation or credential rejection, preserve the email but clear the two
password states. Add the alternate link beneath the form: registration points to
`/login`; login points to `/register`.

## Bootstrap identity into the shared shell

The application header should render the identity DALT has already authenticated.
Create `resources/app/app-shell-data.ts` with this shape:

```ts
export type AppShellData = {
  user: { id: number; email: string } | null
  csrfToken: string
}
```

`readAppShellData()` should accept either `null` or a record with a positive integer
ID and string email, and should require a string CSRF token.

In each application PHP view—`welcome.view.php`, `workspaces/show.view.php`, and
`projects/show.view.php`—encode the same server-derived data:

```php
$auth = new Core\Authenticator();
$shellData = json_encode(
    ['user' => $auth->user(), 'csrfToken' => csrf_token()],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        | JSON_THROW_ON_ERROR,
);
```

Place its script beside the page-specific bootstrap:

```php
<script id="app-shell-data" type="application/json"><?= $shellData ?></script>
```

Read it once in each non-auth branch of `main.tsx` and pass it to the router layout:

```tsx
const shell = readAppShellData()

const router = createBrowserRouter([{
  path: '/',
  element: <AppLayout shell={shell} />,
  children: [/* existing routes */],
}])
```

Keep `user` nullable for this lesson. A visitor who has not registered yet can still
see the application with Log in and Register links. The next lesson will make the
product pages private.

## Put logout in the header

Add a `logout` request to `app-shell-data.ts` using the same method override pattern
as our deletes:

```ts
body: new URLSearchParams({
  _token: csrfToken,
  _method: 'DELETE',
})
```

In `AppLayout`, guests see account links. A signed-in user sees the server-provided
email and a real button:

```tsx
{shell.user === null ? (
  <nav aria-label="Account">
    <a href="/login">Log in</a>
    <a href="/register">Register</a>
  </nav>
) : (
  <div>
    <span title={shell.user.email}>{shell.user.email}</span>
    <button onClick={() => void endSession()} disabled={loggingOut}>
      {loggingOut ? 'Logging out…' : 'Log out'}
    </button>
  </div>
)}
```

`endSession` waits for the confirmed response before performing a document navigation
to `/login`. If the request fails, keep the current page and expose a retryable error
instead of pretending the server session ended.

## Prove both sides of the session

Extend `AuthenticationTest.php` with three behaviors:

```php
expect($unknown->status())->toBe(422)
    ->and($wrong->status())->toBe(422)
    ->and($unknown->content())->toBe($wrong->content())
    ->and($_SESSION['user'] ?? null)->toBeNull();
```

The successful test seeds a hash, places `/workspaces/7` in
`auth.intended`, logs in, and proves the response consumes that exact path. The logout
test begins with a valid identity and proves `Session::active()` becomes false and
`$_SESSION` becomes empty.

Add `resources/app/auth-workflow.test.tsx` for the browser boundary. Let MSW inspect
the CSRF token and email, return the uniform credential error, then prove the message
is connected to the form, the email remains, and the password is cleared.

Update the three older router tests to pass a small authenticated `AppShellData` value
to `AppLayout`.

Run everything this change can disturb:

```bash
php vendor/bin/pest tests/Feature/AuthenticationTest.php \
  tests/Feature/IssueApiTest.php
npm run typecheck
npm run lint
npm test
npm run build
```

The PHP run should report eighteen tests and 141 assertions. React should report four
files and sixteen tests. In the browser, a registered account can log out, return to
the login screen, log back in, and see its email in the header after refresh.

```bash
git add routes/routes.php app/Http/controllers/api/auth \
  app/Http/controllers/auth/show.php resources/views \
  resources/app tests/Feature/AuthenticationTest.php
git commit -m "Add login and logout"
```

We now have a complete identity lifecycle, but the product pages still allow a guest.
Next we will protect HTML routes with DALT's redirecting middleware and JSON routes
with an API-shaped 401 boundary.
