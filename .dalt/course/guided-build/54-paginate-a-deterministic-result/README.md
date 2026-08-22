# Paginate a deterministic issue result

Search makes the right issues easier to find, but the server still returns every
match. We will add bounded pages whose links preserve the active search, filters,
sorting, and page size.

> **Helpful background:** PostgreSQL documents [`LIMIT` and `OFFSET`](https://www.postgresql.org/docs/18/queries-limit.html)
> together with an important warning: pagination needs a predictable `ORDER BY`.

## Bound the public page inputs

Open `app/Http/controllers/api/issues/index.php`. Read `page` and `perPage` beside the
filters from the previous lesson:

```php
$pageInput = $request->query('page');
$requestedPage = is_string($pageInput)
    && preg_match('/\A[1-9]\d*\z/', $pageInput) === 1
        ? min((int) $pageInput, 10_000)
        : 1;

$perPageInput = $request->query('perPage');
$perPage = is_string($perPageInput)
    && preg_match('/\A[1-9]\d*\z/', $perPageInput) === 1
        ? min(max((int) $perPageInput, 1), 50)
        : 10;
```

A missing or malformed page becomes page 1. The server caps page numbers at 10,000
and page sizes at 50, so one request cannot ask PHP to serialize an unlimited result.
These are product limits, not values the browser is trusted to enforce.

## Count before choosing the offset

After assembling the filter conditions and parameters, reuse them for a filtered
count:

```php
$where = implode(' AND ', $conditions);

$total = (int) $database->query(
    'SELECT COUNT(*) AS aggregate FROM issues WHERE ' . $where,
    $parameters,
)->findOrFail()['aggregate'];

$lastPage = max(1, (int) ceil($total / $perPage));
$page = min($requestedPage, $lastPage);
$offset = ($page - 1) * $perPage;
```

We choose at least one logical page even for zero matches. If a user opens page 4 and
deletion has reduced the result to three pages, the server returns page 3 instead of
an unexplained empty list.

The count uses exactly the same `$where` and `$parameters` as the collection. A total
for the whole project would make the page controls disagree with a filtered list.

## Fetch one stable window

Extend the issue query with bound limit and offset values:

```php
$issues = $database->query(
    'SELECT ' . IssueData::SELECT . '
     FROM issues' . IssueData::JOIN . '
     WHERE ' . $where . '
     ORDER BY ' . $order . '
     LIMIT :limit OFFSET :offset',
    [...$parameters, 'limit' => $perPage, 'offset' => $offset],
)->get();
```

This is safe because every ordering option from Lesson 53 ends in unique `issues.id`:

```php
'due' => 'issues.due_date ASC NULLS LAST, issues.id DESC',
'priority' => "CASE issues.priority
    WHEN 'urgent' THEN 1
    WHEN 'high' THEN 2
    WHEN 'medium' THEN 3
    ELSE 4 END, issues.id DESC",
```

Without the ID tie-breaker, two urgent issues or two issues due on the same date
could exchange positions between requests. A row might appear twice or be skipped as
we move between pages.

Return the page metadata with the collection:

```php
'pagination' => [
    'page' => $page,
    'perPage' => $perPage,
    'total' => $total,
    'lastPage' => $lastPage,
],
```

## Parse pagination at the network boundary

In `resources/app/project-page-data.ts`, add:

```ts
export type IssuePagination = {
  page: number
  perPage: number
  total: number
  lastPage: number
}

export type IssueCollection = {
  issues: Issue[]
  filters: IssueFilters
  hasAnyIssues: boolean
  pagination: IssuePagination
}
```

Extend `parseIssuesResponse` to require positive integers for `page`, `perPage`, and
`lastPage`. `total` is slightly different because zero is valid:

```ts
const total = value.pagination.total
if (typeof total !== 'number' || !Number.isInteger(total) || total < 0) {
  throw new Error('Invalid issue total.')
}

return {
  // issues, filters, and hasAnyIssues...
  pagination: {
    page: integerAt(value.pagination, 'page'),
    perPage: integerAt(value.pagination, 'perPage'),
    total,
    lastPage: integerAt(value.pagination, 'lastPage'),
  },
}
```

The parser prevents a malformed server response from leaking impossible values into
our navigation.

## Let the filter form choose a page size

Add a labeled page-size selector to `IssueFiltersForm` in
`resources/app/ProjectPage.tsx`:

```tsx
<label className="text-xs font-bold">
  Per page
  <select
    name="perPage"
    defaultValue={searchParams.get('perPage') ?? '10'}
  >
    <option value="5">5</option>
    <option value="10">10</option>
    <option value="20">20</option>
    <option value="50">50</option>
  </select>
</label>
```

Include `perPage` when the form builds its next `URLSearchParams`, but omit the
default value of 10. Do not copy the current `page` when applying filters: a narrower
result should begin again on page 1.

## Preserve the entire query when moving pages

Create a small `IssuePagination` component. Its link builder clones the current
parameters and changes only `page`:

```tsx
function href(nextPage: number) {
  const next = new URLSearchParams(searchParams)

  if (nextPage === 1) next.delete('page')
  else next.set('page', String(nextPage))

  const query = next.toString()
  return query === '' ? '.' : `?${query}`
}
```

Now render an accessible navigation landmark:

```tsx
<nav aria-label="Issue pages">
  <p>
    Page {page} of {lastPage} · {total} {total === 1 ? 'issue' : 'issues'}
  </p>

  {page > 1
    ? <Link to={href(page - 1)}>Previous</Link>
    : <span aria-disabled="true">Previous</span>}

  {page < lastPage
    ? <Link to={href(page + 1)}>Next</Link>
    : <span aria-disabled="true">Next</span>}
</nav>
```

Use the Tailwind classes already established by the project for spacing, borders,
focus indication, and muted disabled text. Render this component after the issue
panel only when the collection is ready. It returns nothing when `total` is zero.

A Next link from this URL:

```text
?status=open&priority=urgent&sort=due&perPage=5
```

produces this URL:

```text
?status=open&priority=urgent&sort=due&perPage=5&page=2
```

Nothing about the active view is accidentally discarded.

## Prove the page boundaries

Extend `tests/Feature/IssueApiTest.php` with five matching issues and `perPage=2`.
Assert the IDs on the first, middle, and last pages. Ask for page 999 and confirm the
server returns the real last page. Give several rows the same due date and prove the
ID tie-breaker keeps their order stable.

Then delete the only row on page 3 and request page 3 again. The response should now
say page 2 of 2 and return the new last window.

In `resources/app/issue-workflow.test.tsx`, begin with `status=open&perPage=1`, follow
Next, and assert that `status`, `perPage`, and the new `page=2` all remain in the
router URL.

```bash
php vendor/bin/pest tests/Feature/IssueApiTest.php
npm run typecheck
npm run lint
npm test -- --run resources/app/issue-workflow.test.tsx
```

The backend passes 20 focused tests with 120 assertions. The React workflow passes
14 tests. Offset pagination is easy to inspect and correct for the tracker we have
today. Very large offsets can become expensive, but we will measure that later
instead of teaching cursor machinery before our product needs it.
