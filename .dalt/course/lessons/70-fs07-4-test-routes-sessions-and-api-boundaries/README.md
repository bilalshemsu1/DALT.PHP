# FS07.4 — Test routes, sessions, and API boundaries

Lesson ID: FS07.4
Lesson format: Concise theory
Part: 07 — Routed and tested frontend
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Integration
Prerequisites: FS07.3
Last reviewed: 2026-08-23

We will test the small set of places where a URL, the current session, and an API response change one another's meaning.

> **Helpful background:** [Test components through user behavior](/learn/lessons/44-fs07-3-test-frontend-behavior)

## What we will learn

- render real route definitions at a chosen in-memory location;
- combine a fake session and fake resource API without a server;
- distinguish invalid input, anonymous access, expired sessions, and forbidden resources.

## A boundary test joins decisions

FS07.1 tested route matching. FS07.2 tested session navigation. FS07.3 tested a component through a fake client. Each narrow test is useful, but some regressions exist only where those decisions meet:

```text
URL → route parameter → session decision → API request → response status → screen
```

A protected issue page must not request private data for an anonymous visitor. A user whose session expires during the issue request needs login recovery. A signed-in user denied by policy needs an access-denied screen, not login. Those are boundary behaviors.

We do not need every possible combination. We choose cases where one layer changes what the next layer should do.

## Start at a real location

`MemoryRouter` holds history in memory and lets a test choose its initial URL:

```tsx
function renderAt(path: string, api: BoundaryApi) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <BoundaryApp api={api} />
    </MemoryRouter>,
  );
}
```

`BoundaryApp` contains the real route branch used by this experiment:

```tsx
<Routes>
  <Route path="/login" element={<h1>Sign in</h1>} />
  <Route element={<RequireAuth />}>
    <Route path="/issues/:issueId" element={<IssueBoundaryPage api={api} />} />
  </Route>
  <Route path="*" element={<h1>Page not found</h1>} />
</Routes>
```

Rendering the page component alone would skip route matching, parameter extraction, and navigation. Rendering the route branch proves those pieces cooperate.

## One fake describes two server boundaries

The experiment's client joins the current-user call and issue call:

```ts
export type BoundaryApi = SessionApi & {
  getIssue(id: string, signal: AbortSignal): Promise<BoundaryIssue>;
};
```

A typed factory supplies successful defaults, while each test overrides only the outcome it needs:

```tsx
function fakeApi(overrides: Partial<BoundaryApi> = {}): BoundaryApi {
  return {
    getCurrentUser: async () => signedInUser,
    getIssue: async () => ({ id: 'ISS-41', title: 'Trace a request' }),
    ...overrides,
  };
}
```

This is still not a backend test. It assumes that DALT returns the documented statuses. FS06 proves those responses through HTTP; this suite proves React interprets them correctly.

## Anonymous access stops before resource loading

For a direct visit to a protected URL, the session must resolve before the issue request begins:

```tsx
it('redirects an anonymous direct visit before loading private data', async () => {
  const getIssue = vi.fn();

  renderAt('/issues/ISS-41', fakeApi({
    getCurrentUser: async () => null,
    getIssue,
  }));

  expect(await screen.findByRole('heading', { name: 'Sign in' }))
    .toBeInTheDocument();
  expect(getIssue).not.toHaveBeenCalled();
});
```

The visible login heading proves navigation finished. Only then do we assert that the issue client was untouched. Checking the call immediately after render could pass before either asynchronous branch had time to run.

## 401 after login means the session became stale

The initial current-user request may succeed and the next protected request may return 401 because the session expired between them:

```tsx
renderAt('/issues/ISS-41', fakeApi({
  getIssue: async () => { throw new BoundaryApiError(401); },
}));

expect(await screen.findByRole('heading', { name: 'Sign in' }))
  .toBeInTheDocument();
```

This case differs from an initially anonymous visitor, but both recover through login. It prevents the frontend from leaving a stale authenticated shell around a failed private request.

## 403 keeps the known user

A forbidden response says authentication succeeded but policy refused this resource:

