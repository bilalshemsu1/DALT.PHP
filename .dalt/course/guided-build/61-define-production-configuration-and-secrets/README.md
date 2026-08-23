# Define production configuration and secrets

Our application has one `.env` file, and every default in it was chosen to make local
development easy. Debug output is on, the database password is `dalt_local_password`,
and the session cookie is happy to travel over plain HTTP. None of that is a bug — it
is the right set of choices for a laptop, and exactly the wrong set for a deployment.

We will separate what a deployment may read from what it must be given, make our
application refuse to start when production is misconfigured, and add a check that
catches secrets before they reach somewhere public.

> **Helpful background:** OWASP's [secrets management cheat sheet](https://cheatsheetseries.owasp.org/cheatsheets/Secrets_Management_Cheat_Sheet.html)
> covers why a secret in a repository stays leaked even after it is deleted.

## Two kinds of configuration

Everything in `.env` is configuration, but it splits cleanly in two:

```text
non-secret defaults   APP_ENV, APP_URL, DB_HOST, DB_NAME, SESSION_SAME_SITE
                      → describe the shape of a deployment; safe to read

deployment secrets    DB_PASSWORD, and anything like it
                      → supplied by the environment; never committed, never
                        baked into an image, never printed in a log
```

The first kind belongs in the repository, because it documents how the application
expects to be run. The second kind must not be there at all — not commented out, not in
an example file, not "temporarily".

Create `.env.production.example`:

```ini
APP_NAME="DALT Issues"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://issues.example.com

SESSION_DRIVER=file
SESSION_SAME_SITE=Lax
# Identity travels in this cookie, so it must never cross a plain HTTP connection.
SESSION_SECURE_COOKIE=true

DB_DRIVER=pgsql
DB_HOST=db.internal
DB_PORT=5432
DB_NAME=issues
DB_USERNAME=issues_app
DB_CHARSET=utf8
# DB_PASSWORD is intentionally absent. Supply it from the deployment environment.
```

That last comment is the file's most important line. An example file with a plausible
password in it is how a placeholder becomes a production credential.

## Say what production requires

Right now a missing `DB_PASSWORD` in production would not stop anything. The
application would boot, serve the login page, and fail on the first request that
touched the database — a 500 that looks like an outage rather than a misconfiguration.

Create `app/Support/Configuration.php`:

```php
final class Configuration
{
    private const REQUIRED_IN_PRODUCTION = [
        'APP_URL', 'DB_HOST', 'DB_PORT', 'DB_NAME',
        'DB_USERNAME', 'DB_PASSWORD', 'SESSION_DRIVER',
    ];

    private const REJECTED_IN_PRODUCTION = [
        'DB_PASSWORD' => ['dalt_local_password', 'password', 'secret', 'changeme'],
    ];
```

The checks themselves:

```php
    public static function problems(array $environment): array
    {
        $env = $environment['APP_ENV'] ?? 'local';
        if ($env !== 'production') {
            return [];
        }

        $problems = [];

        foreach (self::REQUIRED_IN_PRODUCTION as $key) {
            if (trim($environment[$key] ?? '') === '') {
                $problems[] = "{$key} is required in production and is missing or empty.";
            }
        }
```

`trim(...) === ''` rather than `isset(...)`, because an environment variable set to an
empty string is the common shape of this mistake — a deployment template with a
placeholder nobody filled in.

Then the three defaults that are actively dangerous in production:

```php
        // A stack trace in production tells a stranger our file layout and our query
        // shapes. This is the single most expensive default to get wrong.
        if (self::isTruthy($environment['APP_DEBUG'] ?? 'false')) {
            $problems[] = 'APP_DEBUG must be false in production.';
        }

        // Session cookies carry identity; over plain HTTP they carry it to anyone on
        // the network.
        $url = trim($environment['APP_URL'] ?? '');
        if ($url !== '' && !str_starts_with($url, 'https://')) {
            $problems[] = 'APP_URL must use https in production.';
        }
        if (!self::isTruthy($environment['SESSION_SECURE_COOKIE'] ?? 'false')) {
            $problems[] = 'SESSION_SECURE_COOKIE must be true in production.';
        }
```

