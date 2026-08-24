# FS08.1 — Classify local, URL, session, and server state

Lesson ID: FS08.1
Lesson format: Concise theory
Part: 08 — Server and application state
Status: Published
Estimated effort: 25–35 minutes
Difficulty: Applied
Prerequisites: FS07.4
Last reviewed: 2026-08-23

We will sort the values a frontend holds by who owns them, so that later decisions about caching and stores follow from evidence instead of habit.

> **Helpful background:** [Fetching data and synchronizing with Effects](/learn/lessons/33-fs04-1-fetching-data-and-effects)

## What we will learn

- name the four homes a value can have, and the one that is not a home at all;
- recognise the two failures that hand-held server data produces;
- decide where a value belongs before choosing a tool for it.

## Ownership, not data type

A value's home is not decided by its TypeScript type or by how many components read it. It is decided by three questions: who is allowed to change it, how long it should live, and who else needs to see the change.

```text
local state    one component's private, temporary interaction
URL state      the browser's shareable, restorable location
server state   a remote fact the server owns; we hold a snapshot
derived        computed from something above, and never stored
```

A fourth home appears later — a small shared client store — but it has to earn its place, and nothing in this lesson needs it yet.

Session state is not a fifth kind. Who is signed in is a server fact: the browser holds a cookie, but the server decides what that cookie means. FS07.2 already treated the current user as remote data, and Part 08 keeps that classification.

## Local state is private and disposable

A half-typed issue title is local. It belongs to one form, nobody else may change it, and it is not supposed to survive the form closing:

```tsx
const [draft, setDraft] = useState('');
```

If two boards are on screen, each has its own draft, and they cannot interfere. That isolation is a feature, not a limitation. Ask "should a reload keep this?" — if the honest answer is no, it is local.

## URL state is shareable

A status filter is different. Someone pastes the address into a message, or reloads and expects the same screen back. That makes it the browser's, held in the query string:

```tsx
const [searchParams, setSearchParams] = useSearchParams();
const status = searchParams.get('status') ?? 'open';
```

Copying `?status=open` into `useState` gives us two answers to one question and a bug the moment the back button is pressed. Read it from the URL and write changes back to the URL.

## Derived values are not state at all

An open-issue count is not a value to store. It is a value to compute:

```tsx
const openCount = issues.filter((issue) => issue.status === 'open').length;
```

The moment we keep `openCount` beside `issues`, we have promised to update it everywhere `issues` changes, and one handler will forget. The experiment below watches that promise break.

## Server state is a snapshot, not a possession

An issue list, one issue, its comments, and the current user all live on the server. The browser can keep a recent answer, but it never owns the answer. That produces two problems that no amount of care inside one component can fix:

```text
two components fetch the same fact   → two private copies that drift apart
a write changes a fact               → every other copy is now silently wrong
```

Part 04's manual effect handles one component honestly. It has nothing to say about a second component holding the same fact — which is the real reason Part 08 introduces a cache in the next lesson.

## Try it

**Workspace:** copy the Part 08 lab and install it:

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/state-architecture-lab/starter \
  .dalt/workspace/fs08-state-architecture
cd .dalt/workspace/fs08-state-architecture
npm ci
```

**Starting state:** `src/StateAudit.tsx` holds every value by hand, exactly as Part 04 did. It reads the filter from the URL, keeps the draft in `useState`, keeps the fetched list in `useState`, and also keeps a second copy of the open count. `IssueStatusReader` fetches one issue on its own.

```bash
npm run test:audit
```

**Expected result:** four tests pass. They show the filter surviving in the address bar, the draft staying private to one board and vanishing on unmount, the stored count reading `2` while the derived count correctly reads `1`, and two independent readers of `ISS-1` disagreeing after one of them closes it.

Now delete `storedOpenCount` and render `derivedOpenCount` in both places. The drift test fails, because there is no longer a stale number to find — which is the point.

**Reset:** delete the workspace copy. Keep it if you intend to continue into FS08.2, which grows the same lab.

## What to notice

The third test does not fail. It passes while displaying a wrong number, because a stored derived value is a perfectly ordinary piece of state that happens to be a lie.

The fourth test is the one that motivates the rest of Part 08. Both readers wrote correct code. Both called the API. Both updated their own state honestly. They still disagree, because two private copies of one remote fact have no way to hear about each other — and `api.calls` shows the same issue was fetched twice for one screen.

## Common mistakes

- Copying a URL parameter into `useState` "so it is easier to read".
- Storing a count, a filtered array, or a formatted label beside the value it came from.
- Treating "several components need it" as proof that a value is global. Several components needing the same *server fact* is a caching question, not a store question.

## Check your understanding

1. Which two questions decide whether a value belongs in the URL or in `useState`?
2. Why is the current user classified as server state rather than session state?
3. What is wrong with keeping `openCount` next to `issues`?
4. Name the specific problem that two components fetching `ISS-1` separately creates.

<details><summary>Check your answers</summary>

1. Should it be shareable by copying the address, and should a reload restore it? Two yeses mean the URL.
2. Because the server decides what the cookie means and can end the session at any time; the browser only holds a snapshot of that decision.
3. It duplicates a fact that can be computed, so every update path has to remember to update both, and one will not.
4. Two private copies with no shared address: a write through one leaves the other stale, and the same fact is requested twice.
</details>

## Next

Next we will give each remote fact one address in a cache, so that two screens can read one answer.

<details><summary>Maintainer source record</summary>

- Source dossier: Full Stack Open Part 6 research notes, sections 4–13 and 27.
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: React `useState`, "Choosing the State Structure", and "You Might Not Need an Effect"; React Router `useSearchParams`.
- Versions: React 19.2.3; React Router 7.18.2; Vitest 4.0.18; React Testing Library 16.3.2; TypeScript 5.9.3.
- Consulted: 2026-08-23.
- Curriculum authority: DALT Fullstack theory curriculum, Batch 10, FS08.1.
- DALT files inspected: the new `state-architecture-lab`, the Part 08 track manifest, and the former FS08.1 page.
- Extracted material: the state taxonomy, the derived-state warning, and the "why a library, and why only now" argument from the former FS08.1. Its TanStack Query material moves to FS08.2.
</details>
