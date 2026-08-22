# Make empty and denied states intentional

Our product now has many places where there may be nothing to show—or where a user
may not be allowed to act. We will audit those states, repair the accidental blanks,
give route failures a safe destination, and make API errors consistently machine
readable.

## Name the states before changing components

Walk through every collection and protected location in the running application. We
need to distinguish:

- **loading:** the server has not answered yet;
- **first-use empty:** the collection is valid but nobody has created an item;
- **filtered empty:** items exist, but none match this URL;
- **recoverable failure:** the request may work when explicitly retried;
- **missing:** the resource or route does not exist, including a concealed foreign
  resource;
- **denied:** the resource is known, but the current role lacks one capability.

These are different product facts. Reusing one blank box for all six makes the
interface quiet precisely when the user needs an explanation.

Our workspace, project, issue, member, comment, activity, and dashboard screens
already cover most of the matrix. The audit finds two accidental blanks: an empty
Labels screen and an empty Invitations collection.

## Separate label loading from label emptiness

In `resources/app/LabelsPage.tsx`, change the initial value from an empty array to a
nullable collection:

```tsx
const [labels, setLabels] = useState<WorkspaceLabel[] | null>(null)
```

`null` means the request has not completed. `[]` means it completed and found no
labels. Render those states separately:

```tsx
{labels === null ? (
  <p className="mt-8 text-sm text-muted" role="status">
    Loading labels…
  </p>
) : labels.length === 0 ? (
  <p className="mt-8 rounded-xl border border-dashed border-line-strong p-6 text-center text-sm text-muted">
    No labels yet. Create one when the team needs shared vocabulary.
  </p>
) : (
  <ul aria-label="Labels">
    {/* Existing label rows. */}
  </ul>
)}
```

When creating a label, treat a still-null collection as empty only after the server
has confirmed the new label:

```ts
setLabels((current) =>
  [...(current ?? []), label].sort((a, b) => a.name.localeCompare(b.name)),
)
```

## Explain an empty invitation history

`InvitationPanel` already uses `null` while loading. Make that state visible, then
explain a successful empty result:

```tsx
{invitations === null && (
  <p className="mt-8 text-sm text-muted" role="status">
    Loading invitations…
  </p>
)}

{invitations !== null && invitations.length === 0 && (
  <p className="mt-8 rounded-xl border border-dashed border-line-strong p-6 text-center text-sm text-muted">
    No invitations yet. Create a link when someone is ready to join.
  </p>
)}
```

This is not an error and does not need a red warning. The next action is already on
the same screen.

## Return one envelope for API exceptions

Our `ApiAuth` middleware returns JSON, but an `abort(403)` or `abort(404)` reaches the
framework exception handler and previously became HTML. A React client could not
safely interpret the response in one consistent way.

In `framework/Core/ExceptionHandler.php`, detect requests whose path begins with
`/api/` using the request already bound in the container:

```php
private function isApiRequest(): bool
{
    $container = App::containerOrNull();
    if ($container === null || !$container->resolved(Request::class)) {
        return false;
    }

    $request = $container->resolve(Request::class);

    return $request instanceof Request
        && str_starts_with($request->path(), '/api/');
}
```

At the start of `render`, keep detailed server failures out of every production API
response—even when application debug output is enabled:

```php
$status = $exception instanceof HttpException
    ? $exception->statusCode
    : 500;
$message = $status >= 500
    ? 'Internal Server Error'
    : $exception->getMessage();

if ($this->isApiRequest()) {
    return Response::json([
        'error' => [
            'status' => $status,
            'code' => match ($status) {
                401 => 'unauthenticated',
                403 => 'forbidden',
                404 => 'not_found',
                410 => 'gone',
                default => 'request_failed',
            },
            'message' => $message,
        ],
    ], $status);
}
```

Browser page errors remain HTML. Validation responses keep their useful field-level
`errors` object. This envelope covers request-level exceptions.

Update `app/Http/Middleware/ApiAuth.php` to return the same three fields:

```php
'error' => [
    'status' => 401,
    'code' => 'unauthenticated',
    'message' => 'Log in to continue.',
],
```

Now 401, 403, 404, 410, and 500 responses share a shape, while the status code still
drives browser behavior.

## Create one route problem screen

Create `resources/app/RouteProblemPage.tsx`. It can receive an explicit status for a
known permission route or inspect a React Router error boundary:

