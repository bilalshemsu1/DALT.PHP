> **What exists when you finish:** the Team Issue Tracker, running in your own
> repository at `resources/app/`, served by a real DALT route, with filtering,
> selection, creation and an accessible responsive layout — all from typed local
> data, on your own branch.

## What you are building

**This is the milestone where the project starts.** Everything before it — FS03.1
through FS03.4's lab — lived in a throwaway workspace under `.dalt/workspace/`. From
here you are working in the real repository, in the learner's zone, and the code you
write now survives all the way to Part 12.

```text
resources/app/            your React + TypeScript source          ← new, yours
routes/routes.php         a route serving the tracker             ← yours
app/Http/controllers/     the controller behind it                ← yours
resources/views/          the PHP page the React app mounts into  ← yours

framework/  config/  public/  .dalt/                              ← never touched
```

This walkthrough builds the same screen FS03.1–3.4 teach the concepts behind, straight
in the real application — a project header, a filterable issue list, a detail panel, a
create form, keyboard-usable and responsive. If a step here moves faster than you'd
like on the *why*, that's deliberate: the why lives in FS03.1–3.4, already written, in
depth. This document is the *how*, for the actual project, snippet by snippet, until a
real page in your own repository does everything the lab did.

## Why this milestone exists

Three reasons, in order of how much they matter later.

**Because the lab has no server.** Part 04 makes this interface talk to DALT. It
cannot do that from a directory under `.dalt/workspace/` that Vite does not build and
no route serves. B03 is the move that makes Part 04 possible, and doing it now — while
the app has no server dependencies at all — means when the fetch arrives you are
debugging one new thing instead of two.

**Because you have to see the seam.** A React app does not float. Something serves an
HTML document; that document loads a bundle; the bundle mounts into an element. You
traced exactly that in B00 without knowing it was your own architecture. Wiring it
yourself is the difference between using a framework and understanding one.

**Because the project is now yours to keep.** No scaffold is installed for you. There
is no generator, and that is deliberate — `PROJECT_BLUEPRINT.md` puts the
specification, the fixtures and the teaching on the course, and the implementation on
you. A tracker you assembled is one you can debug.

## Before you start

All four Part 03 lessons complete, with the lab working: filters, selection, create
form, keyboard pass done. If you haven't done the lab yet, this walkthrough builds the
same thing directly in the real project — you can follow it in place of the lab, or
alongside it.

**Take a branch first.** The repository's `main` is the clean framework skeleton; your
issue tracker is not part of it.

```sh
git switch -c fullstack-build
```

Everything from here to Part 12 happens on that branch. It is also your recovery
mechanism — commit at the end of each stage, and a bad refactor costs you one
`git restore`, not an evening.

The toolchain is already installed and pinned at the repository root: React 19.2.3,
TypeScript 5.9.3, Vite 8, Vitest 4, Tailwind 4, ESLint 9. Confirm it:

```sh
npm install
npm run typecheck   # clean
npm run lint        # clean
npm run test        # no test files yet, exits 0
```

If any of those fail before you have written a line, stop and report it — that is a
defect in the course, not in your setup.

---

## Stage 1 — Give the application a source root

`vite.config.mjs` currently builds `resources/js/app.js`. Point it at a new
`resources/app/main.tsx` instead:

```js
// vite.config.mjs — build.rollupOptions.input
build: {
  manifest: true,
  outDir: resolve(__dirname, 'public/build'),
  emptyOutDir: true,
  rollupOptions: {
    input: resolve(__dirname, 'resources/app/main.tsx')   // was resources/js/app.js
  }
}
```

Create the entry file itself, and keep the Tailwind import in it:

```tsx
// resources/app/main.tsx
import { createRoot } from 'react-dom/client';
import '../css/input.css';
import { App } from './App';

const root = document.getElementById('root');
if (root) {
  createRoot(root).render(<App />);
}
```

Nothing to build yet — `App` doesn't exist until Stage 3. For now, a one-line
placeholder is enough to prove the pipeline:

```tsx
// resources/app/App.tsx — placeholder, replaced in Stage 3
export function App() {
  return <p>Issue tracker loading…</p>;
}
```

```sh
npm run build
```

**Check it yourself:** open `public/build/.vite/manifest.json` and read the key, not
just the file list. Exactly one entry, keyed `"resources/app/main.tsx"`, with a `file`
naming a hashed `.js` and a `css` array naming a hashed `.css`:

```json
{
  "resources/app/main.tsx": {
    "file": "assets/main-CvaoQnny.js",
    "name": "main",
    "src": "resources/app/main.tsx",
    "isEntry": true,
    "css": [
      "assets/main-Do1vYMz1.css"
    ]
  }
}
```

Your hashes will differ; the key must not. Creating the two `.tsx` files without
editing `vite.config.mjs` still builds — it just rebuilds the old entry, and the key
stays `"resources/js/app.js"`. That is the failure this check exists to catch, because
Stage 2 looks the bundle up by that exact key and would find nothing.

That manifest is how the PHP side finds your bundle in Stage 2 — not a build artifact
you can ignore, the actual indirection everything downstream depends on.

> Note what you did *not* have to do: install React, configure JSX, add a test runner,
> or set up Tailwind. That work was done when the root manifest was wired. The cost is
> that a fresh clone of this framework carries a React toolchain it may not use — a
> deliberate trade recorded as Option A in `IMPLEMENTATION_PLAN.md` §5.3.

## Stage 2 — Serve it from DALT

Three pieces, one request: a route, a controller, a view.

**The route** — `routes/routes.php`. `/app` is this course's convention; nothing
forces it (see Decisions below):

```php
$router->get('/app', 'app.php');
```

**The controller** — `app/Http/controllers/app.php`. A plain PHP file, no class,
following the exact shape `welcome.php` already uses:

```php
<?php

declare(strict_types=1);

view('app.view.php');
```

**The view** — `resources/views/app.view.php`. A mount element, and your built assets:

```php
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Issue tracker</title>
  <?= vite('resources/app/main.tsx') ?>
</head>
<body>
  <div id="root"></div>
</body>
</html>
```

You do not hand-write `<link>`/`<script>` tags. `vite()` (`framework/Core/functions.php`)
looks your entry up in the manifest from Stage 1 and returns them for you:

```php
function vite(string $entryPath): string
{
    $manifestPath = base_path('public/build/.vite/manifest.json');
    // ...
    if ($manifestPath) {
        $manifest = json_decode(file_get_contents($manifestPath), true);
        $tags = [];
        if (!empty($manifest[$entryPath]['css'])) {
            foreach ($manifest[$entryPath]['css'] as $cssFile) {
                $tags[] = '<link rel="stylesheet" href="/build/' . $cssFile . '">';
            }
        }
        if (!empty($manifest[$entryPath]['file'])) {
            $tags[] = '<script type="module" src="/build/' . $manifest[$entryPath]['file'] . '"></script>';
        }
        return implode("\n", $tags);
    }
    // falls back to the Vite dev server only when no build exists
}
```

The argument, `'resources/app/main.tsx'`, is the manifest *key* — the source path from
Stage 1, not the hashed output filename. That's the whole point: the hash changes on
every build and nothing in your PHP has to know.

**Working looks like:** `php artisan serve`, visit `/app`, and you see "Issue tracker
loading…" — the Stage 1 placeholder, rendered by React, served by DALT.

**Check it yourself — do this with the Network panel open, and recognise it:**

1. A document request to `/app` returns HTML with status 200.
2. That document causes a request for your JS bundle, and one for CSS.
3. The HTML arrives containing an empty `<div id="root">`; the content appears after
   the bundle runs.

That is B00's interaction #1, and you just built the server side of it. If the page is
blank, work down that list rather than guessing — it tells you whether the failure is
the route, the asset path, or the mount.

**The one that catches everyone:** `vite()` prefers the manifest, always. Read the
first branch above again — *if `public/build/.vite/manifest.json` exists, the dev
server is never consulted.* Stage 1 made you run `npm run build`, so from now on that
file exists.

The consequence is worth spelling out, because nothing reports it: start `npm run dev`,
edit a component, reload `/app`, and **you will see the old build**. No error, no
warning in either terminal, no clue in the Network panel beyond a filename hash that
never changes. People lose an evening to this.

So pick a mode deliberately for the rest of this walkthrough:

```sh
# Built mode — what production does. Re-run after every change.
npm run build

# Dev mode — hot reload. The manifest has to be gone for vite() to look for the server.
rm -rf public/build && npm run dev
```

Remember to `npm run build` again before Stage 5, or you will commit an `/app` that
serves nothing. Part 10 revisits this properly under Docker, where the same decision
becomes an environment flag instead of a deleted directory.

## Stage 3 — Build the screen

Everything below lives under `resources/app/`. Build it in the same order the concepts
were taught: a typed data model, one component, split it, add state, add a form, then
make it accessible.

### 3.1 — Typed local data

