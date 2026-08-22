# FS03.5 — State structure and ownership

Lesson ID: FS03.5
Lesson format: Concise theory
Part: 03 — React foundations
Status: Published
Estimated effort: 30–40 minutes
Difficulty: Foundation
Prerequisites: FS03.4
Last reviewed: 2026-08-22

We will keep state small, consistent, and owned by the nearest component that must coordinate it.

> **Helpful background:** [State, snapshots, and events](/learn/lessons/30-fs03-2-state-and-events)

## What we will learn

- distinguish source state from values we can derive;
- give each state value one owner;
- lift state to a common parent when siblings must agree.

## Store the source, derive the rest

It is tempting to store every useful value:

```tsx
const [issues, setIssues] = useState(initialIssues);
const [showDone, setShowDone] = useState(true);
const [visibleIssues, setVisibleIssues] = useState(initialIssues);
const [visibleCount, setVisibleCount] = useState(initialIssues.length);
```

Now one click must synchronize three related values. Missing one setter creates contradictory UI. Keep independent sources as state and calculate the rest during rendering:

```tsx
const [issues, setIssues] = useState(initialIssues);
const [showDone, setShowDone] = useState(true);

const visibleIssues = showDone
  ? issues
  : issues.filter((issue) => issue.status !== 'done');
const visibleCount = visibleIssues.length;
```

Derived values are ordinary constants, not inferior state. Recalculation keeps them consistent with the current snapshot.

Good state avoids contradictions, redundant facts, duplicated objects, and unnecessarily deep structures. For selection, store `selectedIssueId`, not a second copy of the selected issue; derive the object with `find`.

## Each value has one owner

State belongs in the lowest component that needs to coordinate every consumer. If only `CreateIssueForm` needs the current input text, the form can own it. If `IssueFilter` changes which rows `IssueList` shows, their nearest common parent owns the filter:

```text
App                    ← owns statusFilter
├── IssueFilter        ← receives value + onChange
└── IssueList          ← receives filtered issues
```

Moving shared state to the common parent is called **lifting state up**. Data flows down as props; user intent flows up as callback props:

```tsx
type IssueFilterProps = {
  showDone: boolean;
  onToggleDone: () => void;
};

function IssueFilter({ showDone, onToggleDone }: IssueFilterProps) {
  return (
    <button type="button" onClick={onToggleDone}>
      {showDone ? 'Hide completed' : 'Show completed'}
    </button>
  );
}
```

The child does not reach into the parent. It reports an event through the function it received.

## Update collections immutably

State arrays and objects are snapshots. Create a new value instead of changing the old one:

```tsx
setIssues((current) => [
  ...current,
  newIssue,
]);
```

`push` mutates the existing array and then hands React the same reference. A new array communicates a new snapshot and preserves older snapshots for reliable reasoning.

## Try it

**Workspace:** continue in `.dalt/workspace/fs03-react-foundations`.

**Starting state:** `App` owns `showDone`, derives `visibleIssues`, and renders the toggle directly.

Create `src/IssueFilter.tsx` with the component above. Import it in `App.tsx` and replace the button:

```tsx
<IssueFilter
  showDone={showDone}
  onToggleDone={() => setShowDone((current) => !current)}
/>
<p>{visibleIssues.length} visible issues</p>
<IssueList issues={visibleIssues} />
```

Run:

```bash
npm run typecheck
npm run dev
```

Toggle completed issues. The child requests the change, `App` owns the new snapshot, and both count and list update together.

**Expected result:** no duplicated visible-list or count state exists, yet two consumers remain synchronized.

**Reset:** keep the workspace for FS03.6, or delete it and repeat the preceding experiments.

## What to notice

Ownership is not “put all state at the top.” It is “give each fact one source of truth at the nearest useful level.” Local form text can stay local; shared filtering moves only as high as coordination requires.

## Check your understanding

1. Why should `visibleCount` normally not be state?
2. Where should state used by two siblings live?
3. How can a child request a parent-owned change?
4. Why store an ID instead of duplicating a selected object?

<details><summary>Check your answers</summary>

1. It can be calculated from current visible issues.
2. In their nearest common parent.
3. By calling a callback prop supplied by the parent.
4. The object can be derived, avoiding two copies that can disagree.
</details>

## Next

We have a clean owner for shared state; next we will connect browser form controls to state and give validation feedback.

<details><summary>Maintainer source record</summary>

- Source dossier: `REACT_DOCS.md`; `FSO_PART_01.md`.
- Official sources: React Learn, *Choosing the State Structure*, *Sharing State Between Components*, and *Updating Arrays in State*.
- Versions: React 19.2.3; TypeScript 5.9.3.
- Consulted: 2026-08-22.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 4, FS03.5.
- DALT files inspected: React foundations starter `App.tsx`, issue model, and existing FS03 state material.
- Reused material: state ownership and derivation extracted from former FS03.2 and form-state design from former FS03.3.
</details>