Notice the very first thing `problems()` does: outside production it returns an empty
list immediately. A guard that shouted during local development would be switched off
within a week, and then it would protect nothing.

`problems()` returns a list rather than throwing, so one deployment attempt reveals
every problem instead of the first one. `guard()` is the thin wrapper that turns that
list into a refusal:

```php
public static function guard(array $environment): void
{
    $problems = self::problems($environment);

    if ($problems !== []) {
        throw new RuntimeException(
            "This deployment is not configured to run in production:\n- "
            . implode("\n- ", $problems),
        );
    }
}
```

## Refuse to serve, rather than fail later

In `public/index.php`, add the guard as the first thing inside the try block:

```php
try {
    // Refuse to serve at all rather than fail later on the one request that needed
    // the missing value. In development this returns immediately.
    Configuration::guard($_ENV);

    Session::start($config->array('session'));
```

It goes *inside* the existing `try`, after `$exceptionHandler` is constructed, so a
refusal is reported and rendered by the handler we already have rather than becoming a
white page.

Watch what that produces. Start the server pretending to be a misconfigured production
deployment:

```bash
APP_ENV=production APP_DEBUG=true php artisan serve 127.0.0.1 8124
curl -i http://127.0.0.1:8124/login
```

```text
HTTP/1.1 500 Internal Server Error
…
This deployment is not configured to run in production:
- DB_PASSWORD still holds a development placeholder.
- APP_DEBUG must be false in production.
- APP_URL must use https in production.
- SESSION_SECURE_COOKIE must be true in production.
```

Four problems, all at once, before a single route ran.

Now do it with debug off, the way a real deployment would be configured:

```bash
APP_ENV=production APP_DEBUG=false APP_URL=http://insecure.example.test \
  php artisan serve 127.0.0.1 8126
```

```text
HTTP/1.1 500 Internal Server Error
Internal Server Error
```

The browser is told nothing. The reason is in `storage/logs/app.log`, where the person
deploying can read it and a stranger cannot. That is the whole point of `APP_DEBUG` and
it is why the guard refuses to let production turn it on.

And a complete configuration simply works:

```bash
APP_ENV=production APP_DEBUG=false APP_URL=https://issues.example.test \
  SESSION_SECURE_COOKIE=true DB_PASSWORD=a-real-deployment-secret \
  php artisan serve 127.0.0.1 8125
# /login → 200
```

## Check the environment without sending a request

A deployment pipeline should be able to ask "is this environment usable?" before it
routes traffic. `scripts/check-configuration.php` runs the same code:

```php
$problems = Configuration::problems($_ENV);

if ($problems === []) {
    echo "Configuration for APP_ENV={$env} is usable.\n";
    exit(0);
}

fwrite(STDERR, "This deployment is not configured to run in production:\n");
foreach ($problems as $problem) {
    fwrite(STDERR, "- {$problem}\n");
}

exit(1);
```

Problems go to stderr and the exit code is non-zero, because that is what a pipeline
reads. Add it to `composer.json`:

```json
"check:config": "php scripts/check-configuration.php",
"check:secrets": "php scripts/scan-for-secrets.php"
```

```bash
composer check:config
```

```text
Configuration for APP_ENV=local is usable.
```

## Test the rules, not the deployment

`tests/Unit/ConfigurationTest.php` starts from a complete production environment and
damages it one way at a time:

```php
function productionEnvironment(array $overrides = []): array
{
    return [
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'APP_URL' => 'https://issues.example.com',
        // …
        ...$overrides,
    ];
}
```

The most valuable test is the one that proves the guard stays quiet where it should:

```php
test('development is left alone', function () {
    // Everything a production deployment would be refused for, and none of it matters
    // locally. A guard that shouted here would simply be switched off.
    expect(Configuration::problems([
        'APP_ENV' => 'local',
        'APP_DEBUG' => 'true',
        'APP_URL' => 'http://localhost:8000',
        'DB_PASSWORD' => 'dalt_local_password',
    ]))->toBe([]);
});
```

