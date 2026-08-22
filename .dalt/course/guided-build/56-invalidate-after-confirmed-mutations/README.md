# Invalidate after confirmed mutations

Our reads now have cache identities, but several writes still repair component state
by hand. We will move the issue workflow to `useMutation` and refresh only the cached
resources a successful server change can make stale.

> **Helpful background:** TanStack Query documents
> [invalidations from mutations](https://tanstack.com/query/latest/docs/framework/react/guides/invalidations-from-mutations)
> and [updates from mutation responses](https://tanstack.com/query/latest/docs/framework/react/guides/updates-from-mutation-responses).

## Extend the issue key family

Comments and activity belong to one issue, but they change independently. Add their
keys to `resources/app/issue-queries.ts`:

```ts
export const issueKeys = {
  all: ['issues'] as const,
  collections: () => [...issueKeys.all, 'collection'] as const,
  collection: (workspaceId: number, projectId: number, search: string) =>
    [...issueKeys.collections(), workspaceId, projectId, { search }] as const,
  details: () => [...issueKeys.all, 'detail'] as const,
  detail: (workspaceId: number, projectId: number, issueId: number) =>
    [...issueKeys.details(), workspaceId, projectId, issueId] as const,
  comments: (workspaceId: number, projectId: number, issueId: number) =>
    [...issueKeys.detail(workspaceId, projectId, issueId), 'comments'] as const,
  activity: (workspaceId: number, projectId: number, issueId: number) =>
    [...issueKeys.detail(workspaceId, projectId, issueId), 'activity'] as const,
}
```

The hierarchy gives us two useful levels. `issueKeys.collections()` matches every
filtered and paginated issue list. The full activity key matches one timeline only.

Add query options that call the existing fetch functions:

```ts
comments(workspaceId: number, projectId: number, issueId: number) {
  const base = `/api/workspaces/${workspaceId}/projects/${projectId}/issues/${issueId}`
  return queryOptions({
    queryKey: issueKeys.comments(workspaceId, projectId, issueId),
    queryFn: ({ signal }) => fetchComments(base, signal),
  })
},

activity(workspaceId: number, projectId: number, issueId: number) {
  const base = `/api/workspaces/${workspaceId}/projects/${projectId}/issues/${issueId}`
  return queryOptions({
    queryKey: issueKeys.activity(workspaceId, projectId, issueId),
    queryFn: ({ signal }) => fetchActivity(base, signal),
  })
},
```

## Invalidate only after creation succeeds

In `CreateIssueForm` inside `resources/app/ProjectPage.tsx`, create the mutation:

```tsx
const queryClient = useQueryClient()
const createMutation = useMutation({
  mutationFn: (values: {
    title: string
    description: string
    assigneeId: string
    priority: IssuePriority
    dueDate: string
  }) => createIssue(data, values),
  onSuccess: () => queryClient.invalidateQueries({
    queryKey: issueKeys.collections(),
  }),
})
```

Keep controlled input values and validation messages as local state. Replace the
direct request in `submit` with:

```ts
const result = await createMutation.mutateAsync({
  title,
  description,
  assigneeId,
  priority,
  dueDate,
})
```

Only after that promise resolves do we clear the fields and show `result.message`.
The `onSuccess` callback then marks every issue collection stale. The currently
visible URL refetches; inactive filter and page entries are stale for their next use.

Remove the old `addIssue` callback and its hand-written cache shape. A created issue
might not belong on the current page: it may fail an active status, assignee, or label
filter. The server is the right place to recompute that result.

Use mutation state for the submit button instead of maintaining a second boolean:

```tsx
<button disabled={createMutation.isPending}>
  {createMutation.isPending ? 'Creating…' : 'Create issue'}
</button>
```

A 422 rejection throws `IssueValidationError`. `onSuccess` never runs, so a rejected
form causes no collection request and preserves every attempted value.

## Combine a confirmed detail update with invalidation

A status response already contains the complete updated issue. In
`resources/app/IssuePage.tsx`, use that response immediately and invalidate the other
affected resources:

```tsx
const statusMutation = useMutation({
  mutationFn: ({ issue, status }: {
    issue: Issue
    status: 'open' | 'closed'
  }) => changeIssueStatus(data, issue.id, status),

  onSuccess: async (result) => {
    queryClient.setQueryData(detailOptions.queryKey, result.issue)

    await Promise.all([
      queryClient.invalidateQueries({ queryKey: issueKeys.collections() }),
      queryClient.invalidateQueries({
        queryKey: issueKeys.activity(
          data.workspace.id,
          data.project.id,
          parsedIssueId,
        ),
      }),
    ])
  },
})
```

`setQueryData` is appropriate here because the server returned the exact canonical
detail. Lists still need invalidation: some should remove the issue after a status
change, others should add it, and their pagination totals may change. Activity also
needs its new event.

Returning the Promise from `onSuccess` keeps `isPending` true until the active stale
resources have refreshed. The button cannot claim completion while its nearby
timeline is still knowingly old.

Use the same pattern in `resources/app/EditIssuePage.tsx`: `useMutation` calls
`updateIssue`, writes the returned issue to its detail key, and invalidates issue
collections plus this issue's activity. Navigate to the detail screen only after
that synchronization resolves.

## Remove deleted detail data

In `resources/app/DeleteIssuePage.tsx`, a successful delete has no updated object to
store. Remove the deleted detail family, invalidate collections, then navigate:

```tsx
const deleteMutation = useMutation({
  mutationFn: () => deleteIssue(data, parsedIssueId),
  onSuccess: async () => {
    queryClient.removeQueries({
      queryKey: issueKeys.detail(
        data.workspace.id,
        data.project.id,
        parsedIssueId,
      ),
    })
    await queryClient.invalidateQueries({
      queryKey: issueKeys.collections(),
    })
  },
})
```

Because comment and activity keys begin with the detail key, they disappear with the
deleted issue too. Returning to the project cannot briefly resurrect the cached row.

Again, use `deleteMutation.isPending` for disabled and `Deleting…` states. The old
manual request boolean is no longer needed.

## Give conversation reads to the same cache

In `resources/app/CommentsPanel.tsx`, replace the fetch effect and local comments
array:

```tsx
const commentsQuery = useQuery(
  issueQueries.comments(data.workspace.id, data.project.id, issueId),
)
const comments = commentsQuery.data ?? []
```

Render `Loading comments…` only while pending, an alert on error, the conversation
only on success, and the existing first-comment message for a successful empty list.
This avoids saying both “Loading” and “No comments” at once.

Creating or deleting a comment changes two resources: the ordered comment list and
the append-only activity timeline. Share one refresh function:

```tsx
const refreshConversation = async () => {
  await Promise.all([
    queryClient.invalidateQueries({
      queryKey: issueKeys.comments(workspaceId, projectId, issueId),
    }),
    queryClient.invalidateQueries({
      queryKey: issueKeys.activity(workspaceId, projectId, issueId),
    }),
  ])
}

const createMutation = useMutation({
  mutationFn: (body: string) => createComment(base, csrfToken, body),
  onSuccess: refreshConversation,
})

const deleteMutation = useMutation({
  mutationFn: (id: number) => deleteComment(base, id, csrfToken),
  onSuccess: refreshConversation,
})
```

We deliberately do not invent an optimistic comment or remove one before the server
agrees. An optimistic interaction needs a snapshot and rollback story; this form is
clear and fast without that extra failure mode.

In `resources/app/ActivityPanel.tsx`, replace its effect and two local arrays with:

```tsx
const query = useQuery(issueQueries.activity(workspaceId, projectId, issueId))
const events = query.data ?? []
```

Its pending, error, empty, and timeline branches now reflect the shared query.

## Prove success and failure choose different cache work

Make the MSW collection and comment handlers stateful, just like a real server. The
tests now prove:

- a rejected create keeps its form values and makes no second collection request;
- a confirmed create triggers exactly one collection refetch;
- a status change updates the detail and refetches activity;
- comment creation and deletion each refetch comments and activity;
- edit and delete still navigate only after server confirmation.

Run all frontend checks, because the provider and query cache cross several routed
screens:

```bash
npm run typecheck
npm run lint
npm test
```

All 37 frontend tests pass. The issue workflow now has one request lifecycle and one
cache vocabulary instead of fetch effects plus manual copies. In the next lesson we
will build a dashboard and add its key to the mutations whose results affect those
summary views.