```tsx
renderAt('/issues/ISS-41', fakeApi({
  getIssue: async () => { throw new BoundaryApiError(403); },
}));

expect(await screen.findByRole('heading', { name: 'Access denied' }))
  .toBeInTheDocument();
expect(screen.queryByRole('heading', { name: 'Sign in' }))
  .not.toBeInTheDocument();
```

This protects a subtle recovery rule: login cannot grant a workspace membership the current user does not have. Collapsing every non-2xx response into one redirect loses information the server deliberately supplied.

## Reject malformed IDs before the API

Route parameters are untrusted strings. The page validates the identifier before requesting data:

```tsx
const getIssue = vi.fn();
renderAt('/issues/not-an-issue', fakeApi({ getIssue }));

expect(await screen.findByRole('heading', { name: 'Page not found' }))
  .toBeInTheDocument();
expect(getIssue).not.toHaveBeenCalled();
```

This is different from an API 404 for a valid-looking `ISS-999`. The first is malformed browser input and requires no request; the second is a resource lookup whose absence only the server can know.

## Keep the suite layered

Use the cheapest level that can prove each claim:

```text
pure parser test       malformed JSON is rejected
component test         validation error preserves the draft
boundary route test    a 403 renders access denied instead of login
backend HTTP test      a non-member's forged mutation is refused
browser journey        built assets, cookies, DALT, and navigation work together
```

Boundary tests should remain few. If the claim does not depend on routing or session state, keep it in the component suite. If it depends on real cookies, middleware, or database policy, move it to backend or browser evidence.

## Try it

**Workspace:** continue in the shared Batch 9 lab, or copy a clean starter:

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/frontend-testing-lab/starter \
  .dalt/workspace/fs07-frontend-testing
cd .dalt/workspace/fs07-frontend-testing
npm ci
```

**Starting state:** `src/BoundaryApp.tsx` combines the session provider, protected issue route, parameter validation, and API-status rendering. `BoundaryApp.test.tsx` supplies every server outcome through one typed fake.

```bash
npm run test:boundaries
npm run typecheck
npm run build
```

**Expected result:** five boundary tests pass, TypeScript reports no errors, and Vite creates the production bundle. The cases prove anonymous short-circuiting, authenticated success, distinct 403 handling, expired-session 401 recovery, and malformed-ID rejection without an API call.

Temporarily change the 403 branch to `sign-in-required`; the forbidden test must fail because Sign in replaces Access denied.

**Reset:** delete the workspace copy when finished. The experiment is disposable and does not change the cumulative Build application.

## What to notice

The tests begin from a URL and finish at visible behavior. Their fakes control server facts, but they still exercise the real provider, route tree, parameter validation, effect, navigation, and status translation between those points.

## Check your understanding

1. Why render the route tree instead of `IssueBoundaryPage` alone?
2. Why wait for the login heading before asserting `getIssue` was not called?
3. Which two scenarios lead to login, and how are they different?
4. Which authorization claim still belongs to a backend test?

<details><summary>Check your answers</summary>

1. The route tree proves path matching, parameter extraction, the auth guard, and navigation cooperate.
2. The heading is positive evidence that asynchronous session handling finished; an immediate absence assertion could pass before the code ran.
3. An initially anonymous session and a later 401 both lead to login. The first has no current user; the second means a previously valid client view became stale.
4. That the server actually refuses a forged request from a user without permission.
</details>

## Next

Next we will classify local, URL, session, and server state before introducing a server-state cache.

<details><summary>Maintainer source record</summary>

- Source dossier: `FSO_PART_05.md`.
- Official sources: React Testing Library introduction and query priority; React Router MemoryRouter, Navigate, route, and URL-value documentation; Vitest mock functions; MDN HTTP 401 and 403.
- Versions: React 19.2.3; React Router 7.18.2; Vitest 4.0.18; React Testing Library 16.3.2; TypeScript 5.9.3.
- Consulted: 2026-08-23.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 9, FS07.4.
- DALT files inspected: the complete frontend-testing lab, Part 07 track manifest, and frontend lab execution guard.
- Extracted material: MemoryRouter route tests, navigation assertions, test-level selection, session recovery, and authorization-boundary warnings from the former FS07.3 and FS07.2.
</details>
