# Distinguish liveness from readiness

Our container reports itself healthy, and Compose waits for the database before
starting the application. Neither of those facts means the application can actually do
its job. We will add two endpoints that answer two genuinely different questions, and
wire the right one into the container's health check.

> **Helpful background:** Docker's [startup order guide](https://docs.docker.com/compose/how-tos/startup-order/)
> explains why "the database container started" is not the same as "PostgreSQL accepts
> connections".

## Two questions, two answers

They look similar and they lead to opposite actions:

```text
liveness    Is this process able to answer at all?
            No  → restart the container. Nothing else will help.

readiness   Can this process do its job right now?
            No  → stop sending it traffic. Restarting will not fix it.
```

Collapsing them into one endpoint breaks in a specific and painful way. If liveness
checks the database, then a database outage makes every application container fail its
liveness probe, and the orchestrator restarts all of them — turning one incident into a
restart storm that delays recovery.

## Liveness touches nothing

`app/Http/controllers/health/live.php`:

```php
/**
 * Liveness: is this process able to answer at all?
 *
 * Deliberately touches nothing. If this fails, the container is wedged and restarting
 * it is the right response. If it checked the database, a database outage would
 * restart every healthy application container — which turns one incident into two.
 */

return Response::json(['status' => 'alive']);
```

It really is that small. Anything it checks is something that can make a healthy
process look dead.

## Readiness asks the question that matters

`app/Http/controllers/health/ready.php`:

```php
try {
    App::resolve(Database::class)->query('SELECT 1')->findOrFail();
} catch (Throwable) {
    // The reason is deliberately absent. A driver message names the host, the port,
    // the database, and sometimes the user — and this endpoint is unauthenticated.
    return Response::json(['status' => 'not-ready'], 503);
}

return Response::json(['status' => 'ready']);
```

`SELECT 1` is the whole check: it proves a connection can be made *and* a statement
executed against the configured database. Checking that the container can open a TCP
socket to `db:5432` would prove much less — PostgreSQL accepts connections before it
finishes recovery, and a wrong password looks identical to a right one at the socket
level.

The empty error body is the part worth pausing on. Our first instinct is to return the
driver message so operators can debug faster. That message reads:

```text
SQLSTATE[08006] connection to server at "db.internal" (10.0.0.5), port 5432 failed
```

Host, address, and port, from an endpoint with no authentication. The reason belongs in
the log, where the person deploying can read it; the response gets a status code.

Register both without any middleware:

```php
// Health endpoints are unauthenticated on purpose: an orchestrator has no session,
// and neither reveals anything a stranger could use.
$router->get('/health', 'health/live.php');
$router->get('/ready', 'health/ready.php');
```

## Watch them disagree

With everything running:

```bash
curl -w ' [%{http_code}]\n' http://127.0.0.1:8091/ready
curl -w ' [%{http_code}]\n' http://127.0.0.1:8091/health
```

```text
{"status":"ready"} [200]
{"status":"alive"} [200]
```

Now stop the database and ask again:

```bash
docker stop daltphp-db-1
```

```text
{"status":"not-ready"} [503]
{"status":"alive"} [200]
```

That is the entire lesson in four lines. The process is fine. The application is not.
Restarting it would accomplish nothing except losing the sessions it is holding.

Start the database and ask once more — no restart, no intervention:

```text
ready again after 1s
```

## Give the container the right probe

In `compose.production.yaml`, the application service gets a health check:

```yaml
    healthcheck:
      # The php:*-cli image has no curl or wget, and PHP is guaranteed to be here.
      # /ready, not /health: an application that cannot reach its database should not
      # be receiving traffic, even though its process is perfectly alive.
      test: ["CMD-SHELL", "php -r \"exit(@file_get_contents('http://127.0.0.1:8000/ready') === false ? 1 : 0);\""]
      interval: 2s
      timeout: 3s
      start_period: 20s
      retries: 5
```

