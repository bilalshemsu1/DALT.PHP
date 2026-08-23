# Add useful structured application logs

Our application already writes to `storage/logs/app.log`, and what it writes is prose:
readable if you are looking at one entry, useless if you want to ask how many members
were refused last night. We will make every record one JSON object on one line, tie all
the records from a request together with an id the caller can quote, and make it
impossible for a credential to appear in any of them.

> **Helpful background:** OWASP's [logging cheat sheet](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html)
> covers what belongs in a log and, more importantly, what does not.

## Decide what a record contains

Before writing code, decide the fields. A log is only useful if every record has the
same ones:

```text
time        an ISO timestamp a machine can sort
level       info | warning | error
event       a stable name, not a sentence
requestId   ties every record from one request together
durationMs  how long we had been working when this was written
```

Plus whatever the event itself is about — `method`, `path`, `status`, `actorId`,
`capability`.

And the list that matters more, in `app/Support/RequestLog.php`:

```php
/**
 * Keys whose values never appear in a log, at any level, for any reason.
 *
 * A log is copied into tickets, pasted into chat, and shipped to a third-party
 * aggregator. Anything here would be a credential in all three places.
 */
private const NEVER_LOGGED = [
    'password',
    'password_confirmation',
    '_token',
    'token',
    'invitation_token',
    'db_password',
    'authorization',
    'cookie',
];
```

Session identifiers and invitation tokens are on that list for the same reason as
passwords: possessing one *is* being the user.

## Write one line per event

```php
private static function write(string $level, string $event, array $context): void
{
    $record = [
        'time' => gmdate('c'),
        'level' => $level,
        'event' => $event,
        'requestId' => self::requestId(),
        ...self::redact($context),
    ];

    $duration = self::elapsedMs();
    if ($duration !== null) {
        $record['durationMs'] = $duration;
    }

    app_log(json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}
```

The redaction is recursive, because context arrives nested:

```php
foreach ($context as $key => $value) {
    if (in_array(strtolower((string) $key), self::NEVER_LOGGED, true)) {
        $safe[$key] = '[redacted]';

        continue;
    }

    $safe[$key] = is_array($value) ? self::redact($value) : $value;
}
```

Redacted, not dropped. `"password": "[redacted]"` tells an operator the field was
present, which is often the thing they need to know.

## Give the caller the request id

In `public/index.php`, start the request and hand its id back:

```php
// One id for everything this request produces, so a log line, a response header, and
// a user's screenshot can all be joined together.
$requestId = RequestLog::begin();
```

```php
// The id goes back to the caller. A bug report that quotes it turns "it broke earlier"
// into one grep. `withHeader` returns a new Response rather than mutating this one,
// so the result has to be reassigned.
$response = $response->withHeader('X-Request-Id', $requestId);
```

That comment records a mistake worth avoiding. `Response::withHeader()` is immutable —
it returns a new object — so the first version called it without reassigning and the
header silently never appeared.

Check it:

```bash
curl -D- -o /dev/null http://127.0.0.1:8091/health
tail -1 storage/logs/app.log
```

```text
X-Request-Id: e23bd2189bc2a2df
{"time":"2026-08-23T13:37:55+00:00","level":"info","event":"request.handled",
 "requestId":"e23bd2189bc2a2df","method":"GET","path":"/health","status":200,
 "actorId":null,"durationMs":4.2}
```

The same id in both places. That is the whole point.

## Never let logging break a request

Our first version read the request directly:

```php
'path' => parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH),
```

and the existing `RequestLifecycleTest` failed immediately. It sends a deliberately
hostile `REQUEST_URI` — an array rather than a string — and expects a clean 500 page.
Instead the process exited 255, because `parse_url()` threw inside the code that runs
*after* the exception handler.

```php
// $_SERVER is attacker-influenced and not guaranteed to hold strings. Logging must
// never be the thing that fails a request, so the values it reads are coerced here.
$logMethod = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : null;
$logPath = is_string($_SERVER['REQUEST_URI'] ?? null)
    ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
    : null;
```

The rule generalises: observability code runs on every request including the broken
ones, so it has to be the most defensive code in the application.

## Log a refusal as a refusal