```ts
// resources/app/issue.ts
export type IssueStatus = 'todo' | 'in_progress' | 'done';
export type Priority = 'low' | 'medium' | 'high';
export type Issue = { id: string; title: string; status: IssueStatus; priority: Priority };

export const issues: Issue[] = [
  { id: 'ISS-1', title: 'Trace login failure', status: 'todo', priority: 'high' },
  { id: 'ISS-2', title: 'Document response shape', status: 'in_progress', priority: 'medium' },
  { id: 'ISS-3', title: 'Remove stale log', status: 'done', priority: 'low' },
];
```

Nothing fetches anything yet — deliberately. Part 04 replaces this module with a
server request; every component you write against it stays exactly the same on the day
that happens, because they only ever see typed `Issue` values, never this module
directly (FS02.5's runtime-boundary discipline, arriving early).

### 3.2 — One component per responsibility (FS03.1)

`IssueRow` renders one issue. It has no business deciding *which* issue, or how many —
that's the list's job:

```tsx
// resources/app/IssueRow.tsx
import type { Issue } from './issue';

type IssueRowProps = { issue: Issue; onSelect: (id: string) => void };

export function IssueRow({ issue, onSelect }: IssueRowProps) {
  return (
    <li>
      <button type="button" onClick={() => onSelect(issue.id)}>
        {issue.id}: {issue.title}
      </button>
    </li>
  );
}
```

Notice it's a real `<button>` here, not a `<li onClick>` — normally FS03.4 is where
that fix happens, after a keyboard pass finds the defect. If you want to feel that
defect yourself before reading past it, write `<li onClick={...}>` here instead, come
back once you've built through 3.4's keyboard pass below, and compare.

`IssueList` owns the `.map`, and the one decision that goes with it — what an empty
list actually means:

```tsx
// resources/app/IssueList.tsx
import type { Issue } from './issue';
import { IssueRow } from './IssueRow';

type IssueListProps = { issues: readonly Issue[]; onSelect: (id: string) => void; emptyMessage: string };

export function IssueList({ issues, onSelect, emptyMessage }: IssueListProps) {
  if (issues.length === 0) {
    return <p>{emptyMessage}</p>;
  }
  return (
    <ul>
      {issues.map((issue) => (
        <IssueRow key={issue.id} issue={issue} onSelect={onSelect} />
      ))}
    </ul>
  );
}
```

`emptyMessage` is a prop, not a hard-coded string, because "this project has no issues"
and "no issues match your filters" are different facts — you'll use both once filters
exist in 3.3.

`key={issue.id}`, never `key={index}`: a key answers "which of these is the same
logical item across renders," and an index describes a *position*, not an item. Filter
this list and the issue at position 0 becomes a different issue — React, told the key
is still `0`, would reuse the wrong row's state.

### 3.3 — State: filters, selection, mark done (FS03.2)

`ProjectPage` owns everything: the issues themselves (now state, because "mark done"
changes one), which filters are active, and which issue is selected. `IssueList` never
reaches out for any of this — it only ever receives what `ProjectPage` derives.

```tsx
// resources/app/ProjectPage.tsx (first pass — filters and selection only)
import { useState } from 'react';
import { issues as initialIssues, type Issue } from './issue';
import { IssueList } from './IssueList';
import { IssueDetail } from './IssueDetail';

type StatusFilter = 'all' | Issue['status'];
type PriorityFilter = 'all' | Issue['priority'];

export function ProjectPage({ workspaceName, projectName }: { workspaceName: string; projectName: string }) {
  const [issues, setIssues] = useState<Issue[]>(initialIssues);
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('all');
  const [priorityFilter, setPriorityFilter] = useState<PriorityFilter>('all');
  const [selectedIssueId, setSelectedIssueId] = useState<string | null>(null);

  // Derived during render, not stored — one source of truth, no useEffect
  // needed to "keep it in sync" (FS03.2's whole point).
  const visibleIssues = issues.filter(
    (issue) => (statusFilter === 'all' || issue.status === statusFilter)
            && (priorityFilter === 'all' || issue.priority === priorityFilter),
  );

  // Decision: a filter that hides the selected issue does not clear the
  // selection — it only stops IssueDetail from rendering while hidden.
  // Toggling the filter back re-reveals the same detail without a reselect.
  const selectedIssue = visibleIssues.find((issue) => issue.id === selectedIssueId) ?? null;

  function handleMarkDone(id: string) {
    // Functional updater, new array, new object — never a mutation. React
    // compares by reference; mutating in place gives it nothing to notice.
    setIssues((current) => current.map((issue) => (issue.id === id ? { ...issue, status: 'done' } : issue)));
  }

  return (
    <>
      <h1>{workspaceName} / {projectName}</h1>

      {(['all', 'todo', 'in_progress', 'done'] as const).map((value) => (
        <button key={value} onClick={() => setStatusFilter(value)}>{value}</button>
      ))}
      {(['all', 'low', 'medium', 'high'] as const).map((value) => (
        <button key={value} onClick={() => setPriorityFilter(value)}>{value}</button>
      ))}

      <IssueList
        issues={visibleIssues}
        onSelect={setSelectedIssueId}
        emptyMessage={issues.length === 0 ? 'This project has no issues yet.' : 'No issues match the current filters.'}
      />

      {selectedIssue && (
        <>
          <IssueDetail issue={selectedIssue} />
          {selectedIssue.status !== 'done' && <button onClick={() => handleMarkDone(selectedIssue.id)}>Mark done</button>}
        </>
      )}
    </>
  );
}
```

```tsx
// resources/app/IssueDetail.tsx
import type { Issue } from './issue';

export function IssueDetail({ issue }: { issue: Issue }) {
  return (
    <section aria-label="Issue detail">
      <h2>{issue.title}</h2>
      <p>Status: {issue.status}</p>
      <p>Priority: {issue.priority}</p>
    </section>
  );
}
```

Run it (`npm run build`, reload `/app`): filter buttons narrow the list, clicking a row
shows its detail, "Mark done" flips one issue's status without touching the others.

### 3.4 — A real create form (FS03.3)

The draft is its own type — a title and a priority, nothing else. Not
`Partial<Issue>`: a draft has no id and no status *yet*, and `Partial` would let both
compile as "maybe present" when they're actually "not this form's decision at all."

```tsx
// resources/app/CreateIssueForm.tsx
import { useState, type FormEvent } from 'react';
import type { Priority } from './issue';

export type IssueDraft = { title: string; priority: Priority };

export function CreateIssueForm({ onCreate }: { onCreate: (draft: IssueDraft) => void }) {
  const [title, setTitle] = useState('');
  const [priority, setPriority] = useState<Priority>('medium');
  const isValid = title.trim().length > 0;

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!isValid) return; // disabling the button is UI convenience, not the check
    onCreate({ title: title.trim(), priority });
    setTitle('');
    setPriority('medium');
  }

  return (
    <form onSubmit={handleSubmit}>
      <label htmlFor="draft-title">Title</label>
      <input id="draft-title" value={title} onChange={(event) => setTitle(event.target.value)} />

      <label htmlFor="draft-priority">Priority</label>
      <select id="draft-priority" value={priority} onChange={(event) => setPriority(event.target.value as Priority)}>
        <option value="low">low</option>
        <option value="medium">medium</option>
        <option value="high">high</option>
      </select>

      <button type="submit" disabled={!isValid}>
        {isValid ? 'Create issue' : 'Create issue (title required)'}
      </button>
    </form>
  );
}
```

Wire it into `ProjectPage` — the page assigns `id` and starting `status`, never the
form:

```tsx
// resources/app/ProjectPage.tsx — add alongside the existing state
import { CreateIssueForm, type IssueDraft } from './CreateIssueForm';

let nextId = initialIssues.length + 1;

// inside ProjectPage:
function handleCreate(draft: IssueDraft) {
  const created: Issue = { id: `ISS-${nextId++}`, title: draft.title, status: 'todo', priority: draft.priority };
  setIssues((current) => [...current, created]);
}

// in the JSX, after the selected-issue block:
<CreateIssueForm onCreate={handleCreate} />
```

Reload, type a title, submit — a fourth row appears and the form clears. Submit with
only spaces — the button stays disabled and nothing is added; that's `isValid`, not a
separate validation library. Five lines of hand-written checking is the lesson.

### 3.5 — The keyboard pass, and making it accessible (FS03.4)

Before changing anything else, put the mouse away and press Tab repeatedly through the
running screen, in order:

```text
all → todo → in_progress → done → all → low → medium → high → [draft-title input] → [priority select] → ???
```

If you built `IssueRow` with `<li onClick={...}>` instead of the `<button>` version
above, this is where you find it: **the issue rows are not in that list at all.**
Nothing to Tab into, no way to select one, complete or filter with the keyboard alone.
A `div` or `li` with an `onClick` is invisible to keyboard users and to Testing
Library's role queries — the exact defect this lesson exists to catch. (If you used the
`<button>` version already, confirm the opposite: every row *is* reachable, in visual
order.)

