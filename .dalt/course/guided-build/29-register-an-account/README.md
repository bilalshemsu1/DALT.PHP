Our tracker can now store serious work, but it still cannot say who that work belongs
to. We will begin the identity boundary with registration: a visitor creates an
account, DALT stores a password hash instead of the password, and the new account
becomes the signed-in session.

## Start with the users table we already have

Open `database/migrations/001_create_users_table.sql`. It came with the clean DALT
project and was applied when we first ran the migrations. The important columns for
this lesson are already present:

```sql
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

The unique email constraint is the final database boundary against duplicate
accounts. `VARCHAR(255)` also leaves room for `PASSWORD_DEFAULT` to use a different,
longer hash format in a future PHP release.

We will never store or return the submitted password itself.

## Register the account routes

Add these routes near the top of `routes/routes.php`:

```php
$router->get('/register', 'auth/show.php')->only('guest');
$router->post('/api/register', 'api/auth/register.php')
    ->only(['guest', 'csrf']);
```

`guest` keeps an already signed-in person out of registration. The POST also carries
`csrf` because it creates durable state and starts an authenticated session.

Create `app/Http/controllers/auth/show.php` as the small document controller:

```php
<?php

declare(strict_types=1);

view('auth.view.php');
```

## Give React safe page data

Create `resources/views/auth.view.php`. The PHP view remains a shell: it generates the
CSRF token and tells React which authentication screen it should render.

```php
$pageData = json_encode(
    [
        'mode' => 'register',
        'csrfToken' => csrf_token(),
    ],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        | JSON_THROW_ON_ERROR,
);
```

In the document body, mount the same application entry we already use:

```php
<body data-page="auth">
  <div id="root"></div>
  <script id="auth-page-data" type="application/json"><?= $pageData ?></script>
  <noscript>This page needs JavaScript to create your account.</noscript>
</body>
```

Create `resources/app/auth-data.ts`. Parse the bootstrap before trusting it, just as we
do for workspaces and projects:

```ts
export type AuthPageData = {
  mode: 'register'
  csrfToken: string
}

export function readAuthPageData(): AuthPageData {
  const source = document.getElementById('auth-page-data')
  if (!(source instanceof HTMLScriptElement)) {
    throw new Error('Authentication page data was not found.')
  }

  const value: unknown = JSON.parse(source.textContent ?? '')
  if (!isRecord(value) || value.mode !== 'register') {
    throw new Error('Authentication page data has an invalid mode.')
  }

  return { mode: 'register', csrfToken: stringAt(value, 'csrfToken') }
}
```

The complete parser should use the same guarded `try`/`catch` around `JSON.parse` as
our earlier page-data modules.

## Validate and hash on the server

Create `app/Http/controllers/api/auth/register.php`. Read every submitted field from
DALT's request and normalize only the values where surrounding whitespace is not
meaningful:

```php
$name = is_string($nameInput) ? trim($nameInput) : '';
$email = is_string($emailInput) ? strtolower(trim($emailInput)) : '';
$password = is_string($passwordInput) ? $passwordInput : '';
$confirmation = is_string($confirmationInput) ? $confirmationInput : '';
```

Do not trim a password. Spaces may be an intentional part of it. Accumulate the
field errors so React can explain the whole submission at once:

```php
$errors = [];

if (!Validator::string($name, 2, 60)) {
    $errors['name'] = 'Use between 2 and 60 characters.';
}
if (!Validator::email($email) || strlen($email) > 254) {
    $errors['email'] = 'Enter a valid email address.';
}
if (!Validator::string($password, 8, 72)) {
    $errors['password'] = 'Use between 8 and 72 characters.';
}
if ($password !== $confirmation) {
    $errors['password_confirmation'] = 'Enter the same password again.';
}

if ($errors !== []) {
    return Response::json(['errors' => $errors], 422);
}
```

Check the normalized email before inserting. The table's unique constraint remains
the final authority; this lookup gives the ordinary browser case a useful field
message:

```php
$existing = $database
    ->query('SELECT id FROM users WHERE email = :email', ['email' => $email])
    ->find();

