# FS04.1 — Fetching data and synchronizing with Effects

Lesson ID: FS04.1
Lesson format: Concise theory
Part: 04 — React and the server
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Foundation
Prerequisites: FS03.8
Last reviewed: 2026-08-22

We will load issues after rendering and use an Effect only because the component must synchronize with an external server.

> **Helpful background:** [Responsive, semantic, and accessible application UI](/learn/lessons/32-fs03-4-tailwind-and-accessible-ui)

## What we will learn

- distinguish rendering, events, and Effects;
- fetch after a component commits instead of during rendering;
- treat HTTP success and runtime data shape as separate checks.

## An Effect synchronizes an external system

React component code has three useful homes:

```text
rendering       calculate JSX from current props and state
event handler   perform work caused by one user action
Effect          synchronize with something external because this UI is present
```

Loading the current server list when an issue screen appears is synchronization. It cannot happen during rendering because `fetch` changes the outside world and finishes later. It is not necessarily caused by a click, so an event handler alone is insufficient.

```tsx
import { useEffect, useState } from 'react';

function App() {
  const [issues, setIssues] = useState<readonly Issue[] | null>(null);

  useEffect(() => {
    // Synchronize this mounted screen with the issue endpoint.
  }, []);
}
```

Effects run after React commits the UI. An empty dependency array means this synchronization reads no reactive prop or state value, so it does not need to restart when those values change. It is not a magic “run once” instruction: development Strict Mode deliberately performs setup, cleanup, and setup again to reveal unsafe synchronization.

## Keep the Effect callback synchronous

An Effect may return a cleanup function. An `async` function always returns a Promise, so do not make the Effect callback itself async. Define and call an inner function:

```tsx
useEffect(() => {
  async function loadIssues() {
    const response = await fetch('http://127.0.0.1:8034/api/issues');
    if (!response.ok) {
      throw new Error(`Issue request failed with ${response.status}.`);
    }

    const body: unknown = await response.json();
    setIssues(parseIssues(body));
  }

  void loadIssues();
}, []);
```

`fetch` resolves when an HTTP response arrives, including a 404 or 500. `response.ok` checks whether its status is in the successful 200–299 range. `response.json()` proves only valid JSON syntax, so its result enters as `unknown` and crosses the runtime parser from FS02.6 before becoming trusted `Issue[]` state.

## Dependencies describe what synchronization reads

Suppose the endpoint depends on a project ID:

```tsx
function IssueScreen({ projectId }: { projectId: string }) {
  useEffect(() => {
    void loadProjectIssues(projectId);
  }, [projectId]);
}
```

When `projectId` changes, the old synchronization no longer represents the screen and React starts a new one. Dependencies are determined by reactive values the Effect reads; omitting one hides a real relationship and can leave stale data.

Do not use an Effect to calculate `visibleIssues` from `issues` and `showDone`. There is no external system in that calculation; derive it during rendering as we did in FS03.5.

## A minimal parser for this boundary

The experiment needs a list parser. Keep it explicit and narrow in `src/parseIssues.ts`:

```ts
import type { Issue, IssueStatus, Priority } from './issue';

const statuses: readonly IssueStatus[] = ['todo', 'in_progress', 'done'];
const priorities: readonly Priority[] = ['low', 'medium', 'high'];

function parseIssue(value: unknown): Issue {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) {
    throw new Error('Issue must be an object.');
  }
  const record = value as Record<string, unknown>;
  if (typeof record.id !== 'string' || typeof record.title !== 'string'
    || !statuses.includes(record.status as IssueStatus)
    || !priorities.includes(record.priority as Priority)) {
    throw new Error('Issue fields are invalid.');
  }
  return {
    id: record.id,
    title: record.title,
    status: record.status as IssueStatus,
    priority: record.priority as Priority,
  };
}

export function parseIssues(value: unknown): Issue[] {
  if (!Array.isArray(value)) throw new Error('Issue list must be an array.');
  return value.map(parseIssue);
}
```

The narrow assertions occur only after membership checks. FS04.4 will place this boundary behind an API function; for now it remains visible so we can see every step.

## Try it

**Workspace:** `.dalt/workspace/fs04-react-server`

**Starting state:** copy a fresh React starter and the course-owned resettable API fixture.

```bash
mkdir -p .dalt/workspace/fs04-react-server
cp -R .dalt/course/fullstack/react-foundations-lab/starter/. .dalt/workspace/fs04-react-server/
cp .dalt/course/fullstack/react-server-fixture/fixture-api.php .dalt/workspace/fs04-react-server/
cd .dalt/workspace/fs04-react-server
npm ci
php -S 127.0.0.1:8034 fixture-api.php
```

Leave the fixture running. In a second terminal, create `src/parseIssues.ts` with the parser above. Replace `App.tsx` with the state and Effect pattern, import `Issue`, `IssueList`, and `parseIssues`, and render:

```tsx
return (
  <main>
    <h1>Server issues</h1>
    {issues === null ? <p>Loading issues…</p> : <IssueList issues={issues} />}
  </main>
);
```

Add `.catch((error: unknown) => console.error(error))` to the `loadIssues()` call for this first observation. Run:

```bash
npm run typecheck
npm run dev
```

Open `http://localhost:5174` with DevTools Network visible. The loading paragraph is committed first; then `GET /api/issues` returns 200 and three parsed issues replace it.

**Expected result:** typecheck passes, Network shows a real GET, and server data enters the component only after HTTP and runtime-shape checks.

**Reset:** keep the workspace and fixture for FS04.2–FS04.4, or delete `.dalt/workspace/fs04-react-server`.

## What to notice

The Effect does not make derived state easier. It exists because a server lies outside React. Rendering remains a calculation, while the Effect synchronizes and then requests a new render with trusted data.

## Check your understanding

1. Why is fetching in the component body unsafe?
2. Why is an Effect callback not declared `async`?
3. What does `response.ok` establish?
4. Why does parsed JSON still begin as `unknown`?

<details><summary>Check your answers</summary>

1. Rendering may repeat and must remain free of side effects.
2. React expects either no return value or a cleanup function, not a Promise.
3. The HTTP status is in the 200–299 range.
4. Valid JSON syntax does not establish the application's domain shape.
</details>

## Next

The happy path loads; next we will model loading, empty, failure, cleanup, and competing responses without letting stale work win.

<details><summary>Maintainer source record</summary>

- Source dossier: `REACT_DOCS.md`; `FSO_PART_02.md`.
- Official sources: React Learn, *Synchronizing with Effects* and *You Might Not Need an Effect*; MDN `fetch`, `Response.ok`, and JSON response behavior.
- Versions: React 19.2.3; TypeScript 5.9.3; PHP 8.4 fixture.
- Consulted: 2026-08-22.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 5, FS04.1.
- DALT files inspected: Part 04 fixture API, React starter issue model, Vite origin, and executable lifecycle test.
- Reused material: Effect timing, dependency, HTTP-status, and runtime-boundary material from former FS04.1.
</details>