Fix (or confirm) the row, then finish the pass: semantic structure, focus rings, and a
responsive layout.

```tsx
// resources/app/IssueRow.tsx — the fix, if you didn't already have it
<li>
  <button
    type="button"
    onClick={() => onSelect(issue.id)}
    className="w-full rounded-md px-2 py-1 text-left text-sm hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-600"
  >
    {issue.id}: {issue.title}
  </button>
</li>
```

```tsx
// resources/app/App.tsx — one <main>, one <h1> (inside ProjectPage), source
// order matching visual order
export function App() {
  return (
    <main className="mx-auto max-w-4xl px-4 py-8">
      <ProjectPage workspaceName="Platform" projectName="Issue tracker" />
    </main>
  );
}
```

```tsx
// resources/app/ProjectPage.tsx — filter buttons in a wrapping Flex row,
// list and detail in a responsive Grid from md upward
<div role="group" aria-label="Filter by status" className="mt-4 flex flex-wrap gap-2">
  {/* ...FilterButton per value, each with focus-visible:outline... */}
</div>

<div className="mt-4 grid gap-4 md:grid-cols-[2fr_1fr]">
  <IssueList issues={visibleIssues} onSelect={setSelectedIssueId} emptyMessage={emptyMessage} />
  {selectedIssue && <div className="flex flex-col gap-2"><IssueDetail issue={selectedIssue} />{/* Mark done */}</div>}
</div>
```

