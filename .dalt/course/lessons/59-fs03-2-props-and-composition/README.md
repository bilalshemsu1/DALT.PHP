# FS03.2 — Typed props and composition

Lesson ID: FS03.2
Lesson format: Concise theory
Part: 03 — React foundations
Status: Published
Estimated effort: 30–40 minutes
Difficulty: Foundation
Prerequisites: FS03.1
Last reviewed: 2026-08-22

We will make components reusable by giving their inputs explicit TypeScript contracts and composing them without hidden data access.

> **Helpful background:** [Rendering, components, and JSX](/learn/lessons/29-fs03-1-components-jsx-and-typed-props)

## What we will learn

By the end, we can:

- declare and read typed props;
- pass data and callback values from a parent to a child;
- use `children` when a wrapper should accept nested UI.

## Props are a component's inputs

**Props** are values a parent passes to a child in JSX. Instead of letting `IssueSummary` import one particular array, we can state what it needs:

```tsx
import type { Issue } from './issue';

type IssueSummaryProps = {
  issues: readonly Issue[];
  heading?: string;
};

export function IssueSummary({
  issues,
  heading = 'Issue summary',
}: IssueSummaryProps) {
  const openCount = issues.filter((issue) => issue.status !== 'done').length;
  return <p aria-label={heading}>{openCount} issues are still open.</p>;
}
```

The parent supplies the values:

```tsx
<IssueSummary issues={issues} heading="Project status" />
```

The type belongs at the receiving boundary. TypeScript now rejects a missing required `issues` prop, a string in place of an array, or a mutable operation such as `issues.push(...)` inside the component. The optional `heading` receives a default only when it is absent or `undefined`.

Props are read-only snapshots for one render. A child does not assign to a prop or mutate an object received through it. When a child later needs to request a change, the parent can pass a function prop such as `onSelect(issue.id)`.

## Data flows down through explicit edges

Props make the component tree also a data-flow diagram:

```text
App
├── IssueSummary  ← issues
└── IssueList     ← issues
    └── IssueRow  ← one issue
```

This repetition is healthy. `App` owns the array and deliberately gives each child the input it needs. Avoid importing application data into every component: that hides dependencies and makes reuse and testing harder.

## Compose with children

Some components should define structure while their caller chooses the content. Nested JSX arrives through the special `children` prop:

```tsx
import type { ReactNode } from 'react';

type PanelProps = {
  title: string;
  children: ReactNode;
};

export function Panel({ title, children }: PanelProps) {
  return (
    <section>
      <h2>{title}</h2>
      {children}
    </section>
  );
}
```

The caller can now compose a panel around any renderable content:

```tsx
<Panel title="Project status">
  <IssueSummary issues={issues} />
  <IssueList issues={issues} />
</Panel>
```

`ReactNode` describes values React can render, including JSX, text, and `null`. Composition is useful for genuine wrappers such as panels and layouts. It is not a reason to split every heading or paragraph into a component.

## Try it

**Workspace:** continue in `.dalt/workspace/fs03-react-foundations` from FS03.1.

**Starting state:** `App.tsx` contains `IssueSummary`; the starter already contains typed `IssueList`.

Replace `IssueSummary` with the typed version above. Create `src/Panel.tsx` with the complete `Panel` component. Then change the returned JSX in `App`:

```tsx
return (
  <main>
    <h1>Platform / Issue tracker</h1>
    <Panel title="Project status">
      <IssueSummary issues={issues} />
      <IssueList issues={issues} />
    </Panel>
  </main>
);
```

Remember to import `Panel`. Run:

```bash
npm run typecheck
npm test
```

Both commands pass. Temporarily remove `issues={issues}` from `IssueSummary`; the checker reports that the required property is missing. Restore it.

**Expected result:** the same issue data appears through two explicit prop edges, nested JSX appears inside `Panel`, and an invalid component call fails before the browser runs.

**Reset:** keep the workspace for FS03.3, or delete it and repeat FS03.1–FS03.2.

## What to notice

The prop type checks the boundary between components, not just a variable in isolation. The caller must supply the contract and the receiver can rely on it.

`Panel` does not know what an issue is. Its narrow responsibility is structure; composition lets application-specific children remain with the caller that understands them.

## Check your understanding

1. Who supplies props and who reads them?
2. Why should a child not mutate an array prop?
3. When is `children: ReactNode` useful?
4. Why is importing shared data directly into every child less clear?

<details>
<summary>Check your answers</summary>

1. The parent supplies props; the rendered child reads them.
2. Props are read-only inputs, and mutation would change data owned elsewhere.
3. When a component owns surrounding structure while callers choose nested content.
4. It hides the component's inputs instead of showing data-flow edges in JSX.
</details>

## Next

We can pass an issue array into a component; next we will turn that array into meaningful conditional UI with stable identity.

<details>
<summary>Maintainer source record</summary>

- Source dossier: `REACT_DOCS.md`; `FSO_PART_01.md`.
- Official sources: React Learn, *Passing Props to a Component* and *Your First Component*; TypeScript JSX guidance.
- Versions: React 19.2.3; TypeScript 5.9.3.
- Consulted: 2026-08-22.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 4, FS03.2.
- DALT files inspected: the React foundations starter's `App.tsx`, `IssueList.tsx`, and `issue.ts`.
- Reused material: typed-prop, read-only input, callback-prop, and composition material extracted from former FS03.1.
</details>
