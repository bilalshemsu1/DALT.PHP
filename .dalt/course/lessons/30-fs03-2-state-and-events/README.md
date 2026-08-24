# FS03.4 — State, snapshots, and events

Lesson ID: FS03.4
Lesson format: Concise theory
Part: 03 — React foundations
Status: Published
Estimated effort: 30–40 minutes
Difficulty: Foundation
Prerequisites: FS03.3
Last reviewed: 2026-08-22

We will make the interface respond to a user while keeping React in charge of the screen.

> **Helpful background:** [Lists, conditional UI, and stable keys](/learn/lessons/60-fs03-3-lists-conditionals-and-keys)

## What we will learn

- store component memory with `useState`;
- pass functions as event handlers;
- reason about state snapshots and updater functions.

## State gives a component memory

An ordinary local variable is recreated on every render, and changing it does not request another render. The `useState` Hook gives React a value to preserve and a setter that schedules fresh output:

```tsx
import { useState } from 'react';

export function IssueCounter() {
  const [count, setCount] = useState(0);

  return (
    <button type="button" onClick={() => setCount(count + 1)}>
      Reviewed {count}
    </button>
  );
}
```

`count` is the value for this render. `setCount` requests another render. We pass a function to `onClick`; writing `onClick={setCount(count + 1)}` would call it during rendering.

Hooks must be called at the top level of a component or another Hook—not inside a condition, loop, or event handler. React relies on stable call order to associate stored values with Hook calls.

## Each render sees a snapshot

Calling a setter does not rewrite the variable captured by the current handler:

```tsx
function handleReview() {
  setCount(count + 1);
  console.log(count);
}
```

If the screen showed zero, the log still prints zero. React queues the next render; the handler continues with its existing snapshot. The new render receives a new snapshot.

When the next value depends on the previous queued value, pass an updater:

```tsx
setCount((current) => current + 1);
```

Three `setCount(count + 1)` calls all request the same snapshot value plus one. Three updater calls receive the evolving queued value and add three.

## Events carry browser information

Named handlers keep non-trivial behavior readable, and TypeScript can describe the event:

```tsx
import type { ChangeEvent } from 'react';

function handleFilterChange(event: ChangeEvent<HTMLSelectElement>) {
  setStatusFilter(event.target.value);
}
```

State describes what should be visible. An event handler requests new state. The next render calculates matching UI. We do not query a list element and hide rows by hand.

## Try it

**Workspace:** continue in `.dalt/workspace/fs03-react-foundations`.

**Starting state:** the page renders the issue list created in FS03.3.

Import `useState`, then add inside `App`:

```tsx
const [showDone, setShowDone] = useState(true);
const visibleIssues = showDone
  ? issues
  : issues.filter((issue) => issue.status !== 'done');
```

Replace the old `IssueList` call with:

```tsx
<button type="button" onClick={() => setShowDone((current) => !current)}>
  {showDone ? 'Hide completed' : 'Show completed'}
</button>
<IssueList issues={visibleIssues} />
```

Run:

```bash
npm run typecheck
npm run dev
```

Click **Hide completed**. Issue 3 disappears and the label becomes **Show completed**. Click again and it returns.

**Expected result:** one event queues a boolean update; the next render derives both the button label and visible list from that snapshot.

**Reset:** keep the workspace for FS03.5, or delete it and repeat the preceding experiments.

## What to notice

We store only `showDone`. `visibleIssues` is calculated during rendering, so it cannot become stale independently. The updater form names the real dependency: the next boolean comes from the previous boolean.

## Check your understanding

1. Why does changing an ordinary local variable not update the screen?
2. Why must `onClick` receive a function?
3. Why can a log after a setter show the old value?
4. When should we use an updater?

<details><summary>Check your answers</summary>

1. React neither preserves it nor receives a request to render.
2. React should call it when the event occurs, not during render.
3. The handler belongs to the current render's snapshot.
4. When the next value depends on a previous queued value.
</details>

## Next

State works; next we will decide what state should exist and which component should own it.

<details><summary>Maintainer source record</summary>

- Source dossier: React documentation research notes; Full Stack Open Part 1 research notes.
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: React Learn, *Responding to Events*, *State: A Component's Memory*, *State as a Snapshot*, and *Queueing a Series of State Updates*.
- Versions: React 19.2.3; TypeScript 5.9.3.
- Consulted: 2026-08-22.
- Curriculum authority: DALT Fullstack theory curriculum, Batch 4, FS03.4.
- DALT files inspected: React foundations starter `App.tsx`, `IssueList.tsx`, and strict TypeScript configuration.
- Reused material: former FS03.2 event, Hook-order, state-snapshot, and batching explanations.
</details>
