# FS07.2 — Authentication in the frontend

Lesson ID: FS07.2
Title: Authentication in the frontend
Part: 07 — React structure, routing and testing
Order: 2
Status: Published
Estimated effort: 90–120 minutes
Difficulty: Integration
Prerequisites: FS07.1 — URLs and React Router
Project milestone: B07 — Navigable tested application
Primary source dossier: FSO_PART_07.md
Last reviewed: 2026-08-20

## Why this matters

Frontend authentication is where a server-side session becomes a humane application. It must make a
loading session, an anonymous visitor, a signed-in user, an expired session, and a forbidden
resource visibly different. Those distinctions prevent both security theatre and confusing UI.

## Before you start

Complete the preceding Part 07 lesson and keep the protected API from FS06. The browser may
remember a rendering decision, but the DALT session and API remain authoritative. Work in your
learner application, not in framework or course files.

```sh
npm run typecheck
npm run lint
```

Going deeper in DALT Core — optional:

- None. The DALT Core material is optional reference and never a gate for this track.

## By the end

You should be able to:

- identify the boundary that owns a decision;
- represent loading, success, and failure explicitly;
- make a small, observable behavior change;
- choose evidence that could fail if the behavior regressed;
- explain why a convenient client state is not a security boundary.

## Predict before reading

Write answers down before reading on.

1. What should a route do while current-user information is unknown?
2. What observable result distinguishes a failed request from an empty result?
3. Which important claim can only the server prove?

## Mental model

```text
browser event → client state → request → server session and authorization → response → rendered UI
                     ↑                                                ↓
                  tests observe labels, controls, navigation, and messages
```

The useful boundary is not “frontend versus backend”; it is authority versus presentation.
The frontend uses server facts to choose a helpful experience. The server makes the access
decision. A test should exercise the public behavior at the narrowest level that can prove it.

## Make state explicit

Avoid an initial render that accidentally means “logged out.” Unknown is a real state: the request
has not completed, so redirecting now would be a race. Model it honestly and let the screen decide
what to show.

```tsx
type CurrentUser = { id: number; email: string };
type AuthState =
  | { status: 'loading' }
  | { status: 'anonymous' }
  | { status: 'authenticated'; user: CurrentUser }
  | { status: 'failed'; message: string };
```

A discriminated union prevents a component from reading `user.email` before a user exists. It
also makes a failure visible instead of silently treating a broken network as a logged-out person.

```tsx
if (auth.status === 'loading') return <p>Checking your session…</p>;
if (auth.status === 'failed') return <p role="alert">{auth.message}</p>;
if (auth.status === 'anonymous') return <Navigate to="/login" replace />;
return <AppShell user={auth.user} />;
```

## Keep authority on the server

A protected route is a UX boundary: it avoids presenting a page that cannot work. It does not
stop a crafted request. On 401, recover to login; on 403, explain that this user lacks access;
on 419, preserve a safe draft and obtain fresh CSRF proof according to your API contract.

```ts
export async function getCurrentUser(signal?: AbortSignal): Promise<CurrentUser | null> {
  const response = await fetch('/api/me', { credentials: 'include', signal });
  if (response.status === 401) return null;
  if (!response.ok) throw new Error('Could not load the current user');
  return response.json();
}
```

Do not put a user record, password, or a session token in localStorage. The session cookie is
handled by the browser according to its security attributes; frontend state is a cache of the
safe response, not a second identity system.

```tsx
function IssueActions({ canEdit }: { canEdit: boolean }) {
  return canEdit ? <button type="button">Edit issue</button> : null;
}
```

Hiding an action is still useful: it reduces confusion. But `canEdit` must derive from safe
server data and its absence never authorizes a mutation. A server test from B06 remains the proof
that a forged PATCH cannot succeed.

## Why the cookie carries identity, not a header

Every fetch above sends `credentials: 'include'` and none of them attach an `Authorization`
header or read a token out of the response. That is not an oversight — it is the whole design,
and DALT's session configuration (`config/session.php`) is what makes it safe:

```php
'cookie' => [
    'secure' => env('SESSION_SECURE_COOKIE'),   // auto-detects HTTPS when unset
    'httponly' => true,                          // never negotiable
    'samesite' => env('SESSION_SAME_SITE', 'Lax'),
],
```