**Check it yourself:** repeat the keyboard pass. You should now be able to filter,
select, mark done, and create — start to finish — without touching the mouse, with a
visible focus ring at every stop. Then narrow the window to 375px: the filter buttons
should wrap onto a second line rather than forcing the page to scroll sideways
(`flex-wrap`, not a fixed-width row), and the list/detail grid should collapse to one
column (`md:grid-cols-[...]`, not applied below `md`).

**Working looks like:** everything that worked in the lab works at `/app` — filters
narrow, selection shows detail, create adds a row, "mark done" updates one issue
immutably, and all of it is reachable with the keyboard alone.

## Stage 4 — Bring the tests with you

Port your lab tests to `resources/app/`, and add the ones you didn't have. At minimum:
the list renders one row per issue; the empty state appears for an empty list; a
whitespace-only title is rejected; a valid submit adds exactly one row; filtering to
one status renders exactly the matching rows.

```tsx
// resources/app/IssueList.test.tsx
import { render, screen } from '@testing-library/react';
import { IssueList } from './IssueList';
import { issues } from './issue';

test('renders one list item per typed issue', () => {
  render(<IssueList issues={issues} onSelect={() => {}} emptyMessage="No issues match the current filters." />);
  expect(screen.getByText(/Trace login failure/)).toBeInTheDocument();
  expect(screen.getAllByRole('listitem')).toHaveLength(3);
});

test('the empty state appears for an empty list and not for a populated one', () => {
  const { rerender } = render(<IssueList issues={[]} onSelect={() => {}} emptyMessage="No issues match the current filters." />);
  expect(screen.getByText(/no issues match the current filters/i)).toBeInTheDocument();

  rerender(<IssueList issues={issues} onSelect={() => {}} emptyMessage="No issues match the current filters." />);
  expect(screen.queryByText(/no issues match the current filters/i)).not.toBeInTheDocument();
});
```

```tsx
// resources/app/ProjectPage.test.tsx
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ProjectPage } from './ProjectPage';

test('filtering to done renders exactly one row', async () => {
  const user = userEvent.setup();
  render(<ProjectPage workspaceName="Platform" projectName="Issue tracker" />);
  await user.click(screen.getByRole('button', { name: 'done' }));
  expect(screen.getAllByRole('listitem')).toHaveLength(1);
  expect(screen.getByText(/Remove stale log/)).toBeInTheDocument();
});

test('submitting a whitespace-only title adds no row', async () => {
  const user = userEvent.setup();
  render(<ProjectPage workspaceName="Platform" projectName="Issue tracker" />);
  await user.type(screen.getByLabelText(/title/i), '   ');
  const submit = screen.getByRole('button', { name: /create issue/i });
  expect(submit).toBeDisabled(); // proves this test exercises validation, not a no-op click
  await user.click(submit);
  expect(screen.getAllByRole('listitem')).toHaveLength(3);
});

test('submitting a valid title adds exactly one row', async () => {
  const user = userEvent.setup();
  render(<ProjectPage workspaceName="Platform" projectName="Issue tracker" />);
  await user.type(screen.getByLabelText(/title/i), 'Write onboarding docs');
  await user.click(screen.getByRole('button', { name: /create issue/i }));
  expect(screen.getAllByRole('listitem')).toHaveLength(4);
  expect(screen.getByText(/Write onboarding docs/)).toBeInTheDocument();
});
```

