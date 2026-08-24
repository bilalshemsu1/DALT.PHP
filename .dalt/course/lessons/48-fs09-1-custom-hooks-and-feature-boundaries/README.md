# FS09.1 — Custom hooks as reusable behavior

Lesson ID: FS09.1
Lesson format: Concise theory
Part: 09 — Advanced React and tooling
Status: Published
Estimated effort: 30–40 minutes
Difficulty: Applied
Prerequisites: FS08.4
Last reviewed: 2026-08-23

We will extract repeated behavior into custom hooks without moving state away from the component that owns it.

> **Helpful background:** [Context, reducers, and when a client store earns its place](/learn/lessons/47-fs08-3-context-reducers-and-zustand)

## What we will learn

- extract a named concept rather than a wrapper around `useState`;
- keep the caller's ownership of state, and the URL's ownership of the URL;
- treat cleanup and the Rules of Hooks as part of a hook's contract.

## A hook shares behavior, never a value

This is the sentence to keep. A custom hook is a function that may call other hooks; when two components call it, each call gets its **own** state. `useIssueSelection()` in a board and in a sidebar produces two independent selections.

That is the difference between a hook and a store. A store is one value many components read. A hook is one *recipe* many components run separately. Choosing a hook when you meant a store gives every component its own private copy, and the bug looks like "my change did not appear over there".

## Extract a concept, not a convenience

The pressure to extract should come from a repeated **idea**, not a repeated line. "Issue filters" is an idea. "A call to `useState`" is not.

```ts
export function useIssueFilters(): IssueFilters {
  const [searchParams, setSearchParams] = useSearchParams();

  const rawStatus = searchParams.get('status') ?? 'open';
  const status: IssueStatus = isIssueStatus(rawStatus) ? rawStatus : 'open';
  const query = searchParams.get('q') ?? '';
  ...
  return {
    status, query, page,
    setStatus: (nextStatus) => write({ status: nextStatus, query, page: 1 }),
    setQuery: (nextQuery) => write({ status, query: nextQuery, page: 1 }),
    setPage: (nextPage) => write({ status, query, page: nextPage }),
  };
}
```

Two things are worth noticing. First, the hook validates untrusted input at the edge: `?status=sideways` becomes `open` rather than a value the rest of the application has to keep re-checking. Second, the rule "narrowing the results returns to page one" now lives in one place. That is the payoff — not fewer lines.

## Wrap the correct owner; do not create a second one

The URL still owns the filters. The hook reads and writes the address bar; it does not copy the values into `useState` and try to keep both in step. The same rule applies to a query hook: it may add a domain name and a complete key, but it must not copy `data` into local state, which would create a second snapshot with no invalidation policy.

```tsx
// Wrong: this copy stops following invalidation and background refetches.
const query = useProjectIssues(filters);
const [issues, setIssues] = useState<Issue[]>([]);
useEffect(() => setIssues(query.data ?? []), [query.data]);
```

A hook is also not a hiding place for an unnecessary effect. If a value can be computed during render, compute it during render.

## Cleanup is part of the contract

Whatever a hook starts, it stops — on unmount and on every change of its reactive inputs:

```ts
export function useIssueEvents(connect: Connect, issueId: string): string | null {
  const [latest, setLatest] = useState<string | null>(null);

  useEffect(() => {
    setLatest(null);

    return connect(issueId, setLatest);
  }, [connect, issueId]);

  return latest;
}
```

Because `issueId` is a reactive input, changing it disconnects the old stream before connecting the new one. Passing `connect` in rather than importing it keeps the hook testable and keeps its dependencies visible instead of hidden behind a module import.

## Structure is not optional

Hooks run at the top level of a component or another hook, in the same order every render:

```tsx
// Wrong: a render can skip the hook call.
if (enabled) { const filters = useIssueFilters(); }

// Right: call unconditionally, then choose what to render.
const filters = useIssueFilters();
return enabled ? <IssueListPage /> : <EmptyState />;
```

A hook's name is a promise about its scope. `useCurrentWorkspace` is constrained by its domain; `useEverything` announces that the architecture has been made invisible.

## Try it

**Workspace:** copy the Part 09 lab and install it:

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/frontend-architecture-lab/starter \
  .dalt/workspace/fs09-frontend-architecture
cd .dalt/workspace/fs09-frontend-architecture
npm ci
```

**Starting state:** `src/features/issues/` holds three hooks — URL filters, selection, and a subscription — plus an `index.ts` naming the feature's public surface.

```bash
npm run test:hooks
npm run typecheck
```

**Expected result:** four tests pass. They prove that `?status=sideways&page=0` is read as `status=open page=1`, that every setter writes back to the address bar, that changing the query returns to page one, that a board and a sidebar keep separate selections, and that the subscription hook logs `connect:ISS-1, disconnect:ISS-1, connect:ISS-2, disconnect:ISS-2` across a changed input and an unmount.

Now remove `issueId` from the effect's dependency array. The subscription test fails: the stream for `ISS-1` is never closed and `ISS-2` never opens.

**Reset:** delete the workspace copy, or keep it for FS09.2.

## What to notice

The selection test is the one worth staring at. Both probes call exactly the same hook, and clicking in the board leaves the sidebar empty. Nothing is shared except the code.

The cleanup log is evidence rather than a claim: React tears down the previous effect before running the new one, so the disconnect for `ISS-1` appears *before* the connect for `ISS-2`.

## Common mistakes

- Extracting a hook because two components both call `useState`, not because they share an idea.
- Copying query data or URL values into local state inside the hook.
- Leaving a subscription without a cleanup, so a changed input leaves the old one open.
- Silencing the exhaustive-dependencies warning instead of fixing the reactive inputs.

## Check your understanding

1. Two components call `useIssueSelection()`. How many selections exist?
2. Why does `useIssueFilters` validate `?status=` instead of trusting it?
3. What does returning a function from the effect in `useIssueEvents` guarantee?
4. Why is `connect` a parameter rather than a module import?

<details><summary>Check your answers</summary>

1. Two. A hook shares behavior, not a value; each call has its own state.
2. It is untrusted input from the address bar, and validating once at the edge keeps every later reader from re-checking it.
3. That whatever the effect started is stopped before the next run and on unmount.
4. It keeps the dependency visible and injectable, so a test can supply a fake and observe both ends of the contract.
</details>

## Next

Next we will point the dependencies between features and shared code in one direction, and check it automatically.

<details><summary>Maintainer source record</summary>

- Source dossier: Full Stack Open Part 7 research notes, sections 10–35.
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: React "Reusing Logic with Custom Hooks", "Rules of Hooks", `useEffect` reference and its cleanup semantics, and "You Might Not Need an Effect"; React Router `useSearchParams`.
- Versions: React 19.2.3; React Router 7.18.2; Vitest 4.0.18; React Testing Library 16.3.2; TypeScript 5.9.3.
- Consulted: 2026-08-23.
- Curriculum authority: DALT Fullstack theory curriculum, Batch 10, FS09.1.
- DALT files inspected: the new `frontend-architecture-lab`, the Part 09 track manifest, and the former FS09.1 page.
- Extracted material: the concept-not-wrapper rule, the "wrap a correct owner" warning, the Rules of Hooks examples, and the hook-naming rule from the former FS09.1. Its feature-boundary material moves to FS09.2.
- Verified in the lab: the cleanup order above is the observed log, not an assumption.
</details>