`WorkspaceAccess::findOrFail` now records the case where we know who the caller is and
they lack a capability:

```php
// A member reaching for an owner capability is worth knowing about; it is
// not an emergency, so it is a warning rather than an error.
RequestLog::warning('authorization.denied', [
    'actorId' => $userId,
    'workspaceId' => (int) $workspace['id'],
    'role' => $workspace['role'],
    'capability' => $capability,
]);
```

Note what is *not* logged: the outsider case. An outsider gets a 404 because the
membership join found nothing, and there is no workspace to name. Logging "user 9 was
refused workspace 3" for a workspace they cannot see would put the existence of that
workspace in a file, which is the leak the 404 exists to prevent.

Levels have to mean something, or nobody can filter by them. Our first version logged
every `abort()` as an error, which filled the error level with ordinary 403s:

```php
// A 401, 403, 404 or 422 is the application refusing on purpose. It is already
// recorded by the request.handled line below, and logging it as an error would
// fill the error level with ordinary behaviour.
$expectedRefusal = $exception instanceof \Core\HttpException
    && $exception->statusCode < 500;
```

## Look at the four cases

**A member refused an owner action:**

```text
info     request.handled      /dashboard           status=200  actor=4
warning  authorization.denied                      actor=4  manage_workspace
info     request.handled      /api/workspaces/3    status=403  actor=4
```

Two info lines and one warning, all sharing a request id, and the 403 is visible
without being an error.

**A validation failure and an unauthenticated call** — ordinary, at info:

```text
info  request.handled  POST /api/session    status=422
info  request.handled  GET  /api/dashboard  status=401
```

**An unexpected failure** — stop the database and ask for data:

```text
info   request.handled  /ready           status=503
error  request.failed   /api/dashboard   RuntimeException  Database.php:40
info   request.handled  /api/dashboard   status=500
```

The exception is recorded by class and location, never by message:

```php
// The class and location are useful and safe. The message can contain a
// connection string, so it is recorded only in the trace-free summary a
// developer reads from the server, never in the response.
'exception' => $exception::class,
'file' => basename($exception->getFile()) . ':' . $exception->getLine(),
```

Grep the whole log for anything that names the deployment:

```bash
grep -ciE 'dalt_issue_tracker|55432|SQLSTATE' storage/logs/app.log
```

```text
0
```

## Test what must never happen

`tests/Feature/RequestLogTest.php` points `APP_LOG_PATH` at a temporary file and reads
back what was written. The important test asserts against the **raw file**, not the
decoded record:

```php
$raw = (string) file_get_contents($this->logPath);

foreach ([
    'correct-horse-battery',
    'a-real-csrf-token',
    'e3b0c44298fc1c14',
    'a-real-deployment-secret',
] as $secret) {
    expect(str_contains($raw, $secret))->toBeFalse("The log leaked '{$secret}'.");
}
```

Checking the decoded array would miss a secret that leaked into a different field. The
file is what gets copied into a ticket, so the file is what the test reads.

Then the other half — the record still has to be *useful*:

```php
expect($record['password'])->toBe('[redacted]')
    ->and($record['input']['nested']['db_password'])->toBe('[redacted]')
    // Values that are not credentials survive, or the record teaches nothing.
    ->and($record['input']['email'])->toBe('ada@example.test');
```

A redactor that erased everything would pass the first test and fail the job.

## Reading the log

Because every line is JSON, ordinary tools work:

```bash
# every refusal, with who and what
grep '"event":"authorization.denied"' storage/logs/app.log

# the slowest requests
grep '"event":"request.handled"' storage/logs/app.log \
  | python3 -c 'import json,sys
for l in sys.stdin:
    d = json.loads(l.split("] ",1)[1])
    print(d.get("durationMs"), d.get("method"), d.get("path"))'
```

That is what "structured" buys. No parser to maintain, no format to agree on.

## Run the gate

```bash
./scripts/ci-gate.sh
```

```text
Tests:  1 skipped, 341 passed (1041 assertions)
All release checks passed.
```

Our application can now be asked what it did, by whom, how long it took, and what was
refused — without any of those answers containing something that should not leave the
server. Next we make failures land somewhere useful for the person using the
application, not just for us.