`start_period` is what makes `retries: 5` safe. Failures during startup do not count
toward the retry budget, so a slow first boot is tolerated while a real outage is still
reported in about ten seconds instead of a minute.

The database keeps its own check, because only PostgreSQL can answer for PostgreSQL:

```yaml
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U $${POSTGRES_USER} -d $${POSTGRES_DB}"]
```

And `depends_on` waits for that answer rather than for a container to exist:

```yaml
    depends_on:
      db:
        condition: service_healthy
```

Plain `depends_on` only orders *starting*. The application would come up while
PostgreSQL was still recovering, fail its first queries, and look broken.

## A rebuild is not optional

Our first attempt at this looked like a health-check bug:

```text
container daltprod-app-1 is unhealthy
```

```bash
curl http://127.0.0.1:8200/ready
```

```text
<h1>404</h1><p>Not Found</p>
```

The routes existed on the host and not in the image. A container runs the code that was
baked into it, so adding a route changes nothing until the image is rebuilt:

```bash
DB_PASSWORD=… docker compose --env-file .env.production.example \
  -f compose.production.yaml -p daltprod up -d --build --wait
```

```text
Container daltprod-db-1   Healthy
Container daltprod-app-1  Healthy
```

Keep that in mind for the rest of this batch. "I already fixed that" is usually a
container that was never rebuilt.

## Prove all four states in the real stack

**Healthy:**

```text
{"status":"ready"} [200]
{"status":"alive"} [200]
app Up 24 seconds (healthy)
db Up 8 minutes (healthy)
```

**Database stopped** — the endpoints disagree, and Compose notices:

```bash
docker compose … stop db
```

```text
{"status":"not-ready"} [503]
{"status":"alive"} [200]
after 8s: app Up 35 seconds (unhealthy)
```

**Recovering** — nothing is restarted, and the container marks itself healthy again:

```bash
docker compose … start db
```

```text
after 2s: app Up 53 seconds (healthy)
{"status":"ready"} [200]
```

## Test the behaviour, not the deployment

`tests/Feature/HealthTest.php` covers the same four states without Docker. The liveness
test makes its point structurally:

```php
test('liveness answers without touching anything', function () {
    // Deliberately no database in the container. If liveness needed one, this would
    // fail — and in production a database outage would restart healthy containers.
    $container = new Container();
    $container->instance(Platform::class, Platform::discover(base_path()));
    App::setContainer($container);

    expect(healthRequest('/health')->status())->toBe(200);
});
```

There is no `Database` binding at all. If someone later adds a query to the liveness
endpoint, this test fails immediately and says why.

The failure test uses a stand-in that throws a realistic driver message, then insists
none of it reaches the caller:

```php
foreach (['db.internal', '10.0.0.5', '5432', 'SQLSTATE'] as $secret) {
    expect(str_contains($body, $secret))->toBeFalse(
        "The readiness response leaked '{$secret}' to an unauthenticated caller.",
    );
}
```

And recovery is asserted without any restart:

```php
healthBoot($flaky);
expect(healthRequest('/ready')->status())->toBe(503);

// Nothing restarts the process; the next probe simply succeeds.
$failing = false;
expect(healthRequest('/ready')->status())->toBe(200);
```

One more thing this file proves by omission: `beforeEach` clears `$_SESSION` and no test
ever sets one. An orchestrator has no cookie, so a health endpoint behind
authentication would report every container unhealthy.

## Run the gate

```bash
php vendor/bin/pest tests/Feature/HealthTest.php tests/Unit/ConfigurationTest.php \
  tests/Feature/PolicyMatrixTest.php tests/Feature/DataIntegrityTest.php
npm run test:browser
composer check:config && composer check:secrets
php scripts/verify-build.php
```

Forty-four PHP tests pass with 154 assertions, the seven browser journeys pass, and the
configuration, secret, and build checks are clean.

Our container can now tell an orchestrator the difference between "restart me" and
"give me a moment". Next we make the database schema part of deploying, instead of
something someone remembers to run.
