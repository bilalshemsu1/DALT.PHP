Editing is forgiving: we can submit another update when we make a mistake. Deletion
is different. Once an issue row is removed, our application has no undo button and
no archived copy. In this lesson we will make that consequence visible before we
send a protected request that permanently deletes the issue.

The safe path has two separate moments. First, a GET request displays exactly what
will be deleted without changing data. Only the confirmation form sends the DELETE
request. This gives us a real decision point instead of placing an irreversible
action one accidental click away.

## Give review and deletion different routes

Open `routes/routes.php`. Add a GET route for the review page and a protected DELETE
route beside the existing issue routes:

```php
$router->get('/workspaces/{workspace}/projects/{project}/issues/{issue}', 'issues/show.php');
$router->get('/workspaces/{workspace}/projects/{project}/issues/{issue}/edit', 'issues/edit.php');
$router->get('/workspaces/{workspace}/projects/{project}/issues/{issue}/delete', 'issues/delete.php');
$router->post('/workspaces/{workspace}/projects/{project}/issues/{issue}', 'issues/update.php')->only('csrf');
$router->post('/workspaces/{workspace}/projects/{project}/issues/{issue}/status', 'issues/status.php')->only('csrf');
$router->delete('/workspaces/{workspace}/projects/{project}/issues/{issue}', 'issues/destroy.php')->only('csrf');
```

Visiting `/delete` only asks DALT to render a page, so it remains a GET request. The
actual resource URL receives DELETE when the learner confirms. The HTTP method tells
the router that this request removes the resource rather than updates its content or
status.

Only the DELETE route needs CSRF middleware. A forged visit to the review page cannot
change the database, but a forged deletion request could. Keeping the protection on
the state-changing boundary makes the reason for it precise.

## Load the issue we are about to show

Create `app/Http/controllers/issues/delete.php`. Start with the same three route
parameters used by every issue page:

```php
<?php

declare(strict_types=1);

use Core\App;
use Core\Database;
use Core\Request;

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

This is still an external URL. Before we display a title, description, or status, all
three segments must be positive integer strings. A malformed value stops at 404
without reaching the database.

Walk through the hierarchy and select the names the confirmation page needs:

```php
$database = App::resolve(Database::class);
$workspace = $database
    ->query(
        'SELECT id, name FROM workspaces WHERE id = :id',
        ['id' => (int) $workspaceId],
    )
    ->findOrFail();

$project = $database
    ->query(
        'SELECT id, name FROM projects WHERE id = :id AND workspace_id = :workspace_id',
        [
            'id' => (int) $projectId,
            'workspace_id' => $workspace['id'],
        ],
    )
    ->findOrFail();
```

The project query does not ask only whether that ID exists. It asks whether it exists
inside the workspace from this URL. That distinction keeps a valid project from
being presented under the wrong workspace.

Now find the issue through the verified project and render the page:

```php
$issue = $database
    ->query(
        'SELECT id, title, description, status
         FROM issues
         WHERE id = :id AND project_id = :project_id',
        [
            'id' => (int) $issueId,
            'project_id' => $project['id'],
        ],
    )
    ->findOrFail();

