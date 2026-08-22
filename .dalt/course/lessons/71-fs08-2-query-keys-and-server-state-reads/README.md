# FS08.2 — Query keys and server-state reads

Lesson ID: FS08.2
Lesson format: Concise theory
Part: 08 — Server and application state
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Applied
Prerequisites: FS08.1
Last reviewed: 2026-08-23

We will give every remote fact one address in a query cache, so that several screens read one answer instead of collecting private copies.

> **Helpful background:** [Classify local, URL, session, and server state](/learn/lessons/45-fs08-1-client-state-versus-server-state)

## What we will learn

- create one query client and address remote facts with query keys;
- keep the query function boring, above the API module from FS04.4;
- render the five states a read can actually be in, including the one most screens forget.

## What the cache is, and what it is not

A query cache is a shared place to keep the last answer the server gave, plus a policy for when to ask again. It is not a database, it is not a replacement for our API module, and it never decides who is allowed to see anything — DALT still authenticates and authorizes every request.

FS08.1 ended with two components each holding their own copy of `ISS-1`. A cache fixes that by giving the fact one name that both components look it up by. That is the whole idea; everything else is configuration.

Install it as an exact version, and commit the lockfile:

```bash
npm install @tanstack/react-query@5.102.0
```

## One client, created once

The client holds the cache. Create it outside render, so that a re-render cannot throw the cache away:

```tsx
const queryClient = new QueryClient();

createRoot(document.getElementById('root')!).render(
  <QueryClientProvider client={queryClient}>
    <App />
  </QueryClientProvider>,
);
```

In tests, do the opposite: a fresh client per test, with retries off, so one test's cache cannot reach the next and a deliberate failure does not take three seconds to arrive.

```tsx
export function createTestQueryClient(): QueryClient {
  return new QueryClient({ defaultOptions: { queries: { retry: false } } });
}
```

## A key is an address

Every input that changes the response belongs in the key, and nothing else does. Arrays keep the hierarchy readable:

```ts
export const queryKeys = {
  issues: (projectId: string, status: IssueStatus) =>
    ['projects', projectId, 'issues', { status }] as const,
  issue: (issueId: string) => ['issues', issueId] as const,
};
```

Using `['issues']` for every list and trusting the component's own variables to explain the difference does not work: the cache cannot read a closure, so one project's list is served for another's. Adding unrelated display preferences to the key is the opposite error — it creates a separate cache entry for a value the server never saw.

## The query function stays boring

A query function requests and returns data, or rejects. It does not navigate, set state, or turn a failure into an empty array:

```tsx
const issue = useQuery({
  queryKey: queryKeys.issue(issueId),
  queryFn: () => api.getIssue(issueId),
});
```

Notice it calls `api.getIssue`, not `fetch`. The transport boundary from FS04.4 still owns URLs, status codes, and parsing; the cache sits above it. Writing `fetch` inside a `queryFn` quietly dissolves that boundary.

When an input is not ready, do not invent one. Disable the query:

```tsx
const parsed = parseIssueId(issueId);
const issue = useQuery({
  queryKey: queryKeys.issue(parsed ?? 'none'),
  queryFn: () => api.getIssue(parsed as string),
  enabled: parsed !== null,
});
```

## Five states, not four

Most screens handle loading, error, and data. There are five:

```text
first load        no data yet, a request in flight
success           data to render
empty             a successful response with no rows — not an error
hard failure      the request failed and there is nothing to show
soft failure      a refresh failed while a good snapshot is still on screen
```

The last one is the one that separates an application people trust on a train from one that blanks at the first dropped packet. `isError` and `data` are true at the same time, so the order of the branches decides whether we throw away a useful screen:

```tsx
if (issues.isPending) return <p>Loading issues…</p>;

if (issues.isError && issues.data === undefined) {
  return <button onClick={() => void issues.refetch()}>Try again</button>;
}

return (
  <div>
    {issues.isError ? <p role="alert">Showing the last known list — could not refresh.</p> : null}
    <IssueList issues={issues.data} />
  </div>
);
```

One naming trap while we are here. `isPending` means *there is no data yet*. `isLoading` is narrower: pending **and** actually fetching. A disabled query is pending and not loading, so a spinner driven by `isPending` alone never disappears.

