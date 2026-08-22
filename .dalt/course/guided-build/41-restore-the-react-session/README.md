# Restore the React session

Our server already owns authentication, but React currently learns who is signed in
from JSON repeated inside every HTML view. We will give the browser one current-session
endpoint, load it through a focused provider, and let every React route share the same
truth after a refresh.

## Give the browser one session contract

Add this route beside the existing login and logout routes in `routes/routes.php`:

```php
$router->post('/api/session', 'api/auth/login.php')
    ->only(['guest', 'csrf']);
$router->get('/api/session', 'api/auth/show.php');
$router->delete('/api/session', 'api/auth/logout.php')
    ->only([ApiAuth::class, 'csrf']);
```

The same resource now has three useful HTTP operations. `POST` creates a session by
logging in, `GET` describes the current session, and `DELETE` destroys it. The `GET`
route is deliberately public: a guest response is a valid state React needs to
understand, not an exceptional response.

Create `app/Http/controllers/api/auth/show.php`:

```php
<?php

declare(strict_types=1);

use Core\Authenticator;
use Core\Response;

return Response::json([
    'user' => (new Authenticator())->user(),
    'csrfToken' => csrf_token(),
]);
```

The cookie still carries the session identifier. We do not copy a user into local
storage, expose the cookie to JavaScript, or accept a user ID from the browser. DALT
reads its server-side session and returns only the public identity React needs.

An authenticated response has this shape:

```json
{
  "user": { "id": 1, "email": "ada@example.com" },
  "csrfToken": "the-current-session-token"
}
```

A signed-out browser receives the same contract with `"user": null`. That stable
shape keeps ordinary guest state separate from a failed network request.

## Model the states React can really encounter

Create `resources/app/session.tsx`. Start with the data and state types:

```tsx
export type SessionUser = {
  id: number
  email: string
}

type Session = {
  user: SessionUser | null
  csrfToken: string
}

type AuthenticatedSession = Session & { user: SessionUser }
type GuestSession = Session & { user: null }

type SessionState =
  | { status: 'loading' }
  | { status: 'authenticated'; session: AuthenticatedSession }
  | { status: 'guest'; session: GuestSession }
  | { status: 'failed'; retry: () => void }
```

This union prevents an important class of UI guesswork. Code inside the
`authenticated` branch knows a user exists. Guest and failure are different: retrying
a healthy guest response would never sign the person in.

As with our other API boundaries, treat response JSON as `unknown` first:

```tsx
function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object'
    && value !== null
    && !Array.isArray(value)
}

function parseSession(value: unknown): Session {
  if (!isRecord(value) || typeof value.csrfToken !== 'string') {
    throw new Error('The session response is malformed.')
  }

  if (value.user === null) {
    return { user: null, csrfToken: value.csrfToken }
  }

  if (
    !isRecord(value.user)
    || !Number.isInteger(value.user.id)
    || Number(value.user.id) < 1
    || typeof value.user.email !== 'string'
  ) {
    throw new Error('The session user is malformed.')
  }

  return {
    user: {
      id: Number(value.user.id),
      email: value.user.email,
    },
    csrfToken: value.csrfToken,
  }
}
```

Now add the request:

```tsx
export async function loadSession(): Promise<Session> {
  const response = await fetch('/api/session', {
    headers: { Accept: 'application/json' },
  })

  if (!response.ok) {
    throw new Error('The session could not be loaded.')
  }

  return parseSession(await response.json())
}
```

The runtime checks matter even though both ends belong to us. An expired deployment,
an HTML error page, or a partially updated backend can still return something the
compiled TypeScript never predicted.

## Load once for the whole application

Create the context near the types:

```tsx
const SessionContext = createContext<SessionState | null>(null)
```

Then add the provider:

```tsx
export function SessionProvider({ children }: { children: ReactNode }) {
  const [request, setRequest] = useState(0)
  const [state, setState] = useState<SessionState>({ status: 'loading' })

  useEffect(() => {
    const controller = new AbortController()
    setState({ status: 'loading' })

    void loadSession()
      .then((session) => {
        if (controller.signal.aborted) return

        setState(session.user === null
          ? { status: 'guest', session: session as GuestSession }
          : {
              status: 'authenticated',
              session: session as AuthenticatedSession,
            })
      })
      .catch(() => {
        if (!controller.signal.aborted) {
          setState({
            status: 'failed',
            retry: () => setRequest((value) => value + 1),
          })
        }
      })

    return () => controller.abort()
  }, [request])

  return (
    <SessionContext.Provider value={state}>
      {children}
    </SessionContext.Provider>
  )
}
```

