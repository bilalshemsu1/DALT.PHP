# FS07.1 — URLs, nested routes, and React Router

Lesson ID: FS07.1
Lesson format: Concise theory
Part: 07 — Routed and tested frontend
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Foundation
Prerequisites: FS06.5
Last reviewed: 2026-08-22

We will give meaningful screens durable browser addresses and translate those addresses into a nested React interface.

> **Helpful background:** [Membership, roles, ownership, and authorization](/learn/lessons/41-fs06-3-authorization-and-ownership)

## What we will learn

- distinguish route paths, links, route parameters, and search parameters;
- share a layout with nested routes and `<Outlet />`;
- validate URL values and provide intentional not-found behavior.

## A useful screen deserves an address

If the selected issue exists only in `useState`, refreshing loses it, Back cannot restore it, and another person cannot open a shared link. A URL is browser-owned application state:

```text
browser location → route match → params + search params → rendered screen
```

React Router supports several architectures. We use its small **Declarative Mode** because this course needs URLs and navigation, while our API functions continue to own HTTP and later TanStack Query will own server-state caching.

The shared lab pins React Router 7.18.2 alongside React 19.2.3. When adding it to an equivalent application, keep that tested pair exact:

```bash
npm install react-router@7.18.2
```

## A route table maps locations to UI

`BrowserRouter` listens to the real address bar. `<Routes>` chooses the matching branch, and each `<Route>` names a path and an element:

```tsx
import { BrowserRouter, Route, Routes } from 'react-router';

createRoot(document.getElementById('root')!).render(
  <BrowserRouter>
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/issues/:issueId" element={<IssuePage />} />
      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  </BrowserRouter>,
);
```

The `*` route gives a stale or mistyped address a useful page instead of a blank screen. This client not-found state differs from an API 404: one means no UI route matched; the other means a route matched but the requested database row does not exist.

## Nested routes share structure

A workspace heading and navigation should not be copied into every project screen. A parent route owns that shared layout, while `<Outlet />` marks where its matching child renders:

```tsx
function WorkspaceLayout() {
  const { workspaceId } = useParams();

  return (
    <main>
      <h1>Workspace {workspaceId}</h1>
      <WorkspaceNavigation />
      <Outlet />
    </main>
  );
}
```

The corresponding route tree mirrors the product hierarchy:

```tsx
<Route path="/workspaces/:workspaceId" element={<WorkspaceLayout />}>
  <Route path="projects/:projectId" element={<ProjectPage />} />
</Route>
```

The child path is relative, so it becomes `/workspaces/:workspaceId/projects/:projectId`. Navigating between children replaces the outlet content without discarding the parent layout.

## Links preserve browser behavior

Use a real `<Link>` for ordinary navigation:

```tsx
<Link to={`/issues/${issue.id}`}>{issue.title}</Link>
```

It provides a real destination that can be copied, opened in another tab, and restored through browser history. `useNavigate()` is for transitions caused by an operation—after a successful login or deletion—not a replacement for every link.

`NavLink` is useful when global navigation needs an active style. It derives that state from the current location, so we do not duplicate it in another `useState` value.

## Every URL value is untrusted text

Dynamic segments begin with `:` and arrive through `useParams()` as strings or `undefined`:

```tsx
const { issueId } = useParams();

if (issueId === undefined || !/^ISS-[0-9]+$/.test(issueId)) {
  return <NotFoundPage />;
}
```

TypeScript cannot validate text someone typed into the address bar. Parse and validate before calling the API; otherwise malformed input becomes an accidental request.

Search parameters describe shareable view choices such as filters and sorting:

```tsx
const allowed = ['todo', 'done'] as const;
const [search, setSearch] = useSearchParams();
const raw = search.get('status');
const status = allowed.includes(raw as 'todo' | 'done') ? raw : 'all';

setSearch({ status: 'todo' });
```

Validate them too. A stale `?status=archived` bookmark should reach a defined fallback. Passwords, CSRF tokens, and unsaved drafts do not belong in a URL because URLs enter history, copied links, logs, and analytics.

## React routes and DALT routes meet on refresh

Clicking a client link reuses the document already loaded. Refreshing `/issues/ISS-41` makes a new request to DALT before React exists, so DALT must serve the same application document for each client-owned prefix:

```php
$router->get('/login', 'app.php');
$router->get('/workspaces/{*}', 'app.php');
$router->get('/issues/{*}', 'app.php');
```

Keep API routes separate and avoid a root `/{*}` fallback that could swallow `/learn` or another server surface. A successful route match still grants no data access: the protected API must independently return 401, 403, or 404 as appropriate.

## Try it

**Workspace:** copy the shared Batch 9 lab:

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/frontend-testing-lab/starter \
  .dalt/workspace/fs07-frontend-testing
cd .dalt/workspace/fs07-frontend-testing
npm ci
```

**Starting state:** `src/RouteDemo.tsx` contains a nested workspace/project route, issue links, a validated filter, an issue route, and a catch-all page. Run:

```bash
npm run test:routing
```

**Expected result:** four tests pass. They prove that the nested headings render together, a real link reaches issue detail, an unknown search value falls back to `all`, and an invalid issue ID reaches the not-found page.

Change `initialEntries` in one test, or temporarily remove `<Outlet />`, and read which observable behavior disappears.

**Reset:** keep this workspace for the remaining Part 07 lessons, or delete it and copy the starter again.

## What to notice

The URL, not hidden component state, selects the project, filter, and issue. The parent layout and child screen render as one tree, and malformed browser input is stopped before any API call.

## Check your understanding

1. What does `<Outlet />` contribute to a nested route?
2. Why is `issueId` still untrusted after `useParams()` returns it?
3. Which state belongs in search parameters?
4. Why must DALT know the client route prefixes?

<details><summary>Check your answers</summary>

1. It marks where the matched child element renders inside its parent layout.
2. The address bar is a runtime input anyone can edit; a TypeScript route declaration cannot validate its contents.
3. Non-sensitive choices worth sharing or restoring, such as filters, sort order, and page number.
4. A refresh requests the nested address from DALT before React can match it, so DALT must return the application document there.
</details>

## Next

Next we will resolve the current server session before deciding whether a protected route may render.

<details><summary>Maintainer source record</summary>

- Source dossier: `FSO_PART_05.md`.
- Official sources: React Router 7.18.2 Declarative Mode routing, navigating, URL-values, and Outlet documentation; MDN History API.
- Versions: React 19.2.3; React Router 7.18.2; TypeScript 5.9.3.
- Consulted: 2026-08-22.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 9, FS07.1.
- DALT files inspected: `framework/Core/Router.php`, `routes/routes.php`, `public/index.php`, and the frontend-testing lab toolchain.
- Reused material: URL ownership, route parameters, validated search state, DALT deep-link fallbacks, nested layouts, and the authorization boundary from the former FS07.1.
</details>
