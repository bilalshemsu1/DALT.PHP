# Search, filter, and sort issues

Our project page becomes harder to use as its issue list grows. We will make that
list searchable and filterable, and we will put every choice in the URL so a refresh
or copied link opens the same view.

> **Helpful background:** PostgreSQL documents its case-insensitive
> [`ILIKE` operator](https://www.postgresql.org/docs/18/functions-matching.html), and
> React Router explains how [`useSearchParams`](https://reactrouter.com/api/hooks/useSearchParams)
> reads and navigates with URL query parameters.

## Read only supported query values

Open `app/Http/controllers/api/issues/index.php`. After we authorize the workspace
and project, read the search parameters from DALT's request:

```php
$textInput = $request->query('q');
$text = is_string($textInput) ? mb_substr(trim($textInput), 0, 100) : '';

$statusInput = $request->query('status');
$status = is_string($statusInput)
    && in_array($statusInput, ['open', 'closed'], true)
        ? $statusInput
        : '';

$sortInput = $request->query('sort');
$sort = is_string($sortInput)
    && in_array($sortInput, ['newest', 'oldest', 'due', 'priority'], true)
        ? $sortInput
        : 'newest';
```

Use the same pattern for `priority`, `due`, `assignee`, and `label`. Priority accepts
the four values already protected by our database constraint. Due accepts `overdue`,
`upcoming`, or `none`. Assignee and label accept a positive integer; assignee also
accepts `unassigned`.

The server decides which values are meaningful. An unknown status becomes no status
filter, and an unknown sort becomes `newest`. We never copy an arbitrary sort string
into SQL.

## Assemble parameters and trusted SQL fragments separately

Start with the project scope that every request must keep:

```php
$conditions = ['issues.project_id = :project_id'];
$parameters = ['project_id' => $project['id']];

if ($text !== '') {
    $conditions[] = '(
        issues.title ILIKE :title_search
        OR issues.description ILIKE :description_search
    )';
    $parameters['title_search'] = '%' . $text . '%';
    $parameters['description_search'] = '%' . $text . '%';
}

if ($status !== '') {
    $conditions[] = 'issues.status = :status';
    $parameters['status'] = $status;
}
```

Values stay in `$parameters`, including the `%` wildcards used by `ILIKE`. The SQL
structure comes only from strings we wrote. Continue with priority and assignee:

```php
if ($priority !== '') {
    $conditions[] = 'issues.priority = :priority';
    $parameters['priority'] = $priority;
}

if ($assignee === 'unassigned') {
    $conditions[] = 'issues.assignee_id IS NULL';
} elseif ($assignee !== '') {
    $conditions[] = 'issues.assignee_id = :assignee_id';
    $parameters['assignee_id'] = (int) $assignee;
}
```

A label belongs to a workspace, so keep that scope inside the existence check:

```php
if ($label !== '') {
    $conditions[] = 'EXISTS (
        SELECT 1 FROM issue_labels
        JOIN labels ON labels.id = issue_labels.label_id
        WHERE issue_labels.issue_id = issues.id
          AND labels.workspace_id = :label_workspace_id
          AND labels.id = :label_id
    )';
    $parameters['label_workspace_id'] = $workspace['id'];
    $parameters['label_id'] = (int) $label;
}
```

The due choices are also server-owned SQL fragments. Overdue and upcoming include
`status = 'open'` because completed work should not remain in an action list:

```php
if ($due === 'overdue') {
    $conditions[] = "issues.status = 'open' AND issues.due_date < CURRENT_DATE";
} elseif ($due === 'upcoming') {
    $conditions[] = "issues.status = 'open' AND issues.due_date >= CURRENT_DATE";
} elseif ($due === 'none') {
    $conditions[] = 'issues.due_date IS NULL';
}
```

## Allow known ordering, with a stable tie-breaker

Map the accepted public name to an SQL fragment:

```php
$order = match ($sort) {
    'oldest' => 'issues.id ASC',
    'due' => 'issues.due_date ASC NULLS LAST, issues.id DESC',
    'priority' => "CASE issues.priority
        WHEN 'urgent' THEN 1
        WHEN 'high' THEN 2
        WHEN 'medium' THEN 3
        ELSE 4 END, issues.id DESC",
    default => 'issues.id DESC',
};
```

`id` is unique, so it settles ties. That detail becomes essential when we paginate in
the next lesson: equal due dates or priorities must not shuffle between requests.

Now execute the assembled query:

```php
$issues = $database->query(
    'SELECT ' . IssueData::SELECT . '
     FROM issues' . IssueData::JOIN . '
     WHERE ' . implode(' AND ', $conditions) . '
     ORDER BY ' . $order,
    $parameters,
)->get();
```

## Explain which kind of empty result we received

An empty project and a search with no matches need different guidance. Run one small
unfiltered count and return both that fact and the canonical applied filters:

```php
$hasAnyIssues = (int) $database->query(
    'SELECT COUNT(*) AS aggregate FROM issues WHERE project_id = :project_id',
    ['project_id' => $project['id']],
)->findOrFail()['aggregate'] > 0;

return Response::json([
    'issues' => array_map(
        static fn (array $issue): array => IssueData::present($issue, $database),
        $issues,
    ),
    'filters' => [
        'q' => $text,
        'status' => $status,
        'assignee' => $assignee,
        'priority' => $priority,
        'label' => $label,
        'due' => $due,
        'sort' => $sort,
    ],
    'hasAnyIssues' => $hasAnyIssues,
]);
```

The returned filters describe what the server actually applied—not merely what an
untrusted URL requested.

## Parse the richer collection in TypeScript

In `resources/app/project-page-data.ts`, add the collection types:

```ts
export type IssueFilters = {
  q: string
  status: string
  assignee: string
  priority: string
  label: string
  due: string
  sort: string
}

export type IssueCollection = {
  issues: Issue[]
  filters: IssueFilters
  hasAnyIssues: boolean
}
```

Update `parseIssuesResponse` to require an array of issues, an object of string
filters, and a boolean `hasAnyIssues`. Change `fetchIssues` so it accepts the current
query string and returns `Promise<IssueCollection>`:

```ts
export async function fetchIssues(
  workspaceId: number,
  projectId: number,
  search: string,
  signal: AbortSignal,
): Promise<IssueCollection> {
  const response = await fetch(
    `/api/workspaces/${workspaceId}/projects/${projectId}/issues${search}`,
    { headers: { Accept: 'application/json' }, signal },
  )
  requireAuthenticatedResponse(response)
  if (!response.ok) throw new Error(`The issues request failed with status ${response.status}.`)
  return parseIssuesResponse(await response.json())
}
```

## Let the URL own the form

In `resources/app/ProjectPage.tsx`, use React Router's search-parameter hook:

```tsx
const [searchParams, setSearchParams] = useSearchParams()
const search = searchParams.toString() === ''
  ? ''
  : `?${searchParams.toString()}`

useEffect(() => {
  const controller = new AbortController()
  setIssuesState({ status: 'loading' })
  void fetchIssues(workspace.id, project.id, search, controller.signal)
    .then((collection) => setIssuesState({ status: 'ready', collection }))
    .catch((error: unknown) => {
      if (!(error instanceof DOMException && error.name === 'AbortError')) {
        setIssuesState({ status: 'error' })
      }
    })
  return () => controller.abort()
}, [workspace.id, project.id, attempt, search])
```

Build a compact `IssueFiltersForm` with labeled search, status, assignee, priority,
label, due, and sort controls. On submit, construct a fresh `URLSearchParams`, omit
empty values and the default `newest` sort, then navigate with `setSearchParams(next)`.
The Clear button navigates with an empty parameter set.

Because navigation owns the state, a URL such as this is durable and shareable:

```text
/workspaces/1/projects/2?status=open&assignee=2&priority=urgent&sort=due
```

Finally, use the server's unfiltered count to choose the empty copy:

```tsx
if (state.collection.issues.length === 0) {
  return <EmptyIssues filtered={state.collection.hasAnyIssues} />
}
```

An empty project invites us to create the first issue. A filtered empty list asks us
to change or clear a filter.

## Prove the boundary

Extend `tests/Feature/IssueApiTest.php` with a combined search, status, assignee,
priority, label, due, and sort request. Then send an injection-shaped search value
and `sort=id; DROP TABLE issues`. The first request returns only the intended issue;
the second returns no matches, falls back to `newest`, and leaves all issue rows
present.

In `resources/app/issue-workflow.test.tsx`, submit status and sort controls, assert
that both the router URL and API request contain them, observe the filtered-empty
message, then clear the URL and see the issue again.

```bash
php vendor/bin/pest tests/Feature/IssueApiTest.php
npm run typecheck
npm run lint
npm test -- --run resources/app/issue-workflow.test.tsx
```

The backend now passes 19 focused tests with 111 assertions, and the React workflow
passes 13 tests. We can find work through a refresh-safe URL. Next we will keep those
same parameters while splitting a deterministic result into pages.
