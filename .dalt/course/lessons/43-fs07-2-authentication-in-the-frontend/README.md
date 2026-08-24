# FS07.2 — Current-user state and protected navigation

Lesson ID: FS07.2
Lesson format: Concise theory
Part: 07 — Routed and tested frontend
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Integration
Prerequisites: FS07.1
Last reviewed: 2026-08-22

We will turn DALT's server-side session into honest React state before deciding whether a protected screen may render.

> **Helpful background:** [URLs, nested routes, and React Router](/learn/lessons/42-fs07-1-urls-and-react-router)

## What we will learn

- model an unresolved session separately from an anonymous visitor;
- derive the current user from a server endpoint;
- protect navigation without pretending that React provides authorization.

## The browser knows less than the server

After a refresh, every React value is gone. The browser may still hold an HttpOnly session cookie, but JavaScript cannot read it. React therefore starts with one honest fact: the current user is **unknown**.

```text
new React tree → GET /api/me → DALT reads session → safe user or 401
```

The frontend caches that answer so it can present the right navigation. DALT must still authenticate and authorize every API request. Hiding a route or button is useful interface behavior; it cannot stop a crafted HTTP request.

## Model all four session outcomes

A nullable `user` is too vague: `null` could mean the request has not finished, the visitor is anonymous, or the request failed. A discriminated union makes those states impossible to confuse:

```tsx
export type AuthState =
  | { status: 'loading' }
  | { status: 'anonymous' }
  | { status: 'authenticated'; user: PublicUser }
  | { status: 'failed'; message: string };
```

This matters most on a protected URL. Starting at `anonymous` would briefly send a returning user to login before `/api/me` confirms their valid session. Starting at `loading` shows that no decision has been made yet.

## Ask for a safe current user

The endpoint returns only public account fields needed by the interface:

```json
{
  "user": {
    "id": 7,
    "email": "learner@example.test"
  }
}
```

Passwords, password hashes, CSRF secrets, and the session identifier never belong in this response. The real client translates a missing session into `null` and preserves other failures:

```ts
async function getCurrentUser(signal: AbortSignal): Promise<PublicUser | null> {
  const response = await fetch('/api/me', {
    credentials: 'same-origin',
    signal,
  });

  if (response.status === 401) return null;
  if (!response.ok) throw new Error('Could not check your session');

  const body: unknown = await response.json();
  return parseCurrentUser(body);
}
```

Fetch already defaults to `same-origin`; spelling it out documents that this same-origin application relies on its session cookie. `include` is needed when an intentionally cross-origin API must receive credentials, together with the corresponding server policy. Neither setting makes the cookie readable by React.

## One provider resolves the session

The shared lab injects a small `SessionApi`, so production can use fetch while tests use deterministic fakes. The provider owns the request and the state transition:

```tsx
export function AuthProvider({ api, children }: Props) {
  const [auth, setAuth] = useState<AuthState>({ status: 'loading' });

  useEffect(() => {
    const controller = new AbortController();

    api.getCurrentUser(controller.signal)
      .then((user) => {
        if (controller.signal.aborted) return;
        setAuth(user
          ? { status: 'authenticated', user }
          : { status: 'anonymous' });
      })
      .catch(() => {
        if (controller.signal.aborted) return;
        setAuth({ status: 'failed', message: 'Could not check your session.' });
      });

    return () => controller.abort();
  }, [api]);

  return <AuthContext.Provider value={auth}>{children}</AuthContext.Provider>;
}
```

The cleanup prevents an obsolete request from updating a provider that has gone away. A real logout should also call the server first; clearing React state alone leaves the server session alive.

## Protect a route branch

React Router can put one guard around every child that requires a current user:

```tsx
export function RequireAuth() {
  const auth = useAuth();

  if (auth.status === 'loading') return <p>Checking your session…</p>;
  if (auth.status === 'failed') return <p role="alert">{auth.message}</p>;
  if (auth.status === 'anonymous') return <Navigate to="/login" replace />;

  return <Outlet />;
}
```

