# FS08.3 — Mutations, invalidation, and careful optimism

Lesson ID: FS08.3
Lesson format: Concise theory
Part: 08 — Server and application state
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Applied
Prerequisites: FS08.2
Last reviewed: 2026-08-23

We will write through the same cache we read from, and decide when showing a result before the server confirms it is worth the risk.

> **Helpful background:** [Query keys and server-state reads](/learn/lessons/71-fs08-2-query-keys-and-server-state-reads)

## What we will learn

- run a write with `useMutation` and give the interaction an honest pending state;
- invalidate the facts a write changed instead of patching local arrays;
- apply one optimistic update with a rollback we have actually seen work.

## A write changes facts, not variables

After a successful create, the issue list is wrong. Not our copy of it — the *fact* it represents changed. So the honest response is to tell the cache which addresses are now suspect and let it re-ask:

```tsx
const create = useMutation({
  mutationFn: (title: string) => api.createIssue(projectId, title),
  onSuccess: async (created, _title, _onMutateResult, context) => {
    setDraft('');
    await context.client.invalidateQueries({
      queryKey: queryKeys.issues(projectId, created.status),
    });
  },
});
```

`invalidateQueries` marks matching entries stale and refetches the ones currently on screen. Any prefix matches: `['projects', 'PRJ-1', 'issues']` invalidates every status of that project's list, which is usually what a create means.

The alternative — splicing the created issue into a local array — recreates exactly the drift FS08.1 measured. The server may have trimmed the title, assigned a different id, or ordered the list differently.

## Read the callback signature carefully

In current versions the callbacks take more arguments than most older tutorials show. The value returned by `onMutate` arrives **third**, and a mutation context carrying the query client is always **last**:

```ts
onMutate?:  (variables, context) => TOnMutateResult
onSuccess?: (data, variables, onMutateResult, context) => unknown
onError?:   (error, variables, onMutateResult, context) => unknown
onSettled?: (data, error, variables, onMutateResult, context) => unknown
```

Reading a tutorial written for the three-argument shape is a reliable way to write a rollback that silently restores `undefined`.

## Pending state is part of the interaction

`mutation.isPending` is the only honest answer to "did my click do anything?", and disabling the control is what stops a double submission:

```tsx
<button type="submit" disabled={create.isPending}>
  {create.isPending ? 'Creating…' : 'Create issue'}
</button>
```

A failed write is also not one thing. A 422 means the input was rejected and the draft must survive so it can be corrected. A 403 means this user may not do it at all. A 500 means try again later. Clearing the form on every failure throws away work the person can still use.

## Optimism is a trade

An optimistic update shows the expected result immediately and repairs the screen if the server disagrees. It is worth it when the write nearly always succeeds, the correct result is fully predictable from the client, and the wait would otherwise be visible — a status toggle qualifies; a create with a server-assigned id does not.

The complete pattern has four moving parts:

```tsx
const setStatus = useMutation({
  mutationFn: (next: IssueStatus) => api.setIssueStatus(issueId, next),
  onMutate: async (next, context) => {
    await context.client.cancelQueries({ queryKey: queryKeys.issue(issueId) });
    const previous = context.client.getQueryData<Issue>(queryKeys.issue(issueId));
    if (previous !== undefined) {
      context.client.setQueryData<Issue>(queryKeys.issue(issueId), { ...previous, status: next });
    }

    return { previous };
  },
  onError: (_error, _next, onMutateResult, context) => {
    if (onMutateResult?.previous !== undefined) {
      context.client.setQueryData(queryKeys.issue(issueId), onMutateResult.previous);
    }
  },
  onSettled: (_data, _error, _next, _onMutateResult, context) =>
    context.client.invalidateQueries({ queryKey: queryKeys.issue(issueId) }),
});
```

`cancelQueries` matters: an in-flight read started before the click would otherwise land on top of the optimistic value and undo it. `onSettled` runs on success and on failure, so the screen always finishes on a server answer rather than on a guess.

## Try it

**Workspace:** continue in the Part 08 lab, or copy a clean starter:

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/state-architecture-lab/starter \
  .dalt/workspace/fs08-state-architecture
cd .dalt/workspace/fs08-state-architecture
npm ci
```

**Starting state:** `src/IssueMutations.tsx` holds a plain create and an optimistic status toggle. The fake server can now hold reads and writes open with `pauseReads()` and `pauseWrites()`, so each step can be observed instead of inferred.

```bash
npm run test:mutations
npm run typecheck
```

**Expected result:** five tests pass. They prove that a successful create causes a second `listIssues` call rather than a local splice, that a rejected create keeps the draft and leaves the list untouched, that the submit button is disabled and the write happens once, that the optimistic status appears before the server answers, and that a forbidden write rolls back while the refetch is still blocked.

Now delete the `onError` callback from `IssueStatusCard`. The rollback test fails.

**Reset:** delete the workspace copy, or keep it for FS08.4.

## What to notice

The rollback test blocks the refetch on purpose, and that detail is the lesson. Written the obvious way — click, assert the old value is back — it passes *with the rollback deleted*, because `onSettled` refetches the truth and the truth happens to be the old value. The test would have been green and would have proved nothing.

Blocking reads separates the two mechanisms: with the refetch held open, only the rollback can put `open` back on screen.

One honest limit: deleting `cancelQueries` leaves all five tests green. These experiments never start a read and a write close enough together to collide, so the suite does not cover that race. Keep the call because the race is real, not because a test here proves it.

## Common mistakes

- Patching a local array after a write instead of invalidating the fact.
- Skipping `cancelQueries`, so a read already in flight overwrites the optimistic value.
- Clearing a form on every failure, including the 422 the person could have fixed.
- Making every mutation optimistic. Optimism is for writes whose result we can predict.

## Check your understanding

1. Why invalidate a query key rather than update the cached array by hand?
2. Which argument of `onError` carries what `onMutate` returned?
3. What does `cancelQueries` protect the optimistic value from?
4. Why does a create make a poor candidate for an optimistic update?

<details><summary>Check your answers</summary>

1. The server decides the result — ids, ordering, normalised fields — so re-asking is the only way to be sure the screen matches it.
2. The third one; the mutation context is the fourth and last.
3. A read that was already in flight when the click happened, which would resolve afterwards with pre-write data.
4. The client cannot predict the server-assigned id, so the optimistic row is not the row that will exist.
</details>

## Next

Next we will decide when client state genuinely needs Context, a reducer, or a store.

<details><summary>Maintainer source record</summary>

- Source dossier: Full Stack Open Part 6 research notes, sections 25–34 and 40–46.
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: TanStack Query v5 Mutations, Invalidations from Mutations, Query Invalidation, and Optimistic Updates guides; the `MutationOptions` callback types in `query-core`.
- Versions: `@tanstack/react-query` 5.102.0; React 19.2.3; Vitest 4.0.18; React Testing Library 16.3.2; TypeScript 5.9.3.
- Consulted: 2026-08-23.
- Curriculum authority: DALT Fullstack theory curriculum, Batch 10, FS08.3.
- DALT files inspected: `state-architecture-lab`, the Part 08 track manifest, and the former FS08.2 page.
- Extracted material: invalidation, pending-state honesty, failure classification, and the optimism trade-off from the former FS08.2. Its four-callback section is corrected here: the current signatures carry `onMutateResult` third and a mutation context last.
- Verified in the lab: deleting `onError` leaves the naive rollback test green, because `onSettled` refetches the same value. The shipped test blocks reads so the rollback is what is being measured.
</details>