And the required-value test loops rather than repeating itself, checking both the
missing key and the blank one:

```php
foreach (['APP_URL', 'DB_HOST', /* … */] as $key) {
    $missing = productionEnvironment();
    unset($missing[$key]);

    expect(Configuration::problems($missing))
        ->toContain("{$key} is required in production and is missing or empty.");

    expect(Configuration::problems(productionEnvironment([$key => '   '])))
        ->toContain("{$key} is required in production and is missing or empty.");
}
```

Seven tests, twenty-six assertions, and they run in a hundredth of a second because
none of them needs a server.

## Catch a secret before it becomes public

Configuration validation stops a bad deployment. It does nothing about a secret that
has already been written somewhere readable. `scripts/scan-for-secrets.php` checks the
three places that actually happen in a project this size.

**A committed environment file.** Anything Git tracks is permanent, and deleting it
later does not remove it from history:

```php
exec('git -C ' . escapeshellarg($root) . ' ls-files', $tracked);
foreach ($tracked as $path) {
    // `.dalt/` is the learning platform, not our application — `platform:remove`
    // deletes it and it is never part of a deployment — so its fixtures are out of
    // scope here.
    if (str_starts_with($path, '.dalt/')) {
        continue;
    }

    $name = basename($path);
    if ($name === '.env' || preg_match('/\A\.env\.(?!example$)(?!.*\.example$).+/', $name) === 1) {
        $problems[] = "{$path} is tracked by Git; environment files with real values must not be.";
    }
}
```

**A secret in the browser bundle.** Everything Vite emits is downloaded by every
visitor. A key there is not at risk of leaking; it has leaked:

```php
$secretShapes = [
    '/DB_PASSWORD/i',
    '/BEGIN [A-Z ]*PRIVATE KEY/',
    // A long random-looking assignment, which is what a leaked key looks like.
    '/(secret|token|password|api[_-]?key)\s*[:=]\s*[\'"][A-Za-z0-9\/+_-]{24,}[\'"]/i',
];
```

**A password in our own checked-in template**, which is the mistake this lesson is most
likely to cause:

```php
if (is_file($template) && preg_match('/^DB_PASSWORD=.+$/m', (string) file_get_contents($template)) === 1) {
    $problems[] = '.env.production.example sets DB_PASSWORD; secrets belong to the deployment, not the repository.';
}
```

## Prove every rule fires

A scanner that has never found anything is indistinguishable from a scanner that cannot
find anything. Create each exposure deliberately, one at a time:

```bash
printf 'DB_PASSWORD=real-production-secret\n' > .env.staging && git add -f .env.staging
composer check:secrets
```

```text
Possible secret exposure:
- .env.staging is tracked by Git; environment files with real values must not be.
```

```bash
echo 'const apiKey = "sk_live_9f2b7c41d0e8a5364bd1927fe0aa";' >> public/build/assets/main-*.js
composer check:secrets
```

```text
Possible secret exposure:
- public/build/assets/main-B0_nQ7gI.js contains something secret-shaped; a browser bundle is public.
```

```bash
printf 'DB_PASSWORD=oops\n' >> .env.production.example
composer check:secrets
```

```text
Possible secret exposure:
- .env.production.example sets DB_PASSWORD; secrets belong to the deployment, not the repository.
```

Undo all three — `git rm --cached .env.staging`, rebuild the bundle, restore the
template — and the scan is quiet again. Be honest about what this is: a cheap check
that catches accidents, not a guarantee. It is worth having precisely because accidents
are how secrets actually leak.

## Run the gate

```bash
composer check:config
composer check:secrets
php vendor/bin/pest tests/Unit/ConfigurationTest.php
npm run typecheck && npm run lint && npm test
npm run test:browser
npm run build
```

The configuration and secret checks pass, 51 focused PHP tests pass with 185
assertions, all 42 component tests pass, the seven browser journeys pass, and Vite
produces the production bundle.

Our application now states what it needs, refuses to run without it, keeps its failure
reason out of the response, and can tell us when a secret has reached somewhere public.
The next lesson builds the frontend once, for production, and teaches DALT to serve the
hashed result.
