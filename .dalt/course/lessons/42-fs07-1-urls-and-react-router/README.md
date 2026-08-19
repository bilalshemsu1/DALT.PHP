# FS07.1 — URLs and React Router

Lesson ID: FS07.1
Title: URLs and React Router
Part: 07 — React structure, routing and testing
Order: 1
Status: Published
Estimated effort: 90–120 minutes
Difficulty: Integration
Prerequisites: FS06.3 — Authorization and ownership
Project milestone: B07 — Navigable tested application
Primary source dossier: FSO_PART_07.md
Last reviewed: 2026-08-19

## Why this matters

An application becomes frustrating the moment a useful screen has no address. A selected issue
stored only in component state disappears on refresh, cannot be bookmarked, and makes Back mean
something accidental. A URL is durable, shareable state owned by the browser. Routing is the
translation from browser state into a React view; it is not just a menu component.

Your issue tracker already has durable resources and server-side permission rules. This lesson
gives those resources meaningful locations. The URL says which workspace, project, issue, or
filter a person intends to see. React renders the corresponding screen; the server remains the
authority for whether that screen's data may be read. Keeping those jobs separate makes refresh,
deep links, browser history, and later tests much easier to reason about.

## Before you start

Complete FS06.3 and keep its `/api/me` and protected resource behavior. Install the router in
your learner application at the version this course pins:

```sh
npm install react-router@7.18.2
npm run typecheck
```

**Install the exact version, and understand why.** React Router 8 requires React 19.2.7 or
newer; this repository pins React 19.2.3 under CR-08. A bare `npm install react-router`
resolves to the 8.x line and fails outright:

```text
npm error code ERESOLVE
npm error Found: react@19.2.3
```

That is npm protecting you, not obstructing you — a peer dependency is a library stating
which versions of React it was built against. The fix here is to pin the router, not to
force the install with `--legacy-peer-deps`. Overriding a peer conflict gets you a working
`npm install` and a runtime failure later, which is a far worse trade than a clear error
now. Nothing in Parts 07–09 uses an API that differs between the 7 and 8 lines.

Going deeper in DALT Core — optional:

- None. This is frontend application structure; Core is not a prerequisite.

## By the end

You should be able to:

- distinguish a route, a link, a route parameter, and a query parameter;
- make URL state survive refresh and browser Back/Forward;
- build a small route table with a useful not-found state;
- read and validate route parameters before using them as application input;
- explain why a client route never grants access to protected server data.

## Predict before reading

Write answers down before reading on.

1. What should happen when someone pastes `/issues/999999` into a new tab?
2. Which state belongs in `?status=open`: a visible filter, a form draft, or both?
3. If a React route hides a project, can a direct API request still fetch it?

## Mental model

```text
browser location → router match → route params + search params → screen → API request
                                                              ↓
                                                     server authorization
```

The History API changes the location without requiring a full document navigation. A router
listens to that location and selects a component. Use a normal link for application navigation
because it preserves browser semantics; do not attach a click handler to a generic div merely to
call navigate.

## Route state is application state

Start with a deliberately small, flat route table. It matches the project blueprint and avoids a
giant nested tree before you have a layout problem worth solving.

```tsx
import { BrowserRouter, Route, Routes } from 'react-router';

export function AppRouter() {
  return <BrowserRouter><Routes>
    <Route path="/login" element={<LoginPage />} />
    <Route path="/workspaces/:workspaceId/projects/:projectId" element={<ProjectPage />} />
    <Route path="/issues/:issueId" element={<IssuePage />} />
    <Route path="*" element={<NotFoundPage />} />
  </Routes></BrowserRouter>;
}
```

BrowserRouter owns location updates; Routes chooses exactly one matching route. A route describes
a screen location, while the API request made by that screen still handles loading, missing
resources, and denial.

## Links, parameters, and search

Use Link for a resource transition. It creates an address the user can copy and lets the router
intercept an ordinary browser navigation.

```tsx
import { Link } from 'react-router';

<Link to={`/issues/${issue.id}`}>{issue.title}</Link>
```

Read route values as untrusted strings. A URL can be typed, modified, or restored from history;
TypeScript's route declaration does not turn "oops" into a valid database id.

```tsx
import { useParams } from 'react-router';

const { issueId } = useParams();
const id = Number(issueId);
if (!Number.isInteger(id) || id < 1) return <NotFoundPage />;
```

Use search parameters for a shareable view choice such as a status filter, not for a half-typed
password or an unsaved issue title. A small helper keeps the boundary explicit.

```tsx
import { useSearchParams } from 'react-router';

const [search, setSearch] = useSearchParams();
const status = search.get('status') === 'closed' ? 'closed' : 'open';
setSearch({ status: 'closed' });
```

