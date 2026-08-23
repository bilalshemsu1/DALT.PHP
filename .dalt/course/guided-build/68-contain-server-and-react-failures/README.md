# Contain server and React failures

Failures currently land in two unhelpful places. On the server, two different pieces of
code build the JSON error body, so a client has two shapes to handle. In the browser, a
component that throws while rendering takes the whole application down and leaves a
blank page. We will give every failure one envelope, one id a person can quote, and a
containment wall around each independently useful section.

> **Helpful background:** React's [error boundary reference](https://react.dev/reference/react/Component#catching-rendering-errors-with-an-error-boundary)
> explains exactly which errors a boundary can and cannot catch.

## One envelope, built in one place

Look at the two ways our application currently refuses a JSON request. The exception
handler builds this:

```php
return Response::json([
    'error' => [
        'status' => $status,
        'code' => match ($status) { 401 => 'unauthenticated', /* … */ },
        'message' => $message,
    ],
], $status);
```

and `app/Http/Middleware/ApiAuth.php` builds its own copy by hand. Two copies of a
contract drift; ours already had, because only one of them was about to gain a field.

Create `app/Support/ApiError.php`:

```php
/**
 * One shape for every JSON failure this application produces.
 *
 * The exception handler builds this envelope for anything that throws; middleware and
 * controllers that refuse a request without throwing must produce the identical shape,
 * or a client has two formats to handle and will handle one of them badly.
 */
final class ApiError
{
    public static function json(int $status, string $code, string $message): Response
    {
        return Response::json([
            'error' => [
                'status' => $status,
                'code' => $code,
                'message' => $message,
                // The id of the request that failed. It is already in our log, so a
                // user who quotes it turns a vague report into one grep. It identifies
                // a request, never a person or a session.
                'requestId' => RequestLog::requestId(),
            ],
        ], $status);
    }

    public static function unauthenticated(): Response
    {
        return self::json(401, 'unauthenticated', 'Log in to continue.');
    }
}
```

The middleware becomes one line:

```php
// One envelope, built in one place, so a client never has two shapes.
return ApiError::unauthenticated();
```

The exception handler takes the id as a constructor argument, because it is framework
code and must not reach into our application:

```php
public function __construct(
    private bool $debug,
    private string $requestId = '',
) {
}
```

Both paths now answer identically:

```json
{"error":{"status":401,"code":"unauthenticated","message":"Log in to continue.","requestId":"7192b61261616ca6"}}
{"error":{"status":404,"code":"not_found","message":"Not Found","requestId":"efd0529943dda3f3"}}
```

The first came from middleware, the second from a thrown `abort(404)`.

## Two tests you will have to update

Adding a field to a contract breaks whoever asserted the whole contract. Two existing
tests failed immediately, and both were right to:

```text
FAILED  Tests\Feature\AuthenticationTest > private APIs return a JSON 401…
+        'requestId' => 'c1f8937188503a77',
```

That is the correct reaction to a changed shape. Fix them deliberately rather than
loosening the assertion — the id is generated per request, so assert its shape:

```php
->and($decoded['error'])->toMatchArray([
    'status' => 401,
    'code' => 'unauthenticated',
    'message' => 'Log in to continue.',
])
->and($decoded['error']['requestId'])->toMatch('/\A[0-9a-f]{16}\z/')
```

The unit test can be exact, because it constructs the handler itself:

```php
$handler = new ExceptionHandler(debug: true, requestId: 'fixed-request-id');
```

## What the envelope must never say

`tests/Feature/ErrorEnvelopeTest.php` covers the properties, not the wording:

```php
test('an unexpected failure says nothing beyond its status in production', function () {
    $response = (new ExceptionHandler(false, $this->requestId))->render(
        new RuntimeException('SQLSTATE[08006] connection to server at "db.internal", port 5432 failed'),
    );

    expect($decoded['error']['message'])->toBe('Internal Server Error')
        // The id is how the report gets connected to the log entry that does have detail.
        ->and($decoded['error']['requestId'])->toBe('fixed-request-id');

    foreach (['db.internal', '5432', 'SQLSTATE'] as $secret) {
        expect(str_contains($body, $secret))->toBeFalse("The error envelope leaked '{$secret}'.");
    }
});
```

And one that is easy to get wrong — `APP_DEBUG` is a development affordance, and an API
client is never the developer:

