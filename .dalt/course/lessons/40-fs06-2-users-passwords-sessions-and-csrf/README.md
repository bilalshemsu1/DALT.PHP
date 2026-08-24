# FS06.2 — Users and password hashing

Lesson ID: FS06.2
Lesson format: Concise theory
Part: 06 — Testing, users, and authorization
Status: Published
Estimated effort: 30–40 minutes
Difficulty: Foundation
Prerequisites: FS06.1
Last reviewed: 2026-08-22

We will store users without storing recoverable passwords or exposing password hashes through the API.

> **Helpful background:** [Test backend behavior through HTTP](/learn/lessons/39-fs06-1-backend-api-behavior-tests)

## What we will learn

- model a user row and protect login identity with database uniqueness;
- hash submitted passwords with PHP's password API and verify them later;
- keep the database representation separate from public user JSON.

## Store a verifier, not the password

A password is evidence the user presents. We need to answer “does this submission match?” without keeping a value we can decrypt and recover.

PHP provides a deliberately paired API:

```php
$hash = password_hash($password, PASSWORD_DEFAULT);

$matches = password_verify($submittedPassword, $hash);
```

`password_hash()` uses a password-specific one-way algorithm and generates a random salt for us. The result contains the algorithm, salt, and cost information needed by `password_verify()`.

We do not generate a salt ourselves, encrypt the password, or use a fast general-purpose function such as `sha256`. Password hashing is intentionally expensive so guesses cost an attacker time.

Random salt means the same password produces different stored strings:

```php
$first = password_hash('same password', PASSWORD_DEFAULT);
$second = password_hash('same password', PASSWORD_DEFAULT);

var_dump($first === $second); // false
```

That is why login cannot hash the submission again and compare two strings. The new call would use a new salt. We fetch the user by email, then let `password_verify()` interpret the stored hash.

## Give the hash room to evolve

The users table stores the hash in a column large enough for future algorithms:

```sql
CREATE TABLE users (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT users_email_unique UNIQUE (email)
);
```

PHP's `PASSWORD_DEFAULT` is allowed to change over time, so its output length can change. The PHP manual recommends a column that can grow beyond 60 bytes and names 255 bytes as a good choice.

The unique constraint is the final protection for login identity. An application may check whether an email is available to return a friendly message, but two registrations can race between that check and their inserts. PostgreSQL decides which write wins.

Choose and document one email identity rule. A small application can normalize before storage:

```php
$email = mb_strtolower(trim($input['email']));
$hash = password_hash($input['password'], PASSWORD_DEFAULT);

$database->query(
    'INSERT INTO users (email, password) VALUES (?, ?)',
    [$email, $hash],
);
```

Normalization belongs before both lookup and insertion. Otherwise registration stores `Alice@example.com` while login searches for `alice@example.com` and the same person sees two identities.

The current DALT registration scaffold limits passwords to 8–72 bytes because the current default is bcrypt and bcrypt considers only the first 72 bytes. `strlen()` measures bytes; `mb_strlen()` measures characters. We should state that limit honestly rather than silently treating two long passwords with the same first 72 bytes as equivalent.

## Registration has two validation layers

The handler validates values the user can fix: an email-shaped string, a documented password length, and any required name fields. PostgreSQL enforces invariants that must survive concurrency, especially unique email.

```php
if (!Validator::email($email)) {
    $errors['email'] = 'Enter a valid email address.';
}

if (!is_string($password) || strlen($password) < 8 || strlen($password) > 72) {
    $errors['password'] = 'Use between 8 and 72 bytes.';
}
```

Never log the password, include it in an exception message, place it in a URL, or accept a client-supplied hash as though it were a password. The plaintext should exist only as briefly as the request needs it.

`password_needs_rehash()` can later tell us that an existing hash uses an older policy. A successful login is a possible time to replace it because the user has just supplied the correct plaintext. We do not need to build that upgrade path in this lesson, but the 255-byte column leaves room for it.

## Database rows are not API resources

The database needs the password hash. The browser never does.

```php
function userResource(array $row): array
{
    return [
        'id' => (string) $row['id'],
        'email' => $row['email'],
    ];
}
```

Naming public fields is safer than removing `password` after `SELECT *`. If a future migration adds recovery or security columns, an allowlist does not publish them accidentally.

A behavior test should prove all sides of the boundary:

```php
expect($row['password'])->not->toBe($submittedPassword);
expect(password_verify($submittedPassword, $row['password']))->toBeTrue();
expect(password_verify('wrong', $row['password']))->toBeFalse();
expect(array_keys(userResource($row)))->toBe(['id', 'email']);
```

A second user with the same password should have a different hash. That assertion defeats the plausible fake of a deterministic, unsalted general-purpose hash.

## Try it

**Workspace:** copy the bounded Batch 8 lab:

```bash
mkdir -p .dalt/workspace
cp -r .dalt/course/fullstack/auth-boundaries-lab/starter \
  .dalt/workspace/fs06-auth-boundaries
```

**Starting state:** do not edit the copy. Run:

```bash
php .dalt/workspace/fs06-auth-boundaries/scripts/passwords.php
```

The exact output is:

```text
stored plaintext: no
same password, same hash: no
correct password verifies: yes
wrong password verifies: no
public fields: id,email
```

Run it again. The conclusions remain the same even though the hidden hash values are newly generated.

**Expected result:** the submitted password is never stored, equal passwords receive unequal hashes, only the correct password verifies, and the public shape omits the hash.

**Reset:** keep the workspace for FS06.3, or delete `.dalt/workspace/fs06-auth-boundaries`.

## What to notice

Hashing and verification are a pair. Salt explains why equal passwords do not produce equal stored values. The public resource mapper creates a second boundary: authentication may read the hash on the server, but no response publishes it.

## Check your understanding

1. Why can we not hash a login submission and compare strings?
2. Why is the password column 255 characters rather than 60?
3. What does the unique constraint protect that a prior lookup cannot?
4. Why should a public user resource name fields explicitly?

<details><summary>Check your answers</summary>

1. Each hash uses a new random salt; `password_verify()` reads the salt and policy from the stored result.
2. `PASSWORD_DEFAULT` may change to an algorithm with longer output.
3. It resolves concurrent registrations that both observed the email as available.
4. An allowlist prevents the password hash and future internal columns from leaking accidentally.
</details>

## Next

We can now verify a password safely; next we will turn that successful check into a rotated server-side session.

<details><summary>Maintainer source record</summary>

- Source dossier: Full Stack Open Part 4 research notes.
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: PHP 8.4 manuals for `password_hash()`, `password_verify()`, and `password_needs_rehash()`; OWASP Password Storage Cheat Sheet.
- Versions: PHP 8.4.1 with `PASSWORD_DEFAULT` using bcrypt; PostgreSQL 18 syntax for the schema example.
- Consulted: 2026-08-22.
- Curriculum authority: DALT Fullstack theory curriculum, Batch 8, FS06.2.
- DALT files inspected: `framework/Core/Authenticator.php`, `framework/Core/Validator.php`, the authentication scaffold registration path, and `AuthScaffoldTest.php`.
- Reused material: 255-byte storage, PHP password APIs, unique normalized email, safe public representation, and same-password/different-hash proof from the former FS06.2.
</details>
