# FS03.7 — CSS behavior through Tailwind CSS v4 utilities

Lesson ID: FS03.7
Lesson format: Concise theory
Part: 03 — React foundations
Status: Published
Estimated effort: 30–40 minutes
Difficulty: Foundation
Prerequisites: FS03.6
Last reviewed: 2026-08-22

We will read Tailwind CSS utilities as small, predictable CSS rules and combine them into an intentional interface.

> **Helpful background:** [Controlled forms and validation feedback](/learn/lessons/31-fs03-3-forms-and-state-design)

## What we will learn

- connect common utilities to the CSS behavior they produce;
- reason about spacing, sizing, flexbox, and grid before choosing classes;
- use Tailwind CSS v4's CSS-first setup and theme variables.

## Tailwind is still CSS

A utility class normally expresses one narrow declaration. This JSX:

```tsx
<main className="mx-auto max-w-5xl px-4 py-10">
```

roughly means:

```css
main {
  max-width: 64rem;
  margin-inline: auto;
  padding-inline: 1rem;
  padding-block: 2.5rem;
}
```

We still need the CSS mental model. `max-w-5xl` limits width; it does not center anything. `mx-auto` consumes equal inline space only when a width constraint leaves space to consume. Utilities make declarations quick to combine, but they do not change how layout works.

## Spacing belongs to a relationship

Choose spacing by asking what is separated:

```tsx
<section className="space-y-6">
  <header className="space-y-1">...</header>
  <form className="grid gap-4">...</form>
</section>
```

`space-y-6` separates direct siblings vertically. `gap-4` separates tracks or flex items. Padding such as `p-6` creates space inside a box; margin creates space outside it. Using all three randomly produces a page whose rhythm is hard to change.

## Flex and grid solve different arrangements

Flexbox arranges items primarily along one axis. It suits a heading beside an action or a row of filters:

```tsx
<header className="flex items-center justify-between gap-4">
  <h2 className="text-lg font-semibold">Issues</h2>
  <button type="button">New issue</button>
</header>
```

Grid defines rows and columns together. It suits repeated cards or aligned form fields:

```tsx
<ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
  {/* issue cards */}
</ul>
```

Start from content order and natural document flow. Add a layout system when a real relationship needs it.

## Variants apply a rule under a condition

The prefix before a colon is a condition:

```tsx
<button className="bg-violet-600 hover:bg-violet-500 focus-visible:outline-2 disabled:opacity-50">
  Create issue
</button>
```

The base background is always active. `hover:` applies while a pointer hovers, `focus-visible:` when keyboard-style focus should be visible, and `disabled:` when the native control is disabled. Responsive prefixes such as `sm:` and `lg:` are min-width conditions. We write the small-screen behavior without a prefix, then add changes for wider screens.

## Tailwind v4 starts in CSS

The shared lab already has the Vite plugin and one CSS import:

```css
@import "tailwindcss";
```

Tailwind scans source files for complete class names and generates the CSS that is used. Do not construct fragments such as `` `bg-${color}-600` ``; the scanner cannot reliably discover possible results. Map data to complete strings instead:

```ts
const priorityClass = {
  low: 'bg-sky-100 text-sky-800',
  medium: 'bg-amber-100 text-amber-900',
  high: 'bg-rose-100 text-rose-900',
} as const;
```

When a project needs its own reusable design tokens, v4 defines them in CSS with `@theme`:

```css
@theme {
  --color-brand-600: oklch(0.52 0.21 292);
}
```

That variable creates utilities such as `bg-brand-600`. We do not need custom tokens merely to complete this experiment; the point is that v4 configuration begins in CSS rather than requiring an old `tailwind.config.js` file.

## Try it

**Workspace:** `.dalt/workspace/fs03-tailwind`

**Starting state:** copy the unmodified React foundations starter and install its pinned packages.

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/react-foundations-lab/starter .dalt/workspace/fs03-tailwind
cd .dalt/workspace/fs03-tailwind
npm ci
```

In `src/App.tsx`, give `main` and the heading these classes:

```tsx
<main className="mx-auto max-w-4xl space-y-8 px-4 py-10 sm:px-6">
  <h1 className="text-3xl font-bold tracking-tight text-slate-950">
    Platform / Issue tracker
  </h1>
  <IssueList issues={issues} />
</main>
```

In `IssueList.tsx`, style the list and rows:

```tsx
<ul className="grid gap-3 sm:grid-cols-2">
  {issues.map((issue) => (
    <li className="rounded-xl border border-slate-200 bg-white p-4" key={issue.id}>
      {issue.id}: {issue.title}
    </li>
  ))}
</ul>
```

Run:

```bash
npm run typecheck
npm run build
npm run dev
```

Open `http://localhost:5174`, then resize across the `sm` breakpoint. The list changes from one column to two while document order remains unchanged.

**Expected result:** typecheck and build pass; the browser shows a constrained page, consistent card spacing, and a small-screen-first grid.

**Reset:** keep this workspace for FS03.8, or delete `.dalt/workspace/fs03-tailwind`.

## What to notice

Every class answers a CSS question: width, spacing, track layout, border, or type. If we cannot explain a class that way, we are guessing rather than styling.

## Check your understanding

1. Why do `max-w-5xl` and `mx-auto` do different jobs?
2. When is grid a better first choice than flexbox?
3. What does an unprefixed responsive utility represent?
4. Why should dynamic choices map to complete class strings?

<details><summary>Check your answers</summary>

1. One constrains width; the other distributes remaining inline margin.
2. When repeated content needs coordinated rows and columns.
3. The base, small-screen behavior.
4. Tailwind's scanner needs complete discoverable class names.
</details>

## Next

We can control visual layout; next we will make sure the same interface preserves meaning, keyboard access, focus, and useful behavior at different widths.

<details><summary>Maintainer source record</summary>

- Source dossier: `FSO_PART_02.md`; no dedicated Tailwind dossier existed.
- Official sources: Tailwind CSS, *Installing with Vite*, *Responsive design*, *Hover, focus, and other states*, *Detecting classes in source files*, and *Theme variables*.
- Versions: Tailwind CSS 4.3.0; `@tailwindcss/vite` 4.3.0; Vite 8.0.12.
- Consulted: 2026-08-22.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 5, FS03.7.
- DALT files inspected: React foundations starter `package.json`, `vite.config.ts`, `index.css`, `App.tsx`, and `IssueList.tsx`.
- Reused material: box-model, spacing, flex, grid, responsive, and utility-to-CSS explanations extracted from former FS03.4.
</details>
