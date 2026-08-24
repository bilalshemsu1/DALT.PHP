# FS09.3 — Configuration, production builds, and error boundaries

Lesson ID: FS09.3
Lesson format: Concise theory
Part: 09 — Advanced React and tooling
Status: Published
Estimated effort: 30–40 minutes
Difficulty: Applied
Prerequisites: FS09.2
Last reviewed: 2026-08-23

We will look at what the production build actually produces, decide which configuration values may be inside it, and contain a render failure so one broken section does not take the page with it.

> **Helpful background:** [Feature boundaries and dependency direction](/learn/lessons/72-fs09-2-feature-boundaries)

## What we will learn

- read client configuration as public build-time text, and fail loudly when it is missing;
- see for yourself which values reach the bundle and which do not;
- contain a render failure with an error boundary, and know what it cannot catch.

## Type checking and building are different evidence

`npm run typecheck` answers "do the types agree?". `npm run build` answers "can this be turned into files a browser can load?". A project can pass one and fail the other, so run both before believing a change is finished. Neither of them, as FS09.2 showed, has an opinion about architecture.

## Client configuration is public text

Vite loads `.env`, `.env.local`, and the mode-specific `.env.[mode]` files, and exposes **only** the variables prefixed with `VITE_` to client code. Everything else stays on the machine that ran the build:

```ini
VITE_API_BASE_URL=/api
APP_SESSION_SECRET=never-shipped-to-a-browser
```

The prefix is a reminder, not a security boundary: what makes a value safe is that it is not a secret. Anything in the bundle is readable by anyone who loads the page.

Read configuration once, at a named edge, and fail immediately if it is missing:

```ts
export function readConfig(env: Record<string, unknown>): AppConfig {
  const apiBaseUrl = env.VITE_API_BASE_URL;

  if (typeof apiBaseUrl !== 'string' || apiBaseUrl === '') {
    throw new Error('VITE_API_BASE_URL is missing. Set it in .env before starting the app.');
  }

  return { apiBaseUrl };
}

export const appConfig = readConfig(import.meta.env);
```

Reading it at startup turns a missing value into one clear error at launch instead of a confusing 404 during the first request that needed it.

## An error boundary is a containment wall

A component that throws while rendering unmounts its whole tree by default — one malformed chart and the page is blank. An error boundary catches that and renders something else in its place:

```tsx
export class ErrorBoundary extends Component<Props, State> {
  state: State = { error: null };

  static getDerivedStateFromError(error: Error): State {
    return { error };
  }

  render(): ReactNode {
    if (this.state.error !== null) {
      return (
        <div role="alert">
          <p>{this.props.section} is unavailable.</p>
          <button onClick={() => this.setState({ error: null })}>Try again</button>
        </div>
      );
    }

    return this.props.children;
  }
}
```

This is one of the few places a class component is still required: `getDerivedStateFromError` and `componentDidCatch` have no hook equivalent.

Place boundaries where recovery is meaningful. One at the root turns every failure into the same blank apology. One per independently useful region — a chart, a side panel, a route — keeps the rest of the screen usable, which is the entire point.

## What a boundary does not catch

Errors thrown from event handlers, timers, or rejected promises never pass through React's rendering, so React has nothing to intercept. They go to the global error handler instead:

```tsx
<button onClick={() => { throw new Error('handler failed'); }}>Save</button>
```

Clicking that does **not** show the fallback. Failures in a handler are handled where they happen — a caught error, a message, a failed-mutation state from FS08.3 — not by a boundary.

## Try it

**Workspace:** continue in the Part 09 lab, or copy a clean starter:

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/frontend-architecture-lab/starter \
  .dalt/workspace/fs09-frontend-architecture
cd .dalt/workspace/fs09-frontend-architecture
npm ci
```

**Starting state:** `.env.test` and `.env.production` define one prefixed value and one unprefixed one. `src/app/config.ts` reads the configuration; `src/app/ErrorBoundary.tsx` contains failures.

```bash
npm run test:failures
npm run build
grep -c VITE_API_BASE_URL dist/assets/*.js
grep -c never-shipped-to-a-browser dist/assets/*.js
```

**Expected result:** five tests pass, the build succeeds, the first `grep` prints `1`, and the second prints `0`. The prefixed value is inlined into the bundle; the unprefixed one from the same file is not there at all. (`grep -c` exits non-zero when it counts nothing, which is why the `0` is the answer rather than an error.)

The tests prove that missing configuration throws a named error, that `import.meta.env.APP_SESSION_SECRET` is `undefined` in the client, that a throwing chart shows the fallback while its sibling keeps rendering, that "Try again" recovers once the cause is gone, and that an error thrown in a click handler reaches the global handler instead of the boundary.

**Reset:** delete the workspace copy. Part 10 leaves the browser.

## What to notice

The two `grep` results are the lesson in two lines. Nobody has to be told that a bundle is public; it is visible.

The recovery test also shows what "Try again" really is: clearing the boundary's own error state so it renders its children again. If the cause is still there, it throws again immediately — a boundary reset is not a repair.

## Common mistakes

- Putting an API key or a session secret in a `VITE_`-prefixed value.
- Reading configuration lazily, so a missing value appears as a strange runtime failure.
- One error boundary at the root, which is barely better than none.
- Expecting a boundary to catch a failure from an event handler or a rejected promise.

## Check your understanding

1. What does `npm run build` prove that `npm run typecheck` does not?
2. Why is the `VITE_` prefix not a security feature?
3. Where should error boundaries go, and why not only at the root?
4. Name two kinds of failure an error boundary cannot catch.

<details><summary>Check your answers</summary>

1. That the code can actually be bundled into loadable files; types agreeing does not guarantee that.
2. Because everything in the bundle is public. The prefix only marks which values were deliberately exposed.
3. Around each independently useful region, so a failure removes one section instead of the whole page.
4. An error thrown from an event handler and one from a rejected promise or timer — neither passes through rendering.
</details>

## Next

Next we will leave the browser and put the application inside a container.

<details><summary>Maintainer source record</summary>

- Source dossier: Full Stack Open Part 7 research notes, sections 3–8 and 49–58.
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: Vite "Env Variables and Modes" and build guide; React "Error Boundary", `getDerivedStateFromError`, and `componentDidCatch` references.
- Versions: Vite 8.0.12; React 19.2.3; Vitest 4.0.18; React Testing Library 16.3.2; TypeScript 5.9.3.
- Consulted: 2026-08-23.
- Curriculum authority: DALT Fullstack theory curriculum, Batch 10, FS09.3.
- DALT files inspected: `frontend-architecture-lab`, the Part 09 track manifest, and the former FS09.2 page.
- Extracted material: the typecheck-versus-build distinction, the public-configuration rule, and the boundary-granularity guidance from the former FS09.2.
- Verified in the lab: the built bundle contains `VITE_API_BASE_URL:"/api"` and no occurrence of `never-shipped-to-a-browser`; a click-handler error reaches the window `error` event and leaves the boundary's fallback unrendered.
</details>
