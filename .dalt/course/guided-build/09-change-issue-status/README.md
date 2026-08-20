Our issues can be created and opened, but they can never leave their initial `open`
state. In this lesson we will add the application's first update: closing completed
work and reopening it when more work is needed.

The visible change is one button. Behind it, we need a protected POST request, the
same three-resource ownership chain used by the detail page, a strict set of allowed
values, and a database update that does not create another issue.

## Add a protected update route

Open `routes/routes.php` and register the status route below the issue detail route:

```php
$router->get('/workspaces/{workspace}/projects/{project}/issues/{issue}', 'issues/show.php');
$router->post('/workspaces/{workspace}/projects/{project}/issues/{issue}/status', 'issues/status.php')->only('csrf');
```

The URL still identifies one issue through its workspace and project, then names the
specific part of that issue we intend to change. We use POST because this request
changes stored state. The `csrf` middleware rejects a submission that did not come
with the token from our application session.

The detail page remains a GET route. Reading and changing the same resource are
different operations, so each receives its own route and controller.

## Protect the complete ownership path again

Create `app/Http/controllers/issues/status.php`. Start by resolving and validating
all three route parameters:

```php
<?php

declare(strict_types=1);

use Core\App;
use Core\Database;
use Core\Request;
use Core\Session;

$request = App::resolve(Request::class);
$workspaceId = $request->route('workspace');
$projectId = $request->route('project');
$issueId = $request->route('issue');

if (
    !is_string($workspaceId)
    || preg_match('/\A[1-9]\d*\z/', $workspaceId) !== 1
    || !is_string($projectId)
    || preg_match('/\A[1-9]\d*\z/', $projectId) !== 1
    || !is_string($issueId)
    || preg_match('/\A[1-9]\d*\z/', $issueId) !== 1
) {
    abort(404);
}
```

The link that displayed an issue already passed these boundaries, but the new POST
request stands on its own. A browser tab may be old, and a request can be constructed
without using our interface, so the update must earn the same trust again.

Load the workspace, then require the project to belong to it:

```php
$database = App::resolve(Database::class);
$workspace = $database
    ->query(
        'SELECT id FROM workspaces WHERE id = :id',
        ['id' => (int) $workspaceId],
    )
    ->findOrFail();

$project = $database
    ->query(
        'SELECT id FROM projects WHERE id = :id AND workspace_id = :workspace_id',
        [
            'id' => (int) $projectId,
            'workspace_id' => $workspace['id'],
        ],
    )
    ->findOrFail();
```

This controller only needs the parent IDs, so its queries select less data than the
detail controller. The important conditions remain identical: a real project under
the wrong workspace returns 404 before an issue can be changed.

Continue by loading the issue through the verified project:

```php
$issue = $database
    ->query(
        'SELECT id, title, status FROM issues WHERE id = :id AND project_id = :project_id',
        [
            'id' => (int) $issueId,
            'project_id' => $project['id'],
        ],
    )
    ->findOrFail();
```

We need the current status to recognize a repeated request and the title to produce
useful feedback. The `project_id` condition prevents an existing issue from being
updated through another project's URL.

## Accept an explicit destination

Read the requested status and allow only the two states our product currently knows:

```php
$status = $request->input('status');

if (!is_string($status) || !in_array($status, ['open', 'closed'], true)) {
    abort(422, 'Status must be open or closed.');
}
```

The form will send a hidden input, but hidden does not mean trusted. Anyone can edit
an HTML form or send the request directly. The strict `in_array()` check ensures
values such as `deleted`, an array, or a missing field never reach the update query.
A well-formed request with an unsupported value receives HTTP 422.

We ask for a destination—`open` or `closed`—instead of asking the controller to
“toggle.” Imagine two tabs both showing an open issue. If each blindly toggled the
current database value, submitting both could close and immediately reopen it. An
explicit request for `closed` makes the second submission a harmless repeat.

## Update once, then redirect

Handle the repeated state first, otherwise run the update:

```php
if ($issue['status'] === $status) {
    Session::flash('success', "{$issue['title']} is already {$status}.");
} else {
    $database->query(
        'UPDATE issues SET status = :status WHERE id = :id',
        [
            'status' => $status,
            'id' => $issue['id'],
        ],
    );

    $message = $status === 'closed' ? 'closed' : 'reopened';
    Session::flash('success', "{$issue['title']} was {$message}.");
}
```

The equality branch performs no database write. In the update branch, both values
are bound parameters, and the issue ID came from the record already proven to belong
to this project. `UPDATE` changes the matching row; unlike `INSERT`, it does not add
another record or alter the project's issue count.

Finish the controller with a 303 redirect to the same issue:

```php
return redirect(
    "/workspaces/{$workspace['id']}/projects/{$project['id']}/issues/{$issue['id']}",
    303,
);
```

The redirect turns the successful POST into a new GET. Refreshing the detail page
therefore reads the stored status without submitting the update again, and the flash
message appears for only that first redirected request.

## Derive the next action from the current issue

Open `resources/views/issues/show.view.php`. Extend the opening PHP block after
`$issueStatus`:

```php
$isClosed = $issueStatus === 'closed';
$nextStatus = $isClosed ? 'open' : 'closed';
$actionLabel = $isClosed ? 'Reopen issue' : 'Close issue';
$success = Core\Session::get('success');
$success = is_string($success) ? $success : null;
```

