# Add priority and due dates

Assignment tells us who owns the next step. Priority and a due date tell the team how
to sequence that work. We will store those as different concepts and keep a missing
due date different from an invented deadline.

> **Helpful background:** PostgreSQL documents `DATE` as a calendar date without a
> time of day in its [date/time types](https://www.postgresql.org/docs/18/datatype-datetime.html).
> A native [HTML date input](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/input/date)
> submits either an ISO `YYYY-MM-DD` value or an empty string.

## Extend the issue schema

Create `database/migrations/010_plan_issue_priority_and_due_date.sql`:

```sql
ALTER TABLE issues
    ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT 'medium',
    ADD COLUMN due_date DATE NULL,
    ADD CONSTRAINT issues_priority_check
        CHECK (priority IN ('low', 'medium', 'high', 'urgent'));

CREATE INDEX issues_open_due_date_index
    ON issues (due_date, id DESC)
    WHERE status = 'open' AND due_date IS NOT NULL;
```

The default safely upgrades every existing issue to medium priority. A nullable
`DATE` records exactly the product fact we need—one calendar day, or no deadline—so
we do not convert midnight through time zones. PostgreSQL also rejects a priority
outside our vocabulary even if a future controller forgets validation.

The partial index contains only dated, open work. That is the subset an upcoming
dashboard will ask for; closed or undated rows do not enlarge it.

Run:

```bash
php artisan migrate
```

## Validate the calendar before writing

Add a `planning()` method to `app/Http/IssueData.php`. It gives missing priority the
same default as PostgreSQL, turns a blank date into `null`, and returns field errors:

```php
$priority = is_string($priorityInput) && $priorityInput !== ''
    ? $priorityInput
    : 'medium';
$dueDate = is_string($dueDateInput) && $dueDateInput !== ''
    ? $dueDateInput
    : null;

if (!in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
    $errors['priority'] = 'Choose low, medium, high, or urgent.';
}

if ($dueDate !== null) {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate);
    if ($parsed === false || $parsed->format('Y-m-d') !== $dueDate) {
        $errors['dueDate'] = 'Use a real calendar date.';
    }
}
```

The format round trip matters. A string can resemble a date while naming a day that
does not exist. We validate before asking PostgreSQL so the browser receives a useful
422 field response instead of a database exception.

Call this method in both issue creation and editing, merge its errors with title and
description errors, and add `priority` and `due_date` to the SQL parameters:

```php
$planning = IssueData::planning(
    $request->input('priority'),
    $request->input('due_date'),
);

$errors = [...$errors, ...$planning['errors']];
```

Extend `IssueData::SELECT` and `present()` so every issue response includes:

```php
'priority' => (string) ($issue['priority'] ?? 'medium'),
'dueDate' => ($issue['due_date'] ?? null) === null
    ? null
    : (string) $issue['due_date'],
```

We keep SQL snake case at the database boundary and expose the React-facing
`dueDate` consistently from one presenter.

## Add typed planning fields to React

In `resources/app/project-page-data.ts`, add the closed priority union and new issue
fields:

```tsx
export type IssuePriority = 'low' | 'medium' | 'high' | 'urgent'

export type Issue = {
  // existing fields
  priority: IssuePriority
  dueDate: string | null
}
```

Runtime parsing still checks the priority against those four values. Extend create
and update requests with `priority` and `due_date`; an empty date string deliberately
asks the server to store `NULL`.

Add native controls to both create and edit forms:

```tsx
<label htmlFor="issue-priority">Priority</label>
<select id="issue-priority" value={priority} onChange={changePriority}>
  <option value="low">Low</option>
  <option value="medium">Medium</option>
  <option value="high">High</option>
  <option value="urgent">Urgent</option>
</select>

<label htmlFor="issue-due-date">Due date</label>
<input
  id="issue-due-date"
  type="date"
  value={dueDate}
  onChange={(event) => setDueDate(event.target.value)}
/>
```

Native labels and controls remain reachable by keyboard and expose useful semantics
without a custom picker. Initialize edit state from the confirmed issue, and reset a
new issue form to medium priority with no date.

## Show overdue work without shifting its date

Display priority and the ISO date in issue rows. On the detail page, compare the
stored date to a local calendar key assembled from `getFullYear()`, `getMonth()`, and
`getDate()`. Do not parse `YYYY-MM-DD` into a JavaScript midnight timestamp merely to
compare days; UTC conversion can move the visible date for some users.

Only an open issue strictly before today is overdue:

```tsx
const overdue = issue.status === 'open'
  && issue.dueDate !== null
  && issue.dueDate < todayKey()
```

A closed issue keeps its historical date but no longer displays an overdue warning.

## Prove valid, invalid, and absent values

The backend test submits an unknown priority and February 31 and expects both field
errors. It then creates urgent dated work, updates it to low priority, and clears the
date. The React test proves the same past date is marked overdue while open and not
while closed.

Run:

```bash
php artisan test \
  tests/Feature/IssueApiTest.php \
  tests/Feature/WorkspaceAuthorizationTest.php
npm run typecheck
npm run lint
npm test -- --run resources/app/issue-workflow.test.tsx
```

The backend passes 26 tests with 193 assertions, and the focused React file passes
nine tests. Our issues now express responsibility and urgency. Next we will create
workspace-owned labels so teams can organize work in more than one dimension.