view('issues/delete.view.php', [
    'workspace' => $workspace,
    'project' => $project,
    'issue' => $issue,
]);
```

The learner will see enough information to recognize the record before deciding.
The same query also ensures a real issue under another project returns 404 instead
of appearing on an untruthful confirmation page.

## Place deletion behind a deliberate entry point

Open `resources/views/issues/show.view.php`. Add danger colors to the existing root
variables:

```css
:root {
  color-scheme:light;
  --canvas:#f7f8fa;
  --surface:#fff;
  --border:#dfe3e8;
  --border-strong:#c8ced6;
  --text:#17202a;
  --muted:#52606d;
  --accent:#087f5b;
  --accent-soft:#dff7ed;
  --danger:#b42318;
  --danger-soft:#fff1f0;
}
```

Keep green for the normal workflow and reserve red for permanent removal. Then add
the destructive section styles after the status-action rules:

```css
.danger-action { margin-top:32px; padding-top:24px; border-top:1px solid var(--border-strong); }
.danger-action p { margin:10px 0 0; color:var(--muted); font-size:13px; line-height:1.55; }
.delete-link { display:flex; min-height:42px; margin-top:16px; align-items:center; justify-content:center; border:1px solid #d92d20; border-radius:9px; padding:9px 12px; color:var(--danger); background:var(--surface); font-size:14px; font-weight:750; text-decoration:none; }
.delete-link:hover { background:var(--danger-soft); }
.delete-link:focus-visible { outline:3px solid #fda29b; outline-offset:3px; }
```

This control is intentionally outlined rather than filled. Close and Reopen remain
the prominent everyday actions; deletion is findable but does not compete for an
accidental click.

Inside the issue page's `aside`, after the Change status section, add:

```php
<section class="danger-action" aria-labelledby="danger-action-title">
  <h2 id="danger-action-title">Delete issue</h2>
  <p>Permanently remove this issue from the project.</p>
  <a class="delete-link" href="/workspaces/<?= $workspaceId ?>/projects/<?= $projectId ?>/issues/<?= (int) ($issue['id'] ?? 0) ?>/delete">Review deletion</a>
</section>
```

The label says **Review deletion**, not **Delete**, because following this link does
not yet alter anything. The next page provides the irreversible button.

## Build the confirmation page

Create `resources/views/issues/delete.view.php`. Name the controller data at the top
and derive whether the status needs its neutral closed treatment:

```php
<!doctype html>
<?php
$workspaceId = (int) ($workspace['id'] ?? 0);
$workspaceName = (string) ($workspace['name'] ?? '');
$projectId = (int) ($project['id'] ?? 0);
$projectName = (string) ($project['name'] ?? '');
$issueId = (int) ($issue['id'] ?? 0);
$issueTitle = (string) ($issue['title'] ?? '');
$issueDescription = (string) ($issue['description'] ?? '');
$issueStatus = (string) ($issue['status'] ?? '');
$isClosed = $issueStatus === 'closed';
?>
```

Add the document head and the same narrow application shell used by the edit page:

```php
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title>Delete <?= htmlspecialchars($issueTitle, ENT_QUOTES, 'UTF-8') ?> · DALT Issues</title>
  <style>
    :root { color-scheme:light; --canvas:#f7f8fa; --surface:#fff; --border:#dfe3e8; --border-strong:#c8ced6; --text:#17202a; --muted:#52606d; --accent:#087f5b; --danger:#b42318; --danger-dark:#912018; --danger-soft:#fff1f0; }
    * { box-sizing:border-box; }
    body { margin:0; min-height:100vh; background:var(--canvas); color:var(--text); font-family:ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
    .site-header { background:var(--surface); border-bottom:1px solid var(--border); }
    .header-inner,.content { width:min(760px,calc(100% - 40px)); margin:0 auto; }
    .header-inner { min-height:64px; display:flex; align-items:center; justify-content:space-between; gap:20px; }
    .brand { display:inline-flex; align-items:center; gap:10px; color:var(--text); font-size:15px; font-weight:760; letter-spacing:-.02em; text-decoration:none; }
    .brand-mark { width:10px; height:24px; border-radius:3px; background:var(--accent); }
    .environment { color:var(--muted); font-size:13px; }
    .content { padding:56px 0 72px; }
    .back-link { display:inline-flex; color:var(--muted); font-size:14px; font-weight:650; text-decoration:none; }
    .back-link:hover { color:var(--accent); }
    .back-link:focus-visible,.cancel-link:focus-visible { outline:3px solid #78d6b7; outline-offset:4px; }
  </style>
</head>
```

Continue in the style block with the decision content:

```css
.delete-heading { margin-top:40px; }
h1 { max-width:680px; margin:0; overflow-wrap:anywhere; font-size:clamp(36px,6vw,52px); line-height:1.03; letter-spacing:-.04em; }
.introduction { max-width:620px; margin:14px 0 0; color:var(--muted); font-size:16px; line-height:1.65; }
.issue-summary { margin-top:32px; padding:24px 0; border-top:1px solid var(--border-strong); border-bottom:1px solid var(--border-strong); }
.issue-status { display:inline-flex; padding:5px 9px; border-radius:999px; background:#dff7ed; color:#075b43; font-size:12px; font-weight:800; line-height:1.2; }
.issue-status-closed { background:#e9ecef; color:#46515c; }
.issue-title { margin:14px 0 0; overflow-wrap:anywhere; font-size:20px; font-weight:780; letter-spacing:-.02em; }
.issue-description { max-width:68ch; margin:10px 0 0; color:var(--muted); font-size:14px; line-height:1.65; overflow-wrap:anywhere; white-space:pre-wrap; }
.issue-context { margin:14px 0 0; color:var(--muted); font-size:13px; overflow-wrap:anywhere; }
.warning { margin-top:28px; padding:16px; border:1px solid #f1a9a3; border-radius:12px; background:var(--danger-soft); color:#7a271a; }
.warning strong { display:block; font-size:15px; }
.warning p { margin:7px 0 0; font-size:14px; line-height:1.6; }
```

The issue summary is plain content between rules rather than another decorative card.
The tinted warning is reserved for the consequence the learner must notice.

Finish the controls and responsive rules:

```css
.actions { display:flex; align-items:center; gap:20px; margin-top:28px; }
form { margin:0; }
button { min-height:44px; border:0; border-radius:9px; padding:10px 22px; background:var(--danger); color:#fff; font:inherit; font-weight:750; cursor:pointer; }
button:hover { background:var(--danger-dark); }
button:focus-visible { outline:3px solid #fda29b; outline-offset:3px; }
.cancel-link { color:var(--muted); font-size:14px; font-weight:700; text-underline-offset:3px; }
.cancel-link:hover { color:var(--text); }
@media (max-width:600px) {
  .header-inner,.content { width:min(100% - 32px,760px); }
  .content { padding:40px 0 56px; }
  .delete-heading { margin-top:32px; }
  .actions { align-items:stretch; flex-direction:column; }
  form,button { width:100%; }
  .cancel-link { align-self:center; padding:6px; }
}
```

On a narrow screen, the destructive button becomes a large target and the safe exit
sits separately below it. Close the style and head, then render the shared header and
the question:

```php
<body>
  <header class="site-header">
    <div class="header-inner">
      <a class="brand" href="/">
        <span class="brand-mark" aria-hidden="true"></span>
        DALT Issues
      </a>
      <span class="environment">Local development</span>
    </div>
  </header>

  <main class="content">
    <a class="back-link" href="/workspaces/<?= $workspaceId ?>/projects/<?= $projectId ?>/issues/<?= $issueId ?>">Back to issue</a>

    <header class="delete-heading">
      <h1>Delete this issue?</h1>
      <p class="introduction">Review the issue below before permanently removing it from the project.</p>
    </header>
```

Render the stored content and consequence directly below the question:

```php
<section class="issue-summary" aria-labelledby="issue-title">
  <span class="issue-status <?= $isClosed ? 'issue-status-closed' : '' ?>"><?= ucfirst(htmlspecialchars($issueStatus, ENT_QUOTES, 'UTF-8')) ?></span>
  <h2 class="issue-title" id="issue-title"><?= htmlspecialchars($issueTitle, ENT_QUOTES, 'UTF-8') ?></h2>
  <?php if ($issueDescription !== ''): ?>
    <p class="issue-description"><?= htmlspecialchars($issueDescription, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>
  <p class="issue-context"><?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($workspaceName, ENT_QUOTES, 'UTF-8') ?></p>
</section>

<aside class="warning" aria-labelledby="warning-title">
  <strong id="warning-title">This cannot be undone.</strong>
  <p>The title, description, and status will be permanently removed.</p>
</aside>
```

Everything originating from the database is escaped before entering HTML. The title,
status, description, project, and workspace give the learner several ways to catch a
wrong choice before continuing.

Finish the page with the two decisions:

```php
<div class="actions">
  <form method="POST" action="/workspaces/<?= $workspaceId ?>/projects/<?= $projectId ?>/issues/<?= $issueId ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="_method" value="DELETE">
    <button type="submit">Delete issue</button>
  </form>
  <a class="cancel-link" href="/workspaces/<?= $workspaceId ?>/projects/<?= $projectId ?>/issues/<?= $issueId ?>">Keep issue</a>
</div>
  </main>
</body>
</html>
```

HTML forms can submit only GET and POST. DALT's request object reads `_method` from a
POST form and treats the request as DELETE, allowing the router to reach our DELETE
route. The original request must still be POST; query strings and ordinary GET links
cannot spoof a destructive method. The CSRF field travels with the same submission.

**Keep issue** is an ordinary link. Following it makes no write request, so cancelling
does not need controller logic or a compensating database change.

## Delete only after proving ownership again

Create `app/Http/controllers/issues/destroy.php`. Import the session class for the
result message and repeat the route boundary:

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

The confirmation controller's checks do not protect this request. Someone can forge
a request without viewing that page, and records can change between review and
submission. The destructive controller must establish the complete boundary again.

Load the workspace, project, and issue in order:

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

$issue = $database
    ->query(
        'SELECT id, title FROM issues WHERE id = :id AND project_id = :project_id',
        [
            'id' => (int) $issueId,
            'project_id' => $project['id'],
        ],
    )
    ->findOrFail();
```

We retain the title before deleting because the success message still needs it after
the row is gone. Every lookup uses bound values, and the final query accepts the issue
only inside the already verified project.

Delete that ID, flash the result, and return to the owning project:

```php
$database->query(
    'DELETE FROM issues WHERE id = :id',
    ['id' => $issue['id']],
);

Session::flash('success', "{$issue['title']} was deleted.");

return redirect(
    "/workspaces/{$workspace['id']}/projects/{$project['id']}",
    303,
);
```

We cannot redirect to the issue detail page because that resource no longer exists.
The project is the nearest useful surviving page: it shows the updated list, the
reduced count, and the one-time result message. A `303` tells the browser to follow
the form submission with a GET, so refreshing the project does not repeat deletion.

## Remove a disposable issue

Create a temporary issue from a project page. Open it and choose **Review deletion**.
Read the title and context, then select **Keep issue** once. The detail page should
return with the issue unchanged.

Review it again and select **Delete issue**. The project page should display a
one-time “was deleted” message, remove the issue from the list, and reduce its count
by one. Refresh: the message disappears while the issue remains absent. Its old detail
and confirmation URLs now return 404.

A DELETE request without the CSRF token returns 419. Putting a real issue ID under a
different workspace or project returns 404 and leaves the row unchanged. Those
failure paths matter most here because a destructive request that reports success
against the wrong boundary cannot be repaired from this interface.

If you are using Git, save the complete deletion slice:

```bash
git add routes/routes.php app/Http/controllers/issues/delete.php app/Http/controllers/issues/destroy.php resources/views/issues/show.view.php resources/views/issues/delete.view.php
git commit -m "Delete issues safely"
```

Our server-rendered issue workflow now creates, reads, updates, changes status, and
deletes. Next, we will decide how React, TypeScript, and Tailwind should enter this
working application without discarding the backend behavior we have already earned.