`httponly` means `document.cookie` cannot read the session cookie — a successful XSS injection
still cannot exfiltrate it, because JavaScript was never given access in the first place. That is
why the frontend has no code that reads or stores a session identifier: there is nothing for it to
read. `secure` restricts the cookie to HTTPS once your deployment has it, so a network observer on
an insecure connection never sees it either. `samesite: Lax` is the CSRF-relevant one — it tells
the browser to withhold the cookie on a cross-site *state-changing* request (a `POST` from another
origin's page, say), while still attaching it to an ordinary top-level navigation. FS06.2's CSRF
token is what closes the gap `Lax` deliberately leaves open: a same-site `GET` still carries the
cookie, so `Lax` alone cannot stop a same-site form from riding on it.

**Predict, then check:** delete `credentials: 'include'` from `getCurrentUser` and reload a
signed-in session. The request still fires, the server still runs, and the response is still
`200` — but `/api/me` now answers as though no one is signed in, because the browser never
attached the cookie to a request that did not ask for it. No console error, no network failure
banner, nothing that looks like a bug from the client's side. It is the single most common way
this lesson's state machine gets debugged in the wrong file: the fix is not in `AuthState`, it is
in the one line every request in this lesson depends on.

This is not specific to `getCurrentUser`. Every mutation your API client module makes — creating
an issue, marking one done, deleting it — needs the same option for the same reason, and a
per-request setting is exactly what FS04.3's client boundary is for: put `credentials: 'include'`
once, in the shared request helper every operation calls through, and no individual function gets
a chance to forget it.

## Put behavior where a user can see it

Use semantic HTML so both people and tests can find it by role and label. A named form, a real
button, and an alert for a failed request communicate the application contract more clearly than
a test-only attribute.

```tsx
<form aria-label="Login" onSubmit={submit}>
  <label>Email <input name="email" type="email" /></label>
  <label>Password <input name="password" type="password" /></label>
  <button type="submit">Sign in</button>
  {error && <p role="alert">{error}</p>}
</form>
```

## Try it

Implement the state model above in your shell. Simulate anonymous, authenticated, denied, and
network-failure responses at the API boundary. Refresh an authenticated route, then expire the
session or sign out in another tab. The next protected request must recover rather than leaving
old private content on screen.

```text
unknown session       → loading message, no premature redirect
401 from current user → login route
403 from issue API    → access-denied screen, not login
network failure       → visible retryable error
```

## Translate status codes in one place

Four different statuses need four different reactions, and if every component decides for
itself, the application ends up with three incompatible ideas of what "not allowed" means:

```ts
type AuthOutcome =
  | { kind: 'sign-in-required' }   // 401
  | { kind: 'forbidden' }          // 403
  | { kind: 'stale-session' }      // 419
  | { kind: 'unreachable' };       // network error, or anything else

function classify(error: unknown): AuthOutcome {
  if (error instanceof ApiError) {
    if (error.status === 401) return { kind: 'sign-in-required' };
    if (error.status === 403) return { kind: 'forbidden' };
    if (error.status === 419) return { kind: 'stale-session' };
  }
  return { kind: 'unreachable' };
}
```

**Predict, then check:** two route screens each handle failure differently — one redirects on
any non-2xx, the other only reads `error.message`. Force a 403 through both. One sends a
signed-in user to `/login`, which does nothing useful; the other shows "something went wrong,"
which is technically true and useless. Route every failure through `classify` instead, once, and
every screen answers the same question the same way.

A 401 means the session is absent or expired — login recovery genuinely helps. A 403 means the
user authenticated fine and is still refused; sending them to log in again throws away the one
fact that would actually help them. A 419 means CSRF proof no longer matches, so the fix is fresh
proof, not a redirect at all. Collapsing any of these into the others isn't simplification — it's
information the next screen now has to guess its way around.

The same discipline applies to a login form's own errors, which have different origins: an empty
required field is a local check with no request involved; invalid credentials are a deliberately
generic server response, so the endpoint never reveals whether an account exists; a transport
failure means sign-in could not even complete. Three different next actions. One generic error
paragraph collapses them into a screen that tells the user nothing about what to do next.

## Logout is a request, not a local reset

```tsx
async function logout() {
  const response = await fetch('/api/logout', { method: 'POST', credentials: 'include' });
  if (!response.ok) {
    setBanner('Could not sign you out. Try again.');
    return;                                     // do not clear local state yet
  }
  setAuth({ status: 'anonymous' });
  navigate('/login', { replace: true });
}
```

**Predict, then check:** sign in on two tabs, then log out in one. What does the *other* tab show
right now, and what does it show after you click something in it? The honest answer is:
unchanged, until its next request. A `setAuth` call in one tab cannot reach into another tab's
memory. The second tab learns the truth exactly the way the server always tells it — its next
request gets a 401, and `classify` sends that 401 through the same recovery path every other 401
gets. Cross-tab messaging can make this feel faster; it is never proof the server agrees, which
is why the request above is what actually invalidates the session.

That's also why `logout` checks `response.ok` before touching any state. Clearing local identity
after a *failed* logout tells the user they're signed out while the server still has a live
session — the same lie FS06.2 spent a whole lesson closing on the backend. Don't reopen it here.

## Don't let a slow response win a race it already lost

The initial session check and a fast logout click can both be in flight at once, and nothing
stops the slow "you're signed in" answer from arriving *after* the logout has already set
`auth` to anonymous — silently reauthenticating someone who just signed out:

```tsx
useEffect(() => {
  const controller = new AbortController();

  getCurrentUser(controller.signal)
    .then((user) => setAuth(user ? { status: 'authenticated', user } : { status: 'anonymous' }))
    .catch((error: unknown) => {
      if (controller.signal.aborted) return;
      setAuth({ status: 'failed', message: classify(error).kind });
    });

  return () => controller.abort();
}, []);
```

This is FS04.1's abort pattern, unchanged — a current-user check is exactly as capable of
arriving late as an issue fetch is, and it deserves the same protection. **Predict, then check:**
remove the `AbortController`, then click logout the instant the page loads, before the initial
check has had a chance to resolve. What does `auth` end up holding? Without the guard, whichever
answer resolves last wins, regardless of which one is actually true — the shell that started
this request no longer exists in the sense that matters, and cleanup is what says so.

## What a hard refresh actually does

It is worth tracing the whole sequence once, the way B00 asked you to trace a form submit, because
"the app remembers who I am" is doing a lot of invisible work in that sentence.

```text
1. Browser requests the document              — new HTML, no React yet
2. Bundle downloads and executes               — React mounts, AuthState = 'loading'
3. Effect fires getCurrentUser()                — a fresh fetch, cookie attached automatically
4. DALT reads the session cookie's id           — looks the session up, resolves (or fails to)
5. Response arrives                             — 200 with a user, or 401, or a network failure
6. AuthState updates                            — 'authenticated' | 'anonymous' | 'failed'
```

Nothing about identity survived the refresh in the sense people usually mean it. The React tree
was destroyed and rebuilt from nothing at step 2; every value in it, including a signed-in user's
name and id, is gone. What actually persisted was the cookie sitting in the browser's cookie jar,
attached to step 3 by the browser without any code asking it to, and the server session it points
at, unaffected by the page ever having reloaded. "The app remembers me" is really "the cookie
survived and the server still recognizes it" — two facts that live entirely outside React, checked
again from scratch on every single page load.

This is why `AuthState` cannot start at `'anonymous'` and flip to `'authenticated'` once the
response arrives: for the whole span of steps 2 through 5, a real signed-in user is
indistinguishable from a real anonymous one if you only look at what React currently knows. The
`'loading'` state exists to name that span honestly instead of guessing through it.

## Common mistakes

### Treating a request in flight as proof that the visitor is anonymous

An unresolved current-user request is not the same fact as "no one is signed in." Redirecting before the answer arrives races the request and misfires for every returning user with a slow connection.

### Storing credentials or a long-lived token in browser storage by habit

The session cookie already handles authentication, with browser-enforced security attributes behind it. A second, hand-rolled identity store in `localStorage` adds a copy of the truth with none of those protections.

### Redirecting 403 to login and hiding a real authorization problem

A signed-in user sent back to login signs in again, arrives at the same denied screen, and concludes the application is broken. 401 means "sign in would help." 403 means it wouldn't.

### Calling hidden controls "security"

A control that doesn't render is a UX improvement, not a boundary. The check that matters runs on the server, against the request, regardless of what the UI chose to show.

### Replacing a server error with a blank screen

A caught error that renders nothing gives a person no information and no recovery action. It also looks, to anyone testing casually, exactly like success.

### Testing implementation details instead of a label, navigation, or enabled action

A test coupled to internal state or structure breaks on every refactor and stays green through real regressions — the opposite of what a test is for.

### Dropping `credentials: 'include'` and debugging the wrong layer

Every request in this lesson depends on the browser attaching the session cookie, which it only does when the request explicitly asks for it. Without that option every one of these requests looks identical to an anonymous one — a 200 with no error, which reads exactly like a state-machine bug and wastes far more time in `AuthState` than in the one line actually responsible.

## When this goes wrong

If users flash through login before their session appears, locate the branch that conflates
loading with anonymous. If every request is anonymous, inspect the actual request credentials and
cookie policy before changing React state. If an old screen remains after logout, check that
`logout` above actually waits for `response.ok` before clearing state — a local `setAuth`
call that runs regardless of the server's answer is exactly the false confidence "Logout is a
request, not a local reset" warned about.

## Exercise

### Goal

Make authentication state a coherent, server-derived frontend experience.

### Starting state

Routes and protected DALT API endpoints exist.

### Requirements

- Add loading, anonymous, authenticated, and failed states.
- Build an authenticated shell.
- Add login recovery for 401, and a distinct access-denied path for 403.
- Add authorization-aware controls, derived from server data.
- Keep server enforcement unchanged — this lesson is presentation only.

### Constraints

- No credential, token, or session identifier in `localStorage` or a query string.
- No redirect-to-login on 403.
- No component may decide authorization on its own; every hidden control's underlying rule must still hold at the API.

### Verification

**Mode: tool-run — browser behavior plus `npm run typecheck` and `npm run lint`.** The platform does not grade this exercise; the API response and visible recovery are its evidence.

Refresh a protected route, test an expired session, inspect the network response for 401 and 403, and demonstrate that a direct forbidden API mutation still fails.

### Hints

<details>
<summary>Hint 1 — the first distinction to get right</summary>

Make unknown distinct from anonymous before anything else. Every other state decision depends on this one being correct.
</details>

<details>
<summary>Hint 2 — build order</summary>

Implement one protected route completely before adding the rest. A single working example is easier to generalize from than three unfinished ones built in parallel.
</details>

<details>
<summary>Hint 3 — where status translation belongs</summary>

Keep network-status translation in one client module rather than scattering status checks across components. If every component invents its own rule for 401, you get incompatible recovery experiences across the app.
</details>

<details>
<summary>Reference explanation — read after an honest attempt</summary>

The working shape is the `AuthState` discriminated union in "Make state explicit," the status-code recovery table in "Session lifecycle and recovery details" (401 → login, 403 → explain, network failure → retry), and the `canEdit`-style derived boolean in "Keep authority on the server." The proof isn't that the happy path renders correctly — it's that a direct, forged mutation against the API still fails regardless of what the UI shows or hides.
</details>

## In the project

B07 gains a shell whose navigation tells the person who they are signed in as and whose route
screens react honestly to server outcomes. The next lesson tests these paths through accessible
behavior. It does not replace the B06 backend tests; it complements them.

## Closed-book checkpoint

Close the lesson first.

1. Why is frontend auth state a cache rather than a security boundary?
2. What state must exist before an initial current-user request finishes?
3. Why should 401 and 403 lead to different recovery behavior?
4. What is one thing a hidden Edit button cannot prove?
5. Which session event can make a previously rendered client state stale?
6. `httponly`, `secure`, and `samesite: Lax` each rule out a different attack on the session cookie. What does each one actually stop, and which one is CSRF's job to close the rest of the way?

<details>
<summary>Reveal comparison answers</summary>

1. It's a copy of a fact the server decided, held in a browser the user fully controls. The server re-derives and re-checks identity on every request regardless of what the client believes.
2. A "loading" or "unknown" state — never a default that reads as anonymous. Rendering "logged out" before the request resolves races the answer and misfires for a valid, slow-to-confirm session.
3. 401 means no identity is present at all, and signing in would resolve it. 403 means a known, real identity was denied by policy, and signing in again changes nothing. Collapsing both into one recovery path sends at least one of them somewhere unhelpful.
4. That the action is actually forbidden server-side. A hidden button only proves the UI chose not to show it — a direct request bypasses it completely.
5. Logout in another tab, or the session simply expiring server-side. The client's rendered state doesn't know either happened until its next request meets a 401.
6. `httponly` stops JavaScript — including injected XSS payloads — from ever reading the cookie's value. `secure` stops a network observer on a non-HTTPS hop from seeing it. `samesite: Lax` withholds the cookie from most cross-site requests but still allows it on a plain top-level navigation and on a same-site request — so a same-site form submission still carries it, and closing that remaining gap is exactly what FS06.2's CSRF token is for.
</details>

## Resources

### Read

- [MDN: HTTP 401](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/401)
- [MDN: HTTP 403](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/403)
- [React: conditional rendering](https://react.dev/learn/conditional-rendering)

### Go deeper

- [OWASP: Authorization Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html)

## You are done when

- [ ] Loading is distinct from anonymous state.
- [ ] The shell derives identity from a safe server response.
- [ ] 401, 403, and network failure have intentional UI outcomes.
- [ ] UI controls improve usability without claiming to authorize.
- [ ] Logout and session expiry do not leave private UI presented as current.
- [ ] `npm run typecheck` and `npm run lint` pass.

## Maintainer source record

Source dossier: `docs/dalt-fullstack/sources/FSO_PART_07.md`.

Official sources: React conditional rendering; MDN 401 and 403 references; OWASP authorization guidance, linked above.

Versions: React 19.2.3; TypeScript 5.9.3.

Consulted: 2026-08-15.

Curriculum authority: `CURRICULUM.md` §18, FS07.2; `PROJECT_BLUEPRINT.md` §§40, 42.

Follow-up pass: 2026-08-19 — fixed a stray double-period typo in this record's curriculum-authority citation; restructured Exercise into LESSON_STANDARD.md §97's subsections with a hint ladder and reference explanation; split Common mistakes into explained subsections; added a Closed-book checkpoint answer reveal. Content pass (owner-approved): replaced four generic, near-code-free essay sections ("Session lifecycle and recovery details," "Design review before integration," "Evidence and operational choices," "A deliberate final pass" — collectively the least code-dense ~1,600 words in the course) with three tighter, code-grounded sections covering the same load-bearing ideas — one-place status-code translation, logout as a real request rather than a local reset, and the auth-check-versus-logout race — each with a predict-then-verify moment matching FS04.1/FS07.3's style. Extended `getCurrentUser` with an optional `AbortSignal` parameter to keep the new race-condition example consistent with its earlier definition in this same lesson. Removed a redundant, weaker duplicate `logout()` snippet from "When this goes wrong" in favour of pointing back at the fuller version.

Follow-up pass: 2026-08-20 — `FullstackStandardTest`'s depth-regression check failed after the tighter-prose pass above: replacing code-light essay sections with code-grounded ones is a real improvement, but the course's own word-count measure counts a code line as one word regardless of length, so the swap read as a large drop in depth (2,418 words against a 3,297-word floor set by Part 05). Deepened rather than reverted: added "Why the cookie carries identity, not a header" (grounds `credentials: 'include'` and every fetch's silent dependence on it in DALT's actual `config/session.php` — `httponly`, `secure`, `samesite: Lax` — and what CSRF still has to cover) and "What a hard refresh actually does" (a full six-step request trace answering why `AuthState` cannot start at `'anonymous'`), one more Common mistakes entry on dropping `credentials: 'include'`, and a sixth checkpoint question on the cookie attributes. Re-verified against `framework/Core/Session.php` and `config/session.php` directly. `php artisan test` confirms the regression check now passes.