The database record remains the source of truth. An open issue prepares a request
for `closed`; a closed issue prepares a request for `open`. The visible label follows
the same decision, so the button always describes what submitting it will do.

Add the success notice and closed-state styles to the existing `<style>` block:

```css
.notice { margin:24px 0 0; padding:12px 14px; border-radius:10px; background:var(--accent-soft); color:#075b43; font-size:14px; font-weight:650; overflow-wrap:anywhere; }
.notice + .issue-heading { margin-top:24px; }
.status-closed { background:#e9ecef; color:#46515c; }
```

Open work keeps the established green status. Closed work becomes neutral, making
the state difference visible without treating completion as an error. When a notice
exists, the adjacent-heading rule keeps the vertical rhythm tighter.

Below the back link and before `.issue-heading`, render the flash message:

```php
<?php if ($success !== null): ?>
  <p class="notice" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
```

The stored issue title is escaped before it returns to HTML. `role="status"` lets
assistive technology announce the result without moving keyboard focus.

Update the status pill in the issue heading:

```php
<span class="status <?= $isClosed ? 'status-closed' : '' ?>"><?= ucfirst(htmlspecialchars($issueStatus, ENT_QUOTES, 'UTF-8')) ?></span>
```

The text and visual treatment now change together. We do not rely on color alone:
the pill explicitly says **Open** or **Closed**.

## Add the status action beside the context

The detail page's right column already contains project and workspace context. Wrap
that existing content in a plain `<aside>`, keeping the Context heading and list
inside their own section:

```php
<aside>
  <section class="context" aria-labelledby="context-title">
    <h2 id="context-title">Context</h2>
    <ul class="context-list">
      <li>
        <span class="context-label">Project</span>
        <a class="context-link" href="/workspaces/<?= $workspaceId ?>/projects/<?= $projectId ?>"><?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') ?></a>
      </li>
      <li>
        <span class="context-label">Workspace</span>
        <a class="context-link" href="/workspaces/<?= $workspaceId ?>"><?= htmlspecialchars($workspaceName, ENT_QUOTES, 'UTF-8') ?></a>
      </li>
    </ul>
  </section>
```

After that section, add the status form and close the aside:

```php
  <section class="status-action" aria-labelledby="status-action-title">
    <h2 id="status-action-title">Change status</h2>
    <p><?= $isClosed ? 'Return this issue to active work.' : 'Mark this issue complete without removing its history.' ?></p>
    <form method="POST" action="/workspaces/<?= $workspaceId ?>/projects/<?= $projectId ?>/issues/<?= (int) ($issue['id'] ?? 0) ?>/status">
      <?= csrf_field() ?>
      <input type="hidden" name="status" value="<?= $nextStatus ?>">
      <button type="submit"><?= $actionLabel ?></button>
    </form>
  </section>
</aside>
```

The form's action includes every identity the controller verifies. Its CSRF token
protects the change, and the hidden value names the explicit destination. The copy
also changes with the state: closing preserves history, while reopening returns the
issue to active work.

Style this action as a continuation of the context column rather than a competing
card:

```css
.status-action { margin-top:32px; padding-top:24px; border-top:1px solid var(--border-strong); }
.status-action p { margin:10px 0 0; color:var(--muted); font-size:13px; line-height:1.55; }
.status-action button { width:100%; min-height:42px; margin-top:16px; border:0; border-radius:9px; padding:9px 12px; background:var(--accent); color:#fff; font:inherit; font-size:14px; font-weight:750; cursor:pointer; }
.status-action button:hover { background:#066c4d; }
.status-action button:focus-visible { outline:3px solid #78d6b7; outline-offset:3px; }
```

The existing responsive layout already moves the right column below the description
on a phone. The button becomes a full-width, comfortable target there without any
additional media rule.

## Keep the project list in sync

The detail page is not the only place that displays status. Open
`resources/views/projects/show.view.php` and add the same neutral closed-state rule:

```css
.status-closed { background:#e9ecef; color:#46515c; }
```

Inside the issue loop, derive the state before rendering its row:

```php
<?php foreach ($issues as $issue): ?>
  <?php $isClosed = ($issue['status'] ?? '') === 'closed'; ?>
  <li>
```

Then update the row's status pill:

```php
<span class="status <?= $isClosed ? 'status-closed' : '' ?>"><?= ucfirst(htmlspecialchars((string) ($issue['status'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></span>
```

Both pages now read the same stored value and present it with the same words and
colors. There is no separate project-list status to synchronize; reopening the issue
changes one database column, and every later GET sees that value.

## Move through both states

Open an issue and note the project's issue count. Select **Close issue**. The request
should return to the same detail page with a one-time confirmation, a neutral
**Closed** pill, and a **Reopen issue** button. Refresh: the message should disappear
while the closed status remains.

Return to the project and confirm the same issue says **Closed** without changing the
count. Open it and select **Reopen issue**; both pages should return to **Open**.

The server boundaries still matter. A status request without the CSRF token must
return 419, an unsupported value must return 422, and a real issue placed under the
wrong workspace or project must return 404. None of those requests may change the
database value.

If you are using Git, save the status-update slice:

```bash
git add routes/routes.php app/Http/controllers/issues/status.php resources/views/issues/show.view.php resources/views/projects/show.view.php
git commit -m "Update issue status"
```

Our issue tracker can now preserve both active and completed work. Next, we will add
editing so an issue's title and description can evolve after it is created.