```php
test('debug output is a development affordance and never reaches an API response', function () {
    // Even with debug on, the JSON path stays machine-readable and quiet.
    $response = (new ExceptionHandler(true, $this->requestId))->render(
        new RuntimeException('SQLSTATE[08006] connection to "db.internal" failed'),
    );

    expect(str_contains($response->content(), 'db.internal'))->toBeFalse();
});
```

One practical note while writing these: the handler decides JSON or HTML by looking at
the bound `Request`, so a test has to bind one exactly as the front controller does.
Without it, every assertion sees `<h1>403</h1>` and fails on a JSON parse error.

## Contain a render failure

React Router's `errorElement` already covers a route that fails to load, and we use it.
It does not cover the other case: a component that renders correctly until the day some
data makes it throw. Today that unmounts the entire application.

`resources/app/ErrorBoundary.tsx`:

```tsx
export class ErrorBoundary extends Component<Props, State> {
  state: State = { failed: false, attempt: 0 }

  static getDerivedStateFromError(): Partial<State> {
    return { failed: true }
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    // The component stack is the only part that says which subtree failed. In a real
    // deployment this is where a report would be sent; the message itself never
    // reaches the person looking at the screen.
    console.error(`[${this.props.section}]`, error, info.componentStack)
  }
```

This is one of the few places a class component is still required — `getDerivedStateFromError`
has no hook equivalent.

The recovery is the interesting part:

```tsx
if (!this.state.failed) {
  return <div key={this.state.attempt}>{this.props.children}</div>
}
```

```tsx
onClick={() => this.setState((current) => ({ failed: false, attempt: current.attempt + 1 }))}
```

Clearing `failed` alone would re-render the same component instances with the same
state. Changing `attempt`, used as a `key`, remounts the subtree — which is what "try
again" has to mean for a component that threw partway through rendering.

## Put boundaries where recovery is meaningful

One boundary at the root turns every failure into the same blank apology. Ours go
around the sections of the issue page that are independently useful:

```tsx
{/* Each panel is independently useful, so one failing must not remove the
    issue itself from the screen. */}
<ErrorBoundary section="Comments">
  <CommentsPanel data={data} issueId={state.issue.id} />
</ErrorBoundary>
<ErrorBoundary section="Activity">
  <ActivityPanel workspaceId={data.workspace.id} projectId={data.project.id} issueId={state.issue.id} />
</ErrorBoundary>
```

If the comment payload is malformed, the issue title, description, status, and activity
timeline are all still there and still usable.

Note what stays as it was: expected request failures — a 403, a network error, a
validation response — are still handled by component state, because they are not
exceptions. A boundary is for the unexpected.

## Test the containment

`resources/app/error-containment.test.tsx` covers five behaviours. The first is the
promise the boundary makes:

```tsx
expect(screen.getByRole('alert')).toHaveTextContent('Comments could not be shown')
expect(screen.getByRole('heading', { name: 'Trace a request' })).toBeInTheDocument()
expect(screen.getByText('Activity still works')).toBeInTheDocument()
```

The second is what it must not do:

```tsx
expect(document.body.textContent).not.toContain('the comment payload was malformed')
```

The third and fourth are a pair, and the fourth is the one people forget:

```tsx
it('stays failed when the cause is still there, rather than pretending', async () => {
  // Resetting a boundary is not a repair: if the child still throws, it must fail again.
  await user.click(screen.getByRole('button', { name: 'Try again' }))

  expect(screen.getByRole('alert')).toHaveTextContent('Comments could not be shown')
})
```

A "try again" that clears the message without re-rendering would pass the recovery test
and lie to every user.

The fifth records a limit rather than a feature:

```tsx
it('does not catch an error thrown from an event handler', () => {
  // React reports it to the global handler instead; the boundary never sees it.
  window.addEventListener('error', onError)
  screen.getByRole('button', { name: 'Save' }).click()

  expect(reported).toEqual(['handler failed'])
  expect(screen.queryByRole('alert')).not.toBeInTheDocument()
})
```

Errors from event handlers, timers, and rejected promises never pass through rendering,
so no boundary can catch them. Knowing that is what stops someone wrapping a component
in a boundary and assuming a failing save is now handled.

## Run the gate

```bash
./scripts/ci-gate.sh
```

```text
Tests:  1 skipped, 345 passed (1069 assertions)
Tests   47 passed (47)
All release checks passed.
```

A failure in our application now produces one envelope, carries an id that connects a
user's report to a log line, says nothing about the deployment, and — in the browser —
removes one section instead of the page. Next we audit whether the application can
actually be used by everyone.