## Freshness is a decision

A query is stale the moment it resolves, unless we say otherwise. Stale data is still shown immediately; it is refetched in the background on remount, refocus, or reconnect. Two durations answer different questions:

```ts
useQuery({ queryKey: queryKeys.issue(issueId), queryFn: ..., staleTime: 10_000 });
useQuery({ queryKey: ['workspaces'], queryFn: ..., staleTime: 60 * 60_000 });
```

`staleTime` is "how wrong am I willing to be?". `gcTime` — five minutes by default — is "how long do I keep an unused snapshot for a fast return visit?". An issue changes while people work; a workspace list does not change all afternoon. There is no universally correct number, and choosing one on purpose is most of what configuring a cache means.

One boundary is not negotiable: a cached response is a snapshot of what the server told *this* session, so clear it on sign-out, then navigate away.

```tsx
await api.logout();
queryClient.clear();
navigate('/login', { replace: true });
```

## Try it

**Workspace:** continue in the Part 08 lab, or copy a clean starter:

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/state-architecture-lab/starter \
  .dalt/workspace/fs08-state-architecture
cd .dalt/workspace/fs08-state-architecture
npm ci
```

**Starting state:** `src/queryKeys.ts` holds the addresses, `src/IssueQueries.tsx` holds three small readers, and `src/testQueryClient.tsx` builds a per-test client.

```bash
npm run test:queries
npm run typecheck
```

**Expected result:** five tests pass. They prove that two readers of `ISS-1` cause exactly one request, that a different status is a different address while a fresh snapshot is reused without refetching, that an invalid id never reaches the API and reports `pending=true loading=false`, that a first-load failure offers a retry that succeeds, and that a failed refresh keeps the last known list on screen.

Now change the list's `queryKey` to a constant `['issues']`. The second test fails: the closed list is served from the open list's address.

**Reset:** delete the workspace copy, or keep it for FS08.3.

## What to notice

`api.calls` is the honest evidence here. In FS08.1 the same screen produced `getIssue:ISS-1` twice; with one address it appears once, and both components render the same title.

The third test also settles the naming trap by printing the flags rather than describing them: a disabled query reports `pending=true loading=false fetching=false`.

## Common mistakes

- Writing `fetch` inside a `queryFn` and losing the API boundary built in Part 04.
- Setting `staleTime: Infinity` to stop background refetching, then wondering why the screen shows yesterday's data.
- Rendering the error branch before checking whether usable data is still present.

## Check your understanding

1. Why does a project id belong in the query key rather than only in the query function?
2. What does `enabled` express that an early `return` in the component cannot?
3. Which two states are both true during a failed refresh, and what should the screen do?
4. What is the difference between `staleTime` and `gcTime`?

<details><summary>Check your answers</summary>

1. The cache addresses data by key alone; it cannot see the variables a closure captured, so two projects would share one entry.
2. It keeps the hook call unconditional — the Rules of Hooks — while still preventing a request whose input is not valid.
3. `isError` and a defined `data`. Keep the snapshot on screen and say plainly that refreshing failed.
4. `staleTime` is how long an answer is trusted without rechecking; `gcTime` is how long an unused answer is kept in memory before being discarded.
</details>

## Next

Next we will write through the same cache, and decide when optimism is worth its risk.

<details><summary>Maintainer source record</summary>

- Source dossier: `FSO_PART_06.md`, sections 25–34.
- Official sources: TanStack Query v5 `useQuery` reference, Query Keys, Caching, Important Defaults, Disabling/Pausing Queries, and Testing guides.
- Versions: `@tanstack/react-query` 5.102.0; React 19.2.3; Vitest 4.0.18; React Testing Library 16.3.2; TypeScript 5.9.3.
- Consulted: 2026-08-23.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 10, FS08.2.
- DALT files inspected: `state-architecture-lab`, the Part 08 track manifest, and the former FS08.1 page.
- Extracted material: the query-client, query-key, boring-query-function, five-state rendering, freshness-policy, and sign-out cache-clearing material from the former FS08.1.
- Verified in the lab: a disabled query reports `isPending` true with `isLoading` false; a constant key serves one list from another's address.
</details>
