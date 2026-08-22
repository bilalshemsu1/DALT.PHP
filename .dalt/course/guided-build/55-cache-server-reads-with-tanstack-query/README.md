# Cache server reads with TanStack Query

Our issue collection and detail screen now read the same server resources across
navigation, filters, pagination, and mutations. We will give those repeated reads to
TanStack Query while keeping form values local and identity in `SessionProvider`.

> **Helpful background:** TanStack Query documents
> [query keys](https://tanstack.com/query/latest/docs/framework/react/guides/query-keys),
> [`useQuery`](https://tanstack.com/query/latest/docs/framework/react/reference/useQuery),
> and its [important defaults](https://tanstack.com/query/latest/docs/framework/react/guides/important-defaults).

## Install the server-state library

Run:

```bash
npm install @tanstack/react-query
```

This adds `@tanstack/react-query` to `dependencies`. It does not replace React state.
We still use local state for input values, notices, and whether a disclosure is open.
We still use `SessionProvider` for the signed-in identity. Query owns remote data with
a request lifecycle and cache identity.

## Create one application client

Create `resources/app/query-client.ts`:

```ts
import { QueryClient } from '@tanstack/react-query'

export function createAppQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  })
}

export const queryClient = createAppQueryClient()
```

The browser receives one long-lived client. Tests can call the factory so each test
gets an empty cache. We disable automatic retries because our interface already
offers an explicit retry control, and because a session-expired 401 should reach our
central reload boundary immediately rather than being repeated in the background.

We leave the other defaults visible. Data is stale immediately, so revisiting a
mounted resource can show cached data while a background request checks the server.
Inactive data remains cached for TanStack Query's default collection period.

## Provide the client above routed screens

In `resources/app/main.tsx`, import the provider and client:

```tsx
import { QueryClientProvider } from '@tanstack/react-query'
import { queryClient } from './query-client'
```

Wrap the existing application screen:

```tsx
<StrictMode>
  <QueryClientProvider client={queryClient}>
    {['auth', 'invitation'].includes(document.body.dataset.page ?? '')
      ? applicationScreen
      : <SessionProvider>{applicationScreen}</SessionProvider>}
  </QueryClientProvider>
</StrictMode>
```

Provider order does not move session identity into the query cache. It only makes the
query client available to the routed screens underneath it.

## Give each server result a complete identity

Create `resources/app/issue-queries.ts`:

```ts
import { queryOptions } from '@tanstack/react-query'
import { fetchIssue, fetchIssues } from './project-page-data'

export const issueKeys = {
  all: ['issues'] as const,
  collections: () => [...issueKeys.all, 'collection'] as const,
  collection: (workspaceId: number, projectId: number, search: string) =>
    [...issueKeys.collections(), workspaceId, projectId, { search }] as const,
  details: () => [...issueKeys.all, 'detail'] as const,
  detail: (workspaceId: number, projectId: number, issueId: number) =>
    [...issueKeys.details(), workspaceId, projectId, issueId] as const,
}
```

A collection key contains the workspace, project, and complete canonical query
string. `?status=open&page=2` is not the same server result as `?status=closed`.
Leaving the search string out would make one filter display another filter's cached
rows.

Now keep keys and request functions together:

```ts
export const issueQueries = {
  collection(workspaceId: number, projectId: number, search: string) {
    return queryOptions({
      queryKey: issueKeys.collection(workspaceId, projectId, search),
      queryFn: ({ signal }) =>
        fetchIssues(workspaceId, projectId, search, signal),
    })
  },

  detail(workspaceId: number, projectId: number, issueId: number) {
    return queryOptions({
      queryKey: issueKeys.detail(workspaceId, projectId, issueId),
      queryFn: ({ signal }) =>
        fetchIssue(workspaceId, projectId, issueId, signal),
    })
  },
}
```

`queryOptions` preserves TypeScript inference when the same definition is used by a
component and, in the next lesson, by the query client. The signal still reaches
`fetch`, so an obsolete request can be cancelled.

## Replace the collection fetch effect

In `resources/app/ProjectPage.tsx`, remove the `attempt` counter and the effect that
manually creates an `AbortController`. Ask the cache for the URL-owned collection:

```tsx
const search = searchParams.toString() === ''
  ? ''
  : `?${searchParams.toString()}`

const collectionOptions = issueQueries.collection(
  workspace.id,
  project.id,
  search,
)
const issuesQuery = useQuery(collectionOptions)
```

Translate the query result into the presentation states our existing panel already
understands:

```tsx
const issuesState: IssuesState = issuesQuery.isPending
  ? { status: 'loading' }
  : issuesQuery.isError
    ? { status: 'error' }
    : { status: 'ready', collection: issuesQuery.data }
```

The retry button now calls the query directly:

```tsx
<IssuesPanel
  state={issuesState}
  workspaceId={workspace.id}
  projectId={project.id}
  retry={() => void issuesQuery.refetch()}
/>
```

Pending and refreshing are different. With no cached result, keep the existing
`Loading issues…` status. When cached rows remain visible during a background request,
add a quieter live status:

```tsx
{issuesQuery.isFetching && !issuesQuery.isPending && (
  <p className="mt-3 text-xs font-semibold text-muted" role="status">
    Refreshing issues…
  </p>
)}
```

The user does not lose useful content merely because the browser is checking it.

For this one intermediate lesson, creation can place its server-confirmed issue into
the exact current collection with `queryClient.setQueryData`. We will replace that
special-case synchronization with mutation invalidation in the next lesson.

## Move issue detail reads incrementally

In `resources/app/IssuePage.tsx`, remove its fetch effect and use the detail query:

```tsx
const validIssueId = Number.isInteger(parsedIssueId) && parsedIssueId > 0
const detailOptions = issueQueries.detail(
  data.workspace.id,
  data.project.id,
  parsedIssueId,
)

const issueQuery = useQuery({
  ...detailOptions,
  enabled: validIssueId,
})
```

An invalid route ID never starts a request. Derive loading, error, and ready states
from `issueQuery` just as we did for the collection. The existing 401 guard inside
`fetchIssue` remains the single session-expiry behavior.

After the current direct status request succeeds, place its confirmed issue under the
detail key:

```ts
queryClient.setQueryData(detailOptions.queryKey, result.issue)
```

This preserves the behavior while reads move first. We are intentionally not
rewriting every mutation in the same lesson.

## Give tests a fresh cache

In `resources/app/issue-workflow.test.tsx`, create a client inside `renderAt` and wrap
the router:

```tsx
const queryClient = createAppQueryClient()

render(
  <QueryClientProvider client={queryClient}>
    <SessionProvider>
      <RouterProvider router={router} />
    </SessionProvider>
  </QueryClientProvider>,
)
```

The tests now prove four server-state behaviors:

- changing filters creates two independent collection entries;
- returning from issue detail shows the cached list immediately while it refreshes;
- a first-load failure renders an alert and makes only one request;
- the visible Try again button makes the second request.

Run the frontend gate:

```bash
npm run typecheck
npm run lint
npm test -- --run resources/app/issue-workflow.test.tsx
```

All 16 issue workflow tests pass. We now have one owner for repeated issue reads,
without adding Redux, Zustand, or a second identity store. Next we will move writes to
mutations and invalidate the smallest affected cache families only after the server
confirms success.