## Layout and failure states

An application shell is useful when global navigation belongs around several authenticated pages.
It should not fetch a resource merely because it renders a header. Keep route screens responsible
for their resource boundary and render an intentional 404 for an unmatched client location.

```tsx
export function NotFoundPage() {
  return <main><h1>Page not found</h1><Link to="/login">Go to login</Link></main>;
}
```

A route match is not proof a record exists. A valid `/issues/42` may receive 404, 401, or 403
from the API. Display a clear state and never turn denial into a blank page. Do not redirect every
failure to login: an authenticated user forbidden from an issue needs a different explanation.

## Make the server answer every address React owns

Everything above works the moment you click a Link, because the browser never leaves the
document React already loaded. It stops working the moment you refresh `/issues/42` or paste it
into a fresh tab, because that *is* a new document request, and DALT — not React — decides how to
answer it. `Core\Router` matches an exact path or a single `{param}` segment; it has nothing
today that means "and anything under here," so a direct request to any client location except
whichever one DALT already serves the shell at gets a 404 before React has a chance to boot.

The fix is not one route, because your table has three unrelated top-level names, and it is not a
route per client path, because `/issues/42/comments` would need a fourth one the day you add
comments. Register one server route **per top-level resource name**, using `{*}` — a path
segment that matches the name itself and everything nested under it — to absorb whatever React
adds beneath it later:

```php
// routes/routes.php — after your /api/* routes, before anything else
$router->get('/login', 'app.php');
$router->get('/workspaces/{*}', 'app.php');
$router->get('/issues/{*}', 'app.php');
```

`app.php` is the same controller that already serves your built frontend for `/` — it returns the
one HTML document every client route boots from; React Router then reads the actual browser
location and picks the screen. This is three lines because your route table has three top-level
names, not because the pattern does not scale: `/issues/{*}` matches `/issues/42` and
`/issues/42/comments` identically, so a deeper client route never needs a fourth line.

**Do not** reach for a bare `/{*}` at the root of `routes/routes.php` to cover all three in one
line. Routes match in registration order and DALT's own `.dalt` pages (`/learn/...`) register
*after* your file, so an unscoped root fallback would swallow every one of them structurally,
before their handlers ever run — a router 404 you cannot fix from inside a controller, because
the match already happened. Scope the wildcard to the names your own route table actually owns.

Prove it the way FS07.1's own "Working looks like" will ask for — a browser tab you did not
navigate to from inside the app:

```sh
php artisan serve &
curl -i http://127.0.0.1:8000/issues/42        # 200, the built document
curl -i http://127.0.0.1:8000/api/issues/42    # unchanged: still your JSON API
```

If the second line now returns HTML instead of JSON, a route registered earlier than intended is
too broad — check order before touching either handler.

## Try it

Add one project route, one issue-detail route, links from the issue list, and a not-found page.
Open a detail page in a fresh tab, refresh it, use Back and Forward, and copy an `?status=open`
link into another tab. Then request an issue outside the signed-in user's workspace directly; the
server response must remain denied even if you manually navigate to its route.

```text
valid URL + missing record      → API 404 screen
valid URL + anonymous request   → login recovery
valid URL + forbidden request   → access-denied screen
invalid URL parameter           → client not-found screen
```

## Search parameters, validated like everything else

The `status` read at the top of this lesson pulls one value out of the URL. A real filter reads
several, and every one of them is exactly as untrusted as `issueId` was — a search string gets
edited by hand, restored from a bookmark, or shared in a link, and none of those paths run
through your TypeScript.

```tsx
const ALLOWED_STATUSES = ['open', 'in_progress', 'closed'] as const;
type StatusFilter = (typeof ALLOWED_STATUSES)[number];

function readStatus(search: URLSearchParams): StatusFilter {
  const value = search.get('status');
  return (ALLOWED_STATUSES as readonly string[]).includes(value ?? '')
    ? (value as StatusFilter)
    : 'open';
}
```

**Predict, then check:** what renders for `?status=DROP%20TABLE`? With `readStatus` in place it
falls back silently to `'open'` — the same answer a stale bookmark gets. Without it, `status`
is the literal string `DROP TABLE`, handed straight to `IssueList`, and whatever that component
does with an unrecognised value is now undefined behaviour. Try both and watch the difference.

Search parameters have a second cost beyond validity: they are visible, in copied links, browser
history, and often server logs. That's fine for a status filter and wrong for a password, a CSRF
token, or an unsaved issue title — those belong in state a URL can never carry, never in
`useSearchParams`.

## Don't let an effect redirect on every render

The tempting version of a protected-route redirect looks like this:

```tsx
// Wrong. Fires on every render where auth.status changes — including the
// one this redirect itself causes — and fights the browser for control
// of what Back actually does.
useEffect(() => {
  if (auth.status === 'anonymous') navigate('/login');
}, [auth.status]);
```

**Predict, then check:** log in, then press Back. What happens? The effect fires again on the
next render, sees a stale `auth.status` before the new one has settled, and can bounce the user
straight back to `/login` — a broken Back button that looks like a router bug and is actually a
synchronisation bug, the same shape FS03.2 warned about for any other piece of state.

Redirect only at the one moment that's actually a decision — an anonymous visitor's session has
finished resolving, on a route that requires one — and make it part of the render that already
owns that branch, not a side effect watching for it to change:

```tsx
if (auth.status === 'loading') return <p>Checking your session…</p>;
if (auth.status === 'anonymous') return <Navigate to="/login" replace />;
```

That's the whole fix: derive the redirect from the value you already have, instead of watching
for it to change after the fact.

## Write the route contract down

A route is a contract between a person, the browser, and the application: path in, screen and
outcomes out. Writing it down catches the mistake of designing only the happy path and leaving
every other result to whatever the conditional rendering happens to do:

| Path | Params | Calls | Title | Recovery states |
|---|---|---|---|---|
| `/login` | — | `POST /api/login` | Sign in | invalid credentials |
| `/workspaces/:workspaceId/projects/:projectId` | positive int | `GET /api/projects/:id` | project name | 401 → login, 403 → access denied, 404 → not found |
| `/issues/:issueId` | positive int | `GET /api/issues/:id` | issue title | same three, plus malformed id → client not-found |

Three things are worth checking once this table exists, not before it. A link needs a real
destination and a name a screen reader can announce — `<Link to={...}>{issue.title}</Link>`, not
a styled `<div>`. A route change that replaces the whole screen should move focus to the new
page heading, or a keyboard user lands on an address with no announced destination. And a pasted
deep link is the honest test of all of it — a dev server can make client routing look correct
while never once asking the server for a nested document, so refreshing `/issues/42` for real,
not just clicking to it, is what actually proves the contract above holds.

Write down the expected title, response status, and recovery link for each route outcome. That
small table makes a route contract reviewable before implementation and protects it during later
state-management changes.

## Common mistakes

### Keeping the selected issue only in `useState` when it's a page location

A selected issue that only lives in component state disappears on refresh and can't be bookmarked or shared. If it's meaningful enough to want back after a reload, it belongs in the URL.

### Treating every URL segment as a trusted number

A route parameter is a string typed, edited, or restored from history by a person or a script. `Number(issueId)` can produce `NaN`; validate before it ever reaches the API as though it were a real id.

### Using `a href="#"` or clickable non-buttons instead of `Link`

An anchor with no real destination, or a `div` with an `onClick`, loses the browser behaviours a real navigation gives you for free — a copyable address, a working new-tab, sensible history.

### Putting a sensitive form draft or token in a query string

Query strings show up in copied links, browser history, and sometimes server logs. Reserve them for non-sensitive view choices like a status filter, never for anything a person wouldn't want reappearing in someone else's browser history.

### Calling a client-side redirect an authorization check

A route that redirects an unauthenticated visitor is a usability improvement, not a security boundary. The server still has to refuse the request independently, because nothing stops a direct one that skips the redirect entirely.

### Forgetting an unmatched-route state and presenting a blank screen

A route table with no catch-all leaves a mistyped or stale URL rendering nothing at all — the worst possible feedback for someone trying to figure out what went wrong.

## When this goes wrong

If a direct URL works in development but 404s after deployment, distinguish the server's static
asset fallback from React's route matching; do not change the route table blindly. If a page
refetches with NaN, inspect the parsed parameter at the component boundary and reject it before
calling the API. If Back feels wrong, identify state that belongs in the URL rather than adding
another effect that mirrors location into local state.

```tsx
const parsedId = Number(issueId);
const canLoad = Number.isSafeInteger(parsedId) && parsedId > 0;
return canLoad ? <IssueScreen issueId={parsedId} /> : <NotFoundPage />;
```

## Exercise

### Goal

Give issue detail and project filtering durable URLs.

### Starting state

The authenticated issue tracker can list data from its DALT API.

### Requirements

- Implement the routes shown above, plus one `Link` into issue detail.
- Validate route parameters before using them.
- Add a `?status=` filter.
- Build an intentional not-found page.
- Keep authorization entirely on the API — the route table only decides what's *shown*, never what's *allowed*.

### Constraints

- No route parameter reaches the API without being validated first.
- No sensitive value — a draft, a token — lives in a query string.
- No client-side redirect stands in for a server authorization check.

### Verification

