# FS03.3 — Lists, conditional UI, and stable keys

Lesson ID: FS03.3
Lesson format: Concise theory
Part: 03 — React foundations
Status: Published
Estimated effort: 25–35 minutes
Difficulty: Foundation
Prerequisites: FS03.2
Last reviewed: 2026-08-22

We will translate collections and application conditions into JSX while giving React stable item identity.

> **Helpful background:** [Typed props and composition](/learn/lessons/59-fs03-2-props-and-composition)

## What we will learn

- transform an array into JSX with `map`;
- choose UI with ordinary JavaScript conditions;
- use stable data IDs as React keys.

## An array becomes an array of elements

`map` preserves the useful relationship “one issue in, one row out”:

```tsx
function IssueRow({ issue }: { issue: Issue }) {
  return <li>{issue.id}: {issue.title}</li>;
}

export function IssueList({ issues }: { issues: readonly Issue[] }) {
  return (
    <ul>
      {issues.map((issue) => (
        <IssueRow key={issue.id} issue={issue} />
      ))}
    </ul>
  );
}
```

The callback returns JSX for each value. React can render the resulting array. Extracting `IssueRow` is useful because a row is a meaningful unit with its own input; the `map` still stays near the list that owns iteration.

## Conditions choose descriptions

JSX accepts expressions, so ordinary JavaScript decides what it contains. An early return makes mutually exclusive states clear:

```tsx
if (issues.length === 0) {
  return <p>No issues match this view.</p>;
}
```

For a small optional detail, a conditional expression fits inline:

```tsx
{issue.priority === 'high' ? <strong>High priority</strong> : null}
```

Use `&&` carefully. `count && <p>...</p>` renders the number `0` when the count is zero; `count > 0 && ...` expresses the boolean condition honestly. Avoid deeply nested ternaries—calculate a value or use a named component instead.

## A key preserves identity

When items are inserted, removed, or reordered, React needs to match each new element with its previous counterpart. The `key` supplies that identity among siblings.

A database or domain ID is usually right:

```tsx
<IssueRow key={issue.id} issue={issue} />
```

An array index is unsafe when order can change. After inserting at the top, index `0` means a different issue, so React may preserve input state or focus against the wrong row. A random key is worse: it changes on every render and forces React to discard and recreate the row.

`key` is used by React and is not passed as a normal prop. If `IssueRow` needs the ID, it receives it through `issue` or a separate `issueId` prop.

## Try it

**Workspace:** continue in `.dalt/workspace/fs03-react-foundations`.

**Starting state:** `IssueList.tsx` maps issues directly to list items.

Replace it with the `IssueRow` and `IssueList` above, including this empty branch before the returned list:

```tsx
if (issues.length === 0) {
  return <p>No issues match this view.</p>;
}
```

Run:

```bash
npm run typecheck
npm test
npm run dev
```

The checker and existing tests pass, and the browser still shows three rows. In `App.tsx`, temporarily pass `issues={[]}` to `IssueList`; the list is replaced by “No issues match this view.” Restore `issues={issues}`.

**Expected result:** non-empty data produces one row per issue; empty data produces an explicit empty state; stable IDs identify rows.

**Reset:** keep the workspace for FS03.4, or delete it and repeat the preceding experiments.

## What to notice

We did not append or remove DOM nodes ourselves. We calculated the appropriate tree for the current array. The key does not control visual order; it lets React preserve identity while the array controls order.

## Check your understanding

1. Why is `map` a natural fit for list rendering?
2. Why can `count && <Message />` display an unwanted zero?
3. What question does a key answer?
4. Why is a random key unstable?

<details><summary>Check your answers</summary>

1. It produces one output element for each input item.
2. JavaScript returns the falsy operand, and React renders numeric zero.
3. Which new sibling corresponds to which previous sibling.
4. It changes on every render, so no previous identity can be matched.
</details>

## Next

Our UI now reflects fixed input; next we will let events request state changes and understand why each render sees a snapshot.

<details><summary>Maintainer source record</summary>

- Source dossier: `REACT_DOCS.md`; `FSO_PART_01.md`.
- Official sources: React Learn, *Rendering Lists* and *Conditional Rendering*.
- Versions: React 19.2.3; TypeScript 5.9.3.
- Consulted: 2026-08-22.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 4, FS03.3.
- DALT files inspected: React foundations starter `IssueList.tsx`, its rendering tests, and `issue.ts`.
- Reused material: list transformation, empty-state, stable-key, and conditional-rendering material extracted from former FS03.1.
</details>