Then protected routes become a nested branch:

```tsx
<Route path="/login" element={<LoginPage />} />
<Route element={<RequireAuth />}>
  <Route path="/account" element={<AccountPage />} />
  <Route path="/workspaces/:workspaceId" element={<WorkspacePage />} />
</Route>
```

`replace` keeps the rejected protected location from becoming a useless Back-button stop. Later, login can deliberately remember a validated internal destination if the product needs return navigation.

## 401 and 403 are different stories

A **401** means this request has no valid authenticated session. Login may help. A **403** means the server knows the user and refuses this operation; another login does not grant membership or ownership.

```tsx
if (error.status === 401) return <Navigate to="/login" replace />;
if (error.status === 403) return <AccessDenied />;
return <p role="alert">Could not load this issue.</p>;
```

Keep those outcomes visible and distinct. Also remember that an authenticated screen can become stale when a session expires or another tab logs out. Its next API 401 must recover to anonymous state rather than leave private data presented as current.

## Try it

**Workspace:** continue in the shared Batch 9 lab, or copy a clean starter:

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/frontend-testing-lab/starter \
  .dalt/workspace/fs07-frontend-testing
cd .dalt/workspace/fs07-frontend-testing
npm ci
```

**Starting state:** `src/AuthSession.tsx` contains the four-state provider, a protected route branch, and a small account page. Its tests supply session outcomes without a real server.

```bash
npm run test:session
npm run typecheck
npm run build
```

**Expected result:** four session tests pass, TypeScript reports no errors, and Vite creates the production bundle. The tests prove that an unresolved request does not redirect, an anonymous visitor reaches login, a current user reaches Account, and a network failure remains visible.

Temporarily initialize the provider as `anonymous`, or redirect from the loading branch, and observe which returning-user guarantee the tests lose.

**Reset:** keep the workspace for FS07.3 and FS07.4, or delete it and copy the starter again.

## What to notice

Navigation waits for server evidence. React stores a convenient copy of identity, while the HttpOnly cookie and DALT session remain outside React and every protected endpoint remains responsible for access control.

## Check your understanding

1. Why must `loading` and `anonymous` be separate states?
2. Why can the frontend not reconstruct the current user from an HttpOnly cookie?
3. What should differ between a 401 and a 403 response?
4. What security claim does `<RequireAuth />` not prove?

<details><summary>Check your answers</summary>

1. Loading means the server has not answered; anonymous means it answered that no valid session exists. Collapsing them causes premature redirects.
2. HttpOnly deliberately prevents JavaScript from reading the cookie. React asks the server for a safe public representation instead.
3. A 401 can recover through login; a 403 needs an access-denied explanation because the known user lacks permission.
4. It cannot prove an API operation is authorized. Only the server can enforce that against the actual request.
</details>

## Next

Next we will test React components through the controls, messages, and results a user can observe.

<details><summary>Maintainer source record</summary>

- Source dossier: Full Stack Open Part 5 research notes.
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: React conditional rendering and effects; React Router Navigate and Outlet; Fetch `Request.credentials`; MDN HTTP 401 and 403.
- Versions: React 19.2.3; React Router 7.18.2; TypeScript 5.9.3.
- Consulted: 2026-08-22.
- Curriculum authority: DALT Fullstack theory curriculum, Batch 9, FS07.2.
- DALT files inspected: `framework/Core/Session.php`, `config/session.php`, and the frontend-testing lab toolchain.
- Reused material: explicit auth states, current-user loading, server authority, protected route UX, abort cleanup, and distinct 401/403 recovery from the former FS07.2.
- Corrected material: same-origin fetch sends credentials by default; the former lesson incorrectly claimed that omitting `credentials: 'include'` made a same-origin `/api/me` request anonymous.
</details>