**Mode: tool-run — browser behavior plus `npm run typecheck`.** The platform does not grade this exercise; the observable browser and API results are the evidence.

Refresh each URL, use browser Back/Forward, open one copied link in a new tab, and show that an unauthorized direct API request remains denied.

### Hints

<details>
<summary>Hint 1 — where to start</summary>

Build the not-found page first. Every other route needs somewhere to fall back to, and building it last means testing it last.
</details>

<details>
<summary>Hint 2 — build order</summary>

Make a single detail route work end to end before extracting a shared layout. A layout built around one working route is easier to get right than one built around three unfinished ones.
</details>

<details>
<summary>Hint 3 — treat every param as untrusted</summary>

Treat every `useParams` value as `string | undefined`, never as the type your database expects. Validate and convert it explicitly before it reaches any API call.
</details>

<details>
<summary>Reference explanation — read after an honest attempt</summary>

The working shape is the route table in "Route state is application state," the `Number.isSafeInteger` guard from "When this goes wrong," and the server-side `{*}` wildcard routes from "Make the server answer every address React owns." The proof isn't that clicking through the app works — it's that a pasted deep link, a refresh, and a stopped-server direct API request all still behave correctly.
</details>

## In the project

B07 turns a collection of components into an application with locations. Keep the route table
small: login, a project location, and an issue location are enough. FS07.2 adds an authenticated
shell around these routes; FS07.3 proves their observable behavior.

## Closed-book checkpoint

Close the lesson first.

1. Why is an issue id in a URL still untrusted input?
2. Which kinds of state make good query parameters?
3. What is the difference between a client-side 404 and an API 404?
4. Why does a hidden route never replace server authorization?
5. What browser behavior does Link preserve that local state cannot?

<details>
<summary>Reveal comparison answers</summary>

1. A URL can be typed, edited, or restored from history by anyone. TypeScript's route declaration doesn't turn a malformed value into a valid database id — it's just a string until something checks it.
2. Non-sensitive, shareable view choices — a status filter, a sort order, a page number. Anything a person wouldn't mind seeing reappear in a copied link or browser history.
3. A client-side 404 means the router found no matching route for the current location. An API 404 means the route matched and the request reached the server, but the specific resource doesn't exist. They're different failures at different layers.
4. Because a route decision runs entirely in the browser the user controls. A direct request that skips the hidden route entirely reaches the API exactly the same as any other request, so only server-side authorization actually protects anything.
5. A real address the user can copy, bookmark, open in a new tab, or return to with Back/Forward — none of which a value held only in component state survives.
</details>

## Resources

### Read

- [React Router: routing](https://reactrouter.com/start/declarative/routing)
- [React Router: URL values](https://reactrouter.com/start/declarative/url-values)
- [MDN: History API](https://developer.mozilla.org/en-US/docs/Web/API/History_API)

### Go deeper

- [React: You might not need an Effect](https://react.dev/learn/you-might-not-need-an-effect)

## You are done when

- [ ] Resource pages have stable, refreshable URLs.
- [ ] Links, route parameters, query parameters, and not-found behavior are intentional.
- [ ] Invalid route values do not reach the API as accidental ids.
- [ ] Browser Back and Forward restore meaningful screen state.
- [ ] The server still denies an unauthorized direct resource request.
- [ ] A direct request or refresh at `/login`, `/workspaces/...`, and `/issues/...` returns the
      built document from DALT, not a 404 — proven with curl, not only by clicking inside the app.
- [ ] `npm run typecheck` passes.

## Maintainer source record

Source dossier: `docs/dalt-fullstack/sources/FSO_PART_07.md`.

Official sources: React Router routing and URL-values documentation; MDN History API, linked above.

Versions: React 19.2.3; React Router 7.18.2 (the 8.x line requires React >=19.2.7).

Consulted: 2026-08-15.

Curriculum authority: `CURRICULUM.md` §18, FS07.1; `PROJECT_BLUEPRINT.md` §§40–41.

Follow-up pass: 2026-08-19 — verified the React/react-router version pins against `package.json`, and the `{*}` wildcard route-registration-order claim (the app's own `routes/routes.php` loads before `.dalt`'s platform routes) against the actual `public/index.php` boot sequence, no discrepancies found; restructured Exercise into LESSON_STANDARD.md §97's subsections with a hint ladder and reference explanation; split Common mistakes into explained subsections; added a Closed-book checkpoint answer reveal. Content pass (owner-approved): replaced four generic, code-free essay sections ("Route design review," "Working through route failures," "Practical route checklist," "One more verification pass" — largely restating each other) with three tighter, code-grounded sections carrying the same ideas — validated/allowlisted search params, the effect-redirect anti-pattern, and a concrete route-contract table — matching the predict-then-verify style used elsewhere in the course.
