# FS03.6 — Controlled forms and validation feedback

Lesson ID: FS03.6
Lesson format: Concise theory
Part: 03 — React foundations
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Foundation
Prerequisites: FS03.5
Last reviewed: 2026-08-22

We will connect native form behavior to React state, validate deliberately, and submit one coherent draft.

> **Helpful background:** [State structure and ownership](/learn/lessons/61-fs03-5-state-structure-and-ownership)

## What we will learn

- build a controlled input with `value` and `onChange`;
- handle a real form submission;
- keep attempted-submit feedback separate from domain data.

## React controls the input value

A **controlled input** receives its displayed value from state and reports edits through an event:

```tsx
const [title, setTitle] = useState('');

<label>
  Title
  <input
    value={title}
    onChange={(event) => setTitle(event.target.value)}
  />
</label>
```

The event provides new text, the handler stores it, and the next render feeds it back through `value`. Providing `value` without `onChange` makes an input effectively read-only. Initialize text state with an empty string rather than switching from `undefined` later.

## Submit the form, not the button

Forms already define keyboard submission and related controls. Put behavior on `onSubmit`:

```tsx
import type { FormEvent } from 'react';

function handleSubmit(event: FormEvent<HTMLFormElement>) {
  event.preventDefault();
  // Validate, then send one draft to the owner.
}

return (
  <form onSubmit={handleSubmit}>
    {/* labelled controls */}
    <button type="submit">Create issue</button>
  </form>
);
```

`preventDefault` stops browser navigation because this local experiment handles the result in React. The form keeps native semantics: Enter can submit, labels identify controls, and the button declares its purpose.

## Validation is a state transition

Whitespace is not a useful title. Derive validity, then decide when feedback appears:

```tsx
const [title, setTitle] = useState('');
const [submitted, setSubmitted] = useState(false);
const titleError = submitted && title.trim() === ''
  ? 'Enter an issue title.'
  : null;
```

On submit, set `submitted` to true. If trimmed text is empty, return. Otherwise construct a draft, call the parent callback, and reset form state. Associate visible feedback with its input:

```tsx
<input
  id="issue-title"
  value={title}
  aria-describedby={titleError ? 'title-error' : undefined}
  aria-invalid={titleError ? true : undefined}
  onChange={(event) => setTitle(event.target.value)}
/>
{titleError ? <p id="title-error">{titleError}</p> : null}
```

HTML constraints such as `required` remain useful, but application rules and server rejection still need explicit handling. Client validation improves feedback; it is never a security boundary.

## Keep draft and saved issue distinct

```tsx
type IssueDraft = {
  title: string;
  priority: Issue['priority'];
};

type CreateIssueFormProps = {
  onCreate: (draft: IssueDraft) => void;
};
```

The form should not invent a database ID or pretend persistence succeeded. The parent—or later the server boundary—turns a valid draft into an issue.

## Try it

**Workspace:** continue in `.dalt/workspace/fs03-react-foundations`.

**Starting state:** `App` owns the issue array and filter from FS03.5.

Create `src/CreateIssueForm.tsx` with a controlled title, the submit pattern above, and the `onCreate` prop. In `App`, initialize local state from the imported seed and add:

```tsx
<CreateIssueForm
  onCreate={(draft) => {
    setIssues((current) => [
      ...current,
      {
        id: `ISS-${current.length + 1}`,
        title: draft.title,
        status: 'todo',
        priority: draft.priority,
      },
    ]);
  }}
/>
```

Run:

```bash
npm run typecheck
npm run dev
```

Submit an empty title: the error appears. Enter “Keyboard submission”, choose a priority, and press Enter. A new issue appears and the title clears.

**Expected result:** state mirrors controls, invalid input produces associated feedback, and valid submission sends one typed draft upward.

**Reset:** delete `.dalt/workspace/fs03-react-foundations`; this completes the shared disposable lab.

## What to notice

The form owns temporary editing state. `App` owns the saved collection because the list also consumes it. Later we can replace local insertion with an API call without redesigning every control.

## Check your understanding

1. What makes an input controlled?
2. Why handle `onSubmit` instead of only button clicks?
3. Why is an issue draft different from a saved issue?
4. Why is client validation not security?

<details><summary>Check your answers</summary>

1. React state supplies `value`, and `onChange` updates that state.
2. It preserves form semantics, including keyboard submission.
3. A draft lacks server-owned facts such as its persistent ID.
4. A client can be bypassed; the server must enforce trusted rules.
</details>

## Next

Our React interface has structure and behavior; Batch 5 begins with CSS behavior through Tailwind CSS v4 utilities.

<details><summary>Maintainer source record</summary>

- Source dossier: `REACT_DOCS.md`; `FSO_PART_01.md`.
- Official sources: React DOM `<input>` and `<form>` references; React Learn, *Reacting to Input with State* and *Choosing the State Structure*; MDN form labeling guidance.
- Versions: React 19.2.3; TypeScript 5.9.3.
- Consulted: 2026-08-22.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 4, FS03.6.
- DALT files inspected: React foundations starter model, app, and tests.
- Reused material: controlled-input, submit, validation, draft-state, and accessibility material from former FS03.3.
</details>
