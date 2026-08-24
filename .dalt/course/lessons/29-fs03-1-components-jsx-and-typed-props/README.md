# FS03.1 — Rendering, components, and JSX

Lesson ID: FS03.1
Lesson format: Concise theory
Part: 03 — React foundations
Status: Published
Estimated effort: 30–40 minutes
Difficulty: Foundation
Prerequisites: FS02.6
Last reviewed: 2026-08-22

We will learn how React turns component functions and JSX into the browser interface we see.

> **Helpful background:** [Runtime boundaries and parsing external data](/learn/lessons/28-fs02-5-runtime-boundaries)

## What we will learn

By the end, we can:

- read the path from `main.tsx` to a rendered component;
- write JSX that combines markup with JavaScript values;
- keep rendering predictable by treating a component as a pure calculation.

## From one root to a component tree

React does not scan our files and display every exported function. The application starts when `main.tsx` finds a browser element and asks React to render one **root component**:

```tsx
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { App } from './App';

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
```

`<App />` is JSX. React calls `App`, reads the UI description it returns, and commits the required DOM changes. If `App` renders `IssueList`, React evaluates that child too. Those relationships form a component tree.

## A component is a rendering function

A component is a TypeScript function whose name begins with a capital letter and which returns renderable JSX:

```tsx
export function App() {
  const projectName = 'Platform';

  return (
    <main>
      <h1>{projectName} issue tracker</h1>
      <p>Choose an issue to begin.</p>
    </main>
  );
}
```

Lowercase JSX names such as `<main>` mean browser elements. Capitalized names such as `<App />` mean our components. Define components at module scope, not inside another component; otherwise a new component identity is created during every render.

## JSX is stricter than HTML

JSX is a JavaScript syntax extension, not an HTML string. A component must return one enclosing value, tags must close, and JavaScript expressions go inside braces:

```tsx
const openCount = 3;

return (
  <section className="issue-summary">
    <h2>Open issues</h2>
    <p>{openCount} waiting</p>
  </section>
);
```

Because `class` is a JavaScript keyword, JSX uses `className`. Attribute names generally use JavaScript-style casing, such as `onClick` and `tabIndex`. Braces can contain an expression; they are not a place for statements such as `if`.

The parentheses after `return` matter when JSX spans lines. Without them, JavaScript can insert a semicolon after `return` and the component returns nothing.

## Render without changing the outside world

React expects rendering to be **pure**: given the same props and state, a component returns the same JSX. Do not change an imported array, a global counter, the DOM, or another component while rendering.

```tsx
// Unsafe: every render changes shared data.
let renderCount = 0;

function Summary() {
  renderCount += 1;
  return <p>Render {renderCount}</p>;
}
```

React may render more than once, especially in development `StrictMode`, to expose accidental impurity. Repeated rendering is safe when the component only calculates JSX. User-driven changes belong in event handlers, which we meet in FS03.4.

## Try it

**Workspace:** `.dalt/workspace/fs03-react-foundations`

**Starting state:** copy the course-owned React lab and install its pinned packages.

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/react-foundations-lab/starter .dalt/workspace/fs03-react-foundations
cd .dalt/workspace/fs03-react-foundations
npm ci
```

Open `src/App.tsx`. Add this component above `App`, then render `<IssueSummary />` between the heading and `IssueList`:

```tsx
function IssueSummary() {
  const openCount = issues.filter((issue) => issue.status !== 'done').length;
  return <p>{openCount} issues are still open.</p>;
}
```

Run:

```bash
npm run typecheck
npm run dev
```

Open `http://localhost:5174`. The page shows `2 issues are still open.` above the list. Change the expression to `issues.length`, save, and observe it update to `3` without a manual reload. Restore the filtering expression.

**Expected result:** TypeScript passes, Vite updates the browser, and returned JSX becomes a real paragraph in the DOM.

**Reset:** keep this workspace for FS03.2–FS03.6, or delete `.dalt/workspace/fs03-react-foundations`.

## What to notice

We described the desired result; we did not create a paragraph or tell the browser where to insert it. React compared descriptions and performed the DOM work.

`IssueSummary` reads existing data and calculates output without changing it. That makes it safe for React to evaluate whenever it needs a fresh UI description.

## Check your understanding

1. Why does `<main>` mean something different from `<App />`?
2. What do braces do inside JSX?
3. Why should rendering not increment a module-level counter?
4. What does `createRoot(...).render(<App />)` establish?

<details>
<summary>Check your answers</summary>

1. Lowercase names are browser elements; capitalized names refer to components.
2. They insert the result of a JavaScript expression into JSX.
3. Rendering may repeat, so changing shared data makes output depend on render timing.
4. It connects a browser DOM node to the root of the component tree.
</details>

## Next

Our components currently reach imported data directly; next we will pass typed values through explicit props and compose components with children.

<details>
<summary>Maintainer source record</summary>

- Source dossier: React documentation research notes; Full Stack Open Part 1 research notes.
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: React Learn, *Your First Component*, *Writing Markup with JSX*, *Render and Commit*, and *Keeping Components Pure*.
- Versions: React 19.2.3; TypeScript 5.9.3; Vite 8.0.12.
- Consulted: 2026-08-22.
- Curriculum authority: DALT Fullstack theory curriculum, Batch 4, FS03.1.
- DALT files inspected: the React foundations starter's `main.tsx`, `App.tsx`, `IssueList.tsx`, and `issue.ts`.
- Reused material: former FS03.1 component, JSX, render-purity, and component-tree explanations.
</details>