They should run unchanged from the lab. `resources/setup-tests.ts`, wired from the root
`vite.config.mjs`, registers the jest-dom matchers; `@testing-library/user-event` is
installed at the root exactly as in the lab. If a matcher comes back as *"Invalid Chai
property: toBeInTheDocument"*, that setup file is missing or unregistered — report it,
because your typecheck stays green while it happens and that combination is a course
defect, not yours.

**Working looks like:** `npm run test` runs them and passes.

**Check it yourself:** make each test fail on purpose before you trust it. Comment out
the `disabled` attribute, watch the whitespace test fail, put it back. A test you have
never seen fail is a test you have no evidence about.

Prefer queries by role and label over test IDs, as above — `getByRole('button', { name:
/create/i })` fails the moment a button becomes a `div`; `getByTestId('create')` would
not. 3.5's accessibility work is what makes those queries possible, and the tests are
how it stays true.

## Stage 5 — Confirm the boundary held

The framework must still be a framework.

```sh
php artisan test        # same count as before you started — you changed no framework code
git status --short      # only resources/, routes/, app/, public/build, vite.config.mjs
```

**Check it yourself:** nothing under `framework/`, `config/`, or `.dalt/` appears in
your diff. If it does, something went in the wrong zone and now the course platform
depends on your project — the one thing the architecture forbids.

Then commit. `git log --oneline` on `fullstack-build` should read as a sequence of
stages, which is what makes the next ten parts recoverable.

---

## Decisions you have to make

- **Dev server or built assets?** Vite's dev server gives hot reload and needs the
  view to point at `localhost:5173`; built assets are what production serves. Real
  projects support both and switch on an environment flag. Do the simple one now and
  write down which you chose — Part 10 will make you revisit it under Docker.
- **What is the route?** `/app`, `/issues`, `/`? Replacing `/` means deciding what
  happens to the welcome page. There is no wrong answer, only an undecided one.
- **Where does local data live?** A module you import is simplest. A module that
  *looks* like an API — a function returning a promise — is more work now and less
  churn in Part 04. Either is fine; knowing which you picked and why is the point.
- **How much of the lab comes across?** You may improve things while porting. You may
  also change three things at once and lose track of which broke the tests. Port
  first, verify, then improve.

## Acceptance criteria

Read these against software you actually ran. Nothing here is checked automatically.

- [ ] I am on the `fullstack-build` branch and have committed at each stage.
- [ ] `resources/app/` holds my React + TypeScript source and Vite builds it.
- [ ] A real DALT route serves a page and the React app mounts into it.
- [ ] In the Network panel I can point at the document request, the bundle request,
      and the moment content appears.
- [ ] Filtering, selection, creation and "mark done" all work at that route.
- [ ] `npm run typecheck`, `npm run lint`, `npm run test` and `npm run build` all pass.
- [ ] My tests query by role and label, and I watched each one fail on purpose.
- [ ] `php artisan test` reports the same counts it did before I started this milestone.
- [ ] `git status` shows no change under `framework/`, `config/`, or `.dalt/`.
- [ ] Every value on screen came from typed local data — nothing fetches anything.
- [ ] A keyboard-only pass can filter, select, mark done, and create, with a visible
      focus indicator at every stop.

## Prove it to yourself

Close the editor. In your notes:

1. Trace a request to your route, from address bar to rendered issue list, naming
   every hop. Compare it with the trace you wrote in B00.
2. What does the Vite manifest do, and who reads it?
3. Which zones of this repository does your project live in, and why does it matter
   that it stays there?
4. Which components own state, and which only receive props?
5. Everything is local right now. Name three things that become hard the moment the
   issues live on a server.

Question 5 is Part 04's syllabus. Write your answer down before you read it there.

## What this unlocks

Part 04 replaces your local `issue.ts` with a request to a server, and the four
boundaries from FS01.2 become four things a user can see: loading, offline, an issue
that does not exist, and a response you could not parse.

Everything you built here stays. That is what makes it a project rather than an
exercise — and why the branch, the tests, and the zone discipline were worth the
extra stage.
