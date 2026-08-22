# FS03.8 — Responsive, semantic, and accessible application UI

Lesson ID: FS03.8
Lesson format: Concise theory
Part: 03 — React foundations
Status: Published
Estimated effort: 30–40 minutes
Difficulty: Foundation
Prerequisites: FS03.7
Last reviewed: 2026-08-22

We will preserve the interface's meaning and operation while adapting its presentation across input methods and screen sizes.

> **Helpful background:** [CSS behavior through Tailwind CSS v4 utilities](/learn/lessons/62-fs03-7-css-and-tailwind-v4)

## What we will learn

- choose native elements for their behavior, not their default appearance;
- keep labels, headings, focus, errors, and disabled states understandable;
- check a responsive interface with keyboard, narrow viewport, and browser tools.

## Semantics carry the interaction contract

Use a `button` for an action, an `a` for navigation, and a labelled form control for input. A clickable `div` may resemble a button, but it does not automatically receive keyboard activation, focus behavior, or a button role.

```tsx
<button
  type="button"
  className="rounded-lg px-3 py-2 focus-visible:outline-2 focus-visible:outline-offset-2"
  onClick={onToggleDone}
>
  {showDone ? 'Hide completed' : 'Show completed'}
</button>
```

Tailwind changes presentation; the browser element supplies meaning and built-in interaction. Styling cannot repair the wrong element completely.

## Labels and errors must stay connected

A placeholder disappears as we type, so it cannot replace a label. Associate visible text with the control:

```tsx
<label htmlFor="issue-title" className="text-sm font-medium text-slate-800">
  Issue title
</label>
<input
  id="issue-title"
  aria-describedby={titleError ? 'title-error' : undefined}
  aria-invalid={titleError ? true : undefined}
  className="rounded-lg border border-slate-300 px-3 py-2 focus-visible:outline-2"
/>
{titleError ? (
  <p id="title-error" className="text-sm text-red-700">{titleError}</p>
) : null}
```

The `id` and `htmlFor` establish the input's accessible name. `aria-describedby` connects additional feedback, while `aria-invalid` communicates the invalid state. These attributes support the relationship; they do not replace visible text.

## Focus is information

Keyboard users need to know which control will receive the next action. Do not remove outlines without a visible replacement. `focus-visible:` is useful because it can emphasize keyboard-style focus without forcing the same ring after every pointer click.

Color alone is not enough to communicate status. Pair it with text such as “High priority” or “Saving”. Ensure text and controls retain readable contrast in their default, hover, focus, disabled, and error states.

A disabled button should explain itself through nearby context:

```tsx
<button type="submit" disabled={isSaving}>
  {isSaving ? 'Creating issue…' : 'Create issue'}
</button>
```

Disabling prevents repeated submission; the changing label tells the user why.

## Responsive means reflow, not shrinking

Start with the natural small-screen layout, then add columns when space permits:

```tsx
<div className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(16rem,1fr)]">
  <section aria-labelledby="issues-heading">...</section>
  <aside aria-labelledby="summary-heading">...</aside>
</div>
```

`minmax(0, 2fr)` allows the main track to shrink instead of overflowing because of long content. Keep DOM order meaningful: CSS can move boxes visually, but keyboard and screen-reader order still follow the document.

Avoid fixed widths for controls and cards unless the content truly has a fixed size. Prefer `w-full`, maximum widths, wrapping flex rows, and grids that collapse naturally.

## A heading structure describes the page

Heading levels represent nesting, not font size:

```text
h1  Platform issue tracker
└── h2  Issues
└── h2  Create issue
└── h2  Project summary
```

Use Tailwind classes for visual size. Do not choose `h4` because its browser default happens to look smaller.

## Try it

**Workspace:** continue in `.dalt/workspace/fs03-tailwind` from FS03.7.

**Starting state:** the starter list has responsive card styling but minimal structure.

Wrap the issue list in a labelled section and add a real filter button:

```tsx
<section aria-labelledby="issues-heading" className="space-y-4">
  <div className="flex flex-wrap items-center justify-between gap-3">
    <h2 id="issues-heading" className="text-xl font-semibold">Issues</h2>
    <button
      type="button"
      className="rounded-lg border border-slate-300 px-3 py-2
                 hover:bg-slate-50 focus-visible:outline-2
                 focus-visible:outline-offset-2 focus-visible:outline-violet-600"
    >
      Filter issues
    </button>
  </div>
  <IssueList issues={issues} />
</section>
```

Run:

```bash
npm run typecheck
npm run build
npm run dev
```

At `http://localhost:5174`, narrow the viewport to about 390px and confirm there is no horizontal page scroll. Then use only Tab and Shift+Tab. The filter button receives a visible focus outline; Enter and Space activate its native button behavior.

Open the browser accessibility tree and confirm the section is named “Issues”. Increase text zoom to 200% and check that content reflows without overlapping.

**Expected result:** typecheck and build pass; the page works in source order at a narrow width, retains visible keyboard focus, and exposes meaningful names.

**Reset:** delete `.dalt/workspace/fs03-tailwind`; this completes the disposable Part 03 styling lab.

## What to notice

Accessibility is not a final decoration. The strongest decisions happened before color: native elements, labels, document order, headings, and status text. Tailwind then made those relationships clear visually.

## Check your understanding

1. Why is a styled `div` not equivalent to a button?
2. What relationship do `htmlFor` and `id` create?
3. Why can visual reordering disagree with keyboard order?
4. What must replace an outline if it is removed?

<details><summary>Check your answers</summary>

1. It lacks the button's native semantics, focus, and keyboard activation.
2. They associate a visible label with its form control.
3. Focus follows DOM order, not the boxes' visual positions.
4. An equally visible focus indicator.
</details>

## Next

Part 03 can describe an interactive interface; Part 04 begins by synchronizing that interface with server data after rendering.

<details><summary>Maintainer source record</summary>

- Source dossier: `FSO_PART_02.md`; former FS03.4 sources.
- Official sources: MDN HTML element and form-label guidance; WAI-ARIA accessible descriptions; Tailwind CSS responsive and state variants.
- Versions: React 19.2.3; Tailwind CSS 4.3.0.
- Consulted: 2026-08-22.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 5, FS03.8.
- DALT files inspected: React foundations starter JSX, stylesheet, Vite configuration, and issue-list tests.
- Reused material: semantic controls, labels, focus, disabled state, small-screen layout, and keyboard checks from former FS03.4.
</details>