```tsx
export function RouteProblemPage({
  status: givenStatus,
  backTo = '/',
}: {
  status?: number
  backTo?: string
}) {
  const routeError = useRouteError()
  const status = givenStatus
    ?? (isRouteErrorResponse(routeError) ? routeError.status : 500)
  // ...
}
```

Give 403, 404, and unexpected failures distinct headings and explanations. A 403
should say the user's workspace role does not include this action. A 404 should avoid
revealing whether a concealed foreign resource exists. Every branch gets an explicit
safe link to `/` or the current authorized workspace.

Move keyboard focus to the new heading when the route changes:

```tsx
const heading = useRef<HTMLHeadingElement>(null)

useEffect(() => {
  heading.current?.focus()
}, [])

<h1 ref={heading} tabIndex={-1}>
  {status === 403
    ? 'You cannot manage this area'
    : status === 404
      ? 'This page was not found'
      : 'This page could not be shown'}
</h1>
```

`tabIndex={-1}` permits programmatic focus without adding the heading to the normal
Tab sequence. A screen reader receives the new context immediately, while the safe
link remains the next ordinary interactive control.

Use a single-column responsive container, a fluid heading size, readable line length,
and a button-sized return link. The state works at mobile width without horizontal
controls competing for space.

## Register durable and in-app error routes

Add the explicit server route:

```php
$router->get(
    '/workspaces/{workspace}/permission-denied',
    'workspaces/show.php',
)->only('auth');
```

Then add the matching React child in the workspace router:

```tsx
{
  path: 'permission-denied',
  element: (
    <RouteProblemPage
      status={403}
      backTo={`/workspaces/${data.workspace.id}`}
    />
  ),
},
```

Add `errorElement={<RouteProblemPage />}` to each root router and a final `*` child
for client-side unknown paths. Workspace and project catch-alls point back to the
known workspace rather than guessing at browser history.

## Route a real stale-permission failure

Controls are hidden from members, but React's bootstrapped role can become stale if
another owner changes it in another tab. The server must still be the final boundary.

In `resources/app/members-data.ts`, preserve the response status when a mutation
fails:

```ts
export class MemberMutationError extends Error {
  constructor(readonly status: number, message: string) {
    super(message)
  }
}

throw new MemberMutationError(
  response.status,
  typeof message === 'string' ? message : 'The member change failed.',
)
```

In `MembersPage`, navigate a confirmed 403 to the permission route:

```ts
catch (error) {
  if (error instanceof MemberMutationError && error.status === 403) {
    await navigate(`/workspaces/${data.workspace.id}/permission-denied`)
  } else {
    setMutationNotice(
      error instanceof Error ? error.message : 'The role could not be changed.',
    )
  }
}
```

Do this for role changes and removal. Validation or connection failures remain on the
form because they are recoverable there.

## Keep hidden controls backed by server denials

The UI continues to hide workspace edit/delete, invitation, and member management
controls from ordinary members. That is useful interface guidance, not authorization.
Our reciprocal backend tests still send the forbidden requests directly and prove:

- members receive 403 for known owner-only capabilities;
- outsiders receive 404, concealing foreign workspace existence;
- denied mutations leave database rows unchanged;
- last-owner protection cannot be bypassed.

The new frontend test simulates a stale owner screen, returns a server 403, verifies
navigation to the permission page, verifies heading focus, and verifies the safe
workspace link. Another test proves the client-side 404 has focused context and a
safe workspaces link.

Add one exception-handler test that binds an `/api/...` request and proves both a 403
and an unexpected 500 return JSON. The 500 assertion includes the important negative:
the secret diagnostic message is absent.

## Run the complete audit gate

```bash
npm run typecheck
npm run lint
npm test
php vendor/bin/pest \
  tests/Unit/ExceptionHandlerTest.php \
  tests/Feature/AuthenticationTest.php \
  tests/Feature/IssueApiTest.php \
  tests/Feature/WorkspaceAuthorizationTest.php
npm run build
```

All 42 frontend tests pass. The focused PHP gate passes 48 tests with 297 assertions,
and Vite produces the production bundle. Through real HTTP, an authenticated
`/dashboard` returns the React shell and a new account receives an intentionally empty
dashboard; a guest receives a page redirect and this API response:

```json
{
  "error": {
    "status": 401,
    "code": "unauthenticated",
    "message": "Log in to continue."
  }
}
```

Batch 9 now ends with deliberate server-state, dashboard, empty, denied, missing,
loading, and failure behavior. The next batch can deepen integration and browser
coverage without first guessing what these states are supposed to mean.