if ($existing !== false) {
    return Response::json([
        'errors' => ['email' => 'An account already uses this email address.'],
    ], 422);
}
```

Hash only after validation succeeds, then bind the result into the insert:

```php
$database->query(
    'INSERT INTO users (name, email, password)
     VALUES (:name, :email, :password)',
    [
        'name' => $name,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
    ],
);
```

`password_hash` creates a salted one-way representation. At login we will give the
submitted password and this stored hash to `password_verify`; we will not hash the
submitted value again and compare two strings.

## Start the new account's session

Build the small public identity from the inserted row and pass it to DALT's
`Authenticator`:

```php
$user = [
    'id' => (int) $database->getConnection()->lastInsertId(),
    'email' => $email,
];

(new Authenticator())->login($user);

return Response::json([
    'user' => $user,
    'message' => "Welcome, {$name}. Your account is ready.",
    'redirectTo' => '/',
], 201);
```

`login()` rotates the session ID before storing this identity. Registration therefore
ends with a real signed-in server session, not a React-only boolean. Notice that the
response and session identity contain no password hash.

## Send the registration request from React

In `auth-data.ts`, add a `RegistrationValidationError` carrying the four possible
field messages. Then post the form as URL-encoded data:

```ts
const response = await fetch('/api/register', {
  method: 'POST',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/x-www-form-urlencoded',
  },
  body: new URLSearchParams({
    _token: data.csrfToken,
    name: fields.name,
    email: fields.email,
    password: fields.password,
    password_confirmation: fields.passwordConfirmation,
  }),
})
```

For a 422 response, runtime-check `value.errors` and throw the field-aware error. For
success, return only the checked `message` and `redirectTo` strings.

Create `resources/app/AuthPage.tsx`. Use controlled name, email, password, and
confirmation inputs with the browser autocomplete values `name`, `email`, and
`new-password`. Keep the password hint visible and connect every error with
`aria-describedby`:

```tsx
<label htmlFor="password">Password</label>
<input
  id="password"
  name="password"
  type="password"
  autoComplete="new-password"
  minLength={8}
  maxLength={72}
  value={password}
  onChange={(event) => setPassword(event.target.value)}
  aria-invalid={errors.password !== undefined}
  aria-describedby="password-hint password-error"
/>
<p id="password-hint">Use 8–72 characters.</p>
<FieldError id="password-error" message={errors.password} />
```

On submit, clear old errors, disable the button, and navigate only after the server
confirms creation:

```tsx
try {
  const result = await registerAccount(data, {
    name, email, password, passwordConfirmation,
  })
  window.location.assign(result.redirectTo)
} catch (error) {
  if (error instanceof RegistrationValidationError) {
    setErrors(error.errors)
  } else {
    setNotice(
      'Your account could not be created. Check the connection and try again.',
    )
  }
}
```

Finally, route the new shell in `resources/app/main.tsx` before the existing page
branches:

```tsx
if (document.body.dataset.page === 'auth') {
  applicationScreen = <AuthPage data={readAuthPageData()} />
} else if (document.body.dataset.page === 'workspaces') {
  // existing workspace router
}
```

## Prove that the password never becomes stored data

Create `tests/Feature/AuthenticationTest.php` with an in-memory database and the users
migration. Cover rejected input, successful registration, and a duplicate normalized
email. The successful case must inspect the stored value:

```php
expect($response->status())->toBe(201)
    ->and($user['email'])->toBe('ada@example.com')
    ->and($user['password'])->not->toBe('correct horse')
    ->and(password_verify('correct horse', $user['password']))->toBeTrue()
    ->and($_SESSION['user'])->toBe([
        'id' => (int) $user['id'],
        'email' => 'ada@example.com',
    ]);
```

Start a real file session in the test setup because `Authenticator::login()` genuinely
rotates the native session ID. Destroy it in `afterEach` so tests remain independent.

Run the boundaries we changed:

```bash
php vendor/bin/pest tests/Feature/AuthenticationTest.php \
  tests/Feature/IssueApiTest.php
npm run typecheck
npm run lint
npm test
npm run build
```

The PHP run should report fifteen tests and 126 assertions; the frontend still reports
fifteen tests. In the browser, `/register` keeps rejected values except passwords,
creates one normalized account, returns home, and a second registration using the same
email is refused.

```bash
git add routes/routes.php app/Http/controllers/api/auth/register.php \
  app/Http/controllers/auth/show.php resources/views/auth.view.php \
  resources/app/AuthPage.tsx resources/app/auth-data.ts \
  resources/app/main.tsx tests/Feature/AuthenticationTest.php
git commit -m "Add account registration"
```

We can create a session-backed identity now. Next we will let an existing account log
in, expose that identity in the application shell, and destroy the server session on
logout.