The cleanup stops an old request from updating a provider that React has already
replaced. Incrementing `request` gives failure UI an explicit retry without creating
a second copy of the fetching logic.

Expose one safe consumer:

```tsx
export function useSession(): SessionState {
  const state = useContext(SessionContext)

  if (state === null) {
    throw new Error(
      'useSession must be used inside SessionProvider.',
    )
  }

  return state
}
```

## Put the provider above every private route

In `resources/app/main.tsx`, remove the `readAppShellData` import and wrap the routed
application at the final render boundary:

```tsx
createRoot(root).render(
  <StrictMode>
    {document.body.dataset.page === 'auth'
      ? applicationScreen
      : <SessionProvider>{applicationScreen}</SessionProvider>}
  </StrictMode>,
)
```

The login and registration screen still receives its form token from its small auth
bootstrap. Private routes share the provider. Change each router layout from
`<AppLayout shell={shell} />` to:

```tsx
element: <AppLayout />,
```

Now `AppLayout` calls `useSession()`. Its account area can render all four states:

```tsx
{session.status === 'loading' ? (
  <span aria-live="polite">Restoring session…</span>
) : session.status === 'failed' ? (
  <button type="button" onClick={session.retry}>
    Retry session
  </button>
) : session.status === 'guest' ? (
  <nav aria-label="Account">
    <a href="/login">Log in</a>
    <a href="/register">Register</a>
  </nav>
) : (
  <span title={session.session.user.email}>
    {session.session.user.email}
  </span>
)}
```

Keep the existing Tailwind classes on these elements. Only their data source changes.

Move the logout request into `session.tsx` as `destroySession`. `AppLayout` supplies
the token only from the authenticated branch:

```tsx
if (session.status !== 'authenticated') return

window.location.assign(
  await destroySession(session.session.csrfToken),
)
```

The server destroys the session and returns `/login`; React does not pretend logout
succeeded before DALT confirms it. Our existing centralized 401 handler still reloads
the current deep URL. The server's `auth` middleware then remembers that safe GET URL
and sends the browser through login, preserving where the person was working.

## Remove the duplicated identity bootstrap

Delete `resources/app/app-shell-data.ts`. In each protected view—
`resources/views/welcome.view.php`, `resources/views/workspaces/show.view.php`, and
`resources/views/projects/show.view.php`—remove `$shellData` and this script:

```php
<script id="app-shell-data" type="application/json">
  <?= $shellData ?>
</script>
```

Keep each page's own workspace, project, issue, and form data. Session identity is
global application state; page-specific data is not.

## Prove refresh, guest state, malformed data, and retry

Create `resources/app/session.test.tsx`. A tiny consumer makes each provider state
visible:

```tsx
function SessionProbe() {
  const state = useSession()

  if (state.status === 'loading') return <p>Loading session</p>
  if (state.status === 'failed') {
    return <button onClick={state.retry}>Retry session</button>
  }
  if (state.status === 'guest') return <p>Guest session</p>

  return <p>Signed in as {state.session.user.email}</p>
}
```

Use MSW to prove an authenticated response survives a new provider mount, `user:
null` becomes guest state, and malformed JSON reaches the retry branch. On the retry,
return a valid user and expect the signed-in UI. Also add a backend feature test that
calls `GET /api/session` once with `$_SESSION['user']` present and once without it.

Run the complete focused proof:

```bash
npm run typecheck
npm run lint
npm test -- --run
npm run build
php artisan test tests/Feature/AuthenticationTest.php
```

We now have nineteen passing React tests. The authentication feature file has ten
passing tests and 41 assertions. In a real cookie-backed browser flow, registration,
session restoration, logout, and the following guest session request all return the
expected JSON state.

React can finally ask DALT who is present instead of trusting HTML copied during the
initial page render. Next we can change what workspace access means: not one owner
column, but an explicit membership shared by a team.
