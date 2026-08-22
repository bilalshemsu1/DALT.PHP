# FS08.4 — Context, reducers, and when a client store earns its place

Lesson ID: FS08.4
Lesson format: Concise theory
Part: 08 — Server and application state
Status: Published
Estimated effort: 30–40 minutes
Difficulty: Applied
Prerequisites: FS08.3
Last reviewed: 2026-08-23

We will choose between local state, Context, a reducer, and a client store using a measured cost rather than a preference.

> **Helpful background:** [Classify local, URL, session, and server state](/learn/lessons/45-fs08-1-client-state-versus-server-state)

## What we will learn

- keep client state with its closest sensible owner;
- write a reducer when a screen's transitions have a vocabulary;
- see what Context costs, and what a selector-based store buys.

## Start with the closest owner

The first question is never "which library". It is "who is the closest component that can own this?" Most client state answers that with the component itself, and a lot of the rest answers it with the parent that already renders both readers.

Lift state only as far as the nearest common ancestor. Lifting further does not make an application more organised; it makes every intermediate component a courier for a value it does not use.

## A reducer is for a vocabulary, not for size

Reach for `useReducer` when the transitions have names and rules that keep repeating themselves — not merely when there are several fields:

```ts
export function filterReducer(state: FilterState, action: FilterAction): FilterState {
  switch (action.type) {
    case 'status-changed':
      return { ...state, status: action.status, page: 1 };
    case 'query-changed':
      return { ...state, query: action.query, page: 1 };
    case 'page-changed':
      return { ...state, page: action.page };
  }
}
```

The rule "narrowing the results returns to page one" now lives in one place instead of in three handlers. The payoff is not tidiness. It is that a reducer is a pure function of two values, so it can be tested with no rendering at all:

```ts
const searched = filterReducer(
  { ...initialFilterState, page: 4 },
  { type: 'query-changed', query: 'timeout' },
);

expect(searched).toEqual({ status: 'open', query: 'timeout', page: 1 });
```

Because the switch is exhaustive over a discriminated union, adding a fourth action makes TypeScript report the missing case — the technique from FS02.4, doing real work.

## Context is a delivery mechanism, not a store

Context solves prop drilling: it lets a subtree read a value without threading it through every layer. What it does not do is limit who re-renders. Every consumer re-renders when the context value changes, whichever part of it changed:

```tsx
const value = useMemo(() => ({ density, sidebarOpen, setDensity, toggleSidebar }), [
  density,
  sidebarOpen,
]);
```

`useMemo` here stops a *new object identity* on every parent render. It does not stop the re-render caused by `sidebarOpen` actually changing — a consumer reading only `density` still re-renders. For a small subtree that is completely fine. For a value read by dozens of components across a large screen, it is the cost worth measuring.

## A store earns its place with selectors

Zustand keeps state outside the React tree, so components subscribe to the part they read:

```ts
export const useWorkspaceStore = create<WorkspaceState>()((set) => ({
  density: 'comfortable',
  sidebarOpen: true,
  setDensity: (density) => set({ density }),
  toggleSidebar: () => set((state) => ({ sidebarOpen: !state.sidebarOpen })),
}));
```

```tsx
const density = useWorkspaceStore((state) => state.density);
```

That selector is the whole point. `useWorkspaceStore((state) => state)` subscribes to everything and gives up the only advantage the store had. When a component genuinely needs two fields, select them together with `useShallow` so an unrelated change does not re-render it:

```tsx
import { useShallow } from 'zustand/react/shallow';

const { density, sidebarOpen } = useWorkspaceStore(
  useShallow((state) => ({ density: state.density, sidebarOpen: state.sidebarOpen })),
);
```

Two boundaries come with it. A store defined in a module is global: it outlives every component, so tests must reset it explicitly. And no issue, comment, or current-user record belongs in it — those have an address in the query cache and a server that owns them. A store holding server records is FS08.1's drift problem with extra steps.

## The order to try

```text
1. useState in the component that needs it
2. lift to the nearest common ancestor
3. useReducer when the transitions have a vocabulary
4. Context to deliver a subtree contract without prop drilling
5. a store when Context's re-render cost is measured and real
```

Most applications stop at step two or four. Skipping to five is not a shortcut; it is a decision made without evidence.

## Try it

**Workspace:** continue in the Part 08 lab, or copy a clean starter:

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/state-architecture-lab/starter \
  .dalt/workspace/fs08-state-architecture
cd .dalt/workspace/fs08-state-architecture
npm ci
```

**Starting state:** `src/filterReducer.ts` holds the pure reducer, `src/workspaceStore.ts` a small Zustand store, and `src/ClientStore.tsx` two Context consumers and two store consumers that count their own renders.

```bash
npm run test:store
npm run typecheck
```

**Expected result:** four tests pass. They prove the reducer's transitions without rendering anything, that toggling the sidebar re-renders *both* Context consumers, that the same toggle re-renders only the store subscriber that reads `sidebarOpen` while a `useShallow` consumer stays at one render, and that a module-level store keeps its value after its component unmounts.

Now change `useWorkspaceStore((state) => state.density)` to `useWorkspaceStore((state) => state).density`. The third test fails: the render counts become identical to Context's.

**Reset:** delete the workspace copy. Part 09 uses a different lab.

## What to notice

The comparison is a render count, not an opinion. Context re-rendered a component that reads a field nobody touched; the selector did not. That is the actual difference between the two tools, and it is worth nothing until a screen is large enough for it to matter.

Notice also what neither tool changed: none of these values came from the server.

## Common mistakes

- Adding a store because passing one prop through two components felt tedious.
- Selecting the whole store, which removes the only reason to have one.
- Putting fetched records in a client store, giving one fact two owners again.
- Leaving a module-level store dirty between tests, so failures depend on test order.

## Check your understanding

1. When is `useReducer` a better answer than several `useState` calls?
2. What does `useMemo` on a context value prevent, and what does it not prevent?
3. Why is `useWorkspaceStore((state) => state)` self-defeating?
4. Why must a module-level store be reset between tests?

<details><summary>Check your answers</summary>

1. When the transitions have names and shared rules, so the rules can live in one pure function instead of in every handler.
2. It prevents a new object identity on every parent render; it does not prevent re-renders caused by the value genuinely changing.
3. It subscribes the component to every field, so it re-renders on any change — exactly what Context already did.
4. The store lives in a module, not in the tree, so it survives unmounting and leaks state into the next test.
</details>

## Next

Next we will extract repeated behavior into custom hooks without hiding who owns the state.

<details><summary>Maintainer source record</summary>

- Source dossier: `FSO_PART_06.md`, sections 10–24 and 47–52.
- Official sources: React `useReducer`, `useContext`, "Scaling Up with Reducer and Context", and "Passing Data Deeply with Context"; Zustand TypeScript guide, selector guidance, and `useShallow` reference.
- Versions: Zustand 5.0.15; React 19.2.3; Vitest 4.0.18; React Testing Library 16.3.2; TypeScript 5.9.3.
- Consulted: 2026-08-23.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 10, FS08.4.
- DALT files inspected: `state-architecture-lab`, the Part 08 track manifest, and the former FS08.3 page.
- Extracted material: the closest-owner rule, the Context cost argument, the reducer vocabulary test, the selector rule, and the "do not store server records" boundary from the former FS08.3.
- Verified in the lab: the render counts above are measured, and replacing the slice selector with a whole-store selector makes the store behave exactly like Context.
</details>
