Our project page can create and list issues, but every row is still a dead end. In
this lesson we will give each issue its own URL, make the list navigable, and render
the complete issue record without weakening the hierarchy we have built.

An issue URL will name three resources at once: its workspace, project, and issue.
The controller must prove all three relationships before the page can appear.

## Add the third route parameter

Open `routes/routes.php` and register the issue detail route below issue creation:

```php
$router->post('/workspaces/{workspace}/projects/{project}/issues', 'issues/store.php')->only('csrf');
$router->get('/workspaces/{workspace}/projects/{project}/issues/{issue}', 'issues/show.php');
```

A URL such as `/workspaces/1/projects/2/issues/3` now captures `workspace`, `project`,
and `issue`. The path expresses two ownership claims: project `2` belongs to
workspace `1`, and issue `3` belongs to project `2`.

This is a GET route because opening an issue only reads state. It does not need CSRF
middleware; the protected POST route beside it still owns issue creation.

## Validate every changing segment

Create `app/Http/controllers/issues/show.php`. Resolve the three route values and
apply the same positive-integer boundary to each one:

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

All three values originated outside our application, even when a visitor arrived by
clicking one of our links. Rejecting malformed segments here keeps them out of every
database query that follows. A path containing `no`, `0`, or a missing number
therefore ends as 404 rather than being cast into a different identity.

## Walk down the ownership chain

Continue the controller by loading the workspace:

```php
$database = App::resolve(Database::class);
$workspace = $database
    ->query(
        'SELECT id, name FROM workspaces WHERE id = :id',
        ['id' => (int) $workspaceId],
    )
    ->findOrFail();
```

We select the name because the detail page will use the workspace as visible
context, not only as a guard. `findOrFail()` stops with 404 when this first resource
does not exist.

Next, require the project to belong to that workspace:

```php
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

We use the verified database ID from `$workspace`, not the original route string, as
the ownership value. An existing project placed under the wrong workspace produces
no row.

Now add the issue lookup:

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
```

The final query proves the second relationship in the URL. Looking up only `id =
:id` would allow a real issue to appear beneath an unrelated project. Requiring its
`project_id` makes the database agree with the complete path before any view is
rendered.

Finish the controller by passing the three verified records forward:

```php
view('issues/show.view.php', [
    'workspace' => $workspace,
    'project' => $project,
    'issue' => $issue,
]);
```

The view does not need to query or reconstruct ownership. It receives exactly the
workspace, project, and issue that survived the controller's chain.

## Turn each issue row into one link

Return to `resources/views/projects/show.view.php`. Near the top of the file, keep
the project ID beside its name:

```php
$workspaceId = (int) ($workspace['id'] ?? 0);
$workspaceName = (string) ($workspace['name'] ?? '');
$projectId = (int) ($project['id'] ?? 0);
$projectName = (string) ($project['name'] ?? '');
```

The same verified ID will now serve the form action and every issue URL. Replace the
contents of each issue-list `<li>` with a link around the complete row:

```php
<a class="issue-link" href="/workspaces/<?= $workspaceId ?>/projects/<?= $projectId ?>/issues/<?= (int) ($issue['id'] ?? 0) ?>">
  <div class="issue-line">
    <h3 class="issue-title"><?= htmlspecialchars((string) ($issue['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
    <span class="issue-summary">
      <span class="status"><?= ucfirst(htmlspecialchars((string) ($issue['status'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></span>
      <svg class="issue-arrow" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <path d="M4 10h11m-4-4 4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </span>
  </div>
  <?php if (($issue['description'] ?? '') !== ''): ?>
    <p class="issue-description"><?= htmlspecialchars((string) $issue['description'], ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>
</a>
```

The title, status, and description now form one generous target rather than several
small competing links. The arrow communicates that the row opens another location,
but it is decorative because the issue title already gives the link its accessible
name.

Replace the old list-item spacing rule and add the linked-row styles:

```css
.issue-list li { min-width:0; border-bottom:1px solid var(--border); }
.issue-link { display:block; min-width:0; padding:20px 8px 20px 4px; border-radius:8px; color:var(--text); text-decoration:none; }
.issue-link:hover { background:#f0f3f5; }
.issue-link:focus-visible { outline:3px solid #78d6b7; outline-offset:3px; }
.issue-summary { flex:none; display:inline-flex; align-items:center; gap:10px; }
.issue-arrow { width:17px; height:17px; color:var(--accent); }
```

Hover and keyboard focus make the interaction visible. The existing title wrapping
still protects long issue names, while `.issue-summary` keeps the small status and
arrow together.

Update the creation form to reuse `$projectId` as well:

```php
<form method="POST" action="/workspaces/<?= $workspaceId ?>/projects/<?= $projectId ?>/issues">
```

This does not change the request. It removes a second inline conversion now that the
page needs the project ID in more than one place.

## Prepare the issue detail view

Create `resources/views/issues/show.view.php`. Begin with the values that came from
the controller and the standard document head:

```php
<!doctype html>
<?php
$workspaceId = (int) ($workspace['id'] ?? 0);
$workspaceName = (string) ($workspace['name'] ?? '');
$projectId = (int) ($project['id'] ?? 0);
$projectName = (string) ($project['name'] ?? '');
$issueTitle = (string) ($issue['title'] ?? '');
$issueDescription = (string) ($issue['description'] ?? '');
$issueStatus = (string) ($issue['status'] ?? '');
?>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title><?= htmlspecialchars($issueTitle, ENT_QUOTES, 'UTF-8') ?> · DALT Issues</title>
```

Naming the values once keeps the markup readable. The issue title is escaped in the
browser tab just as it will be escaped in the page heading.

Add the shared application foundation and the detail-page layout inside `<style>`:

```css
:root { color-scheme:light; --canvas:#f7f8fa; --surface:#fff; --border:#dfe3e8; --border-strong:#c8ced6; --text:#17202a; --muted:#52606d; --accent:#087f5b; --accent-soft:#dff7ed; }
* { box-sizing:border-box; }
body { margin:0; min-height:100vh; background:var(--canvas); color:var(--text); font-family:ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
.site-header { background:var(--surface); border-bottom:1px solid var(--border); }
.header-inner,.content { width:min(960px,calc(100% - 40px)); margin:0 auto; }
.header-inner { min-height:64px; display:flex; align-items:center; justify-content:space-between; gap:20px; }
.brand { display:inline-flex; align-items:center; gap:10px; color:var(--text); font-size:15px; font-weight:760; letter-spacing:-.02em; text-decoration:none; }
.brand-mark { width:10px; height:24px; border-radius:3px; background:var(--accent); }
.environment { color:var(--muted); font-size:13px; }
.content { padding:56px 0 72px; }
.back-link { display:inline-flex; color:var(--muted); font-size:14px; font-weight:650; text-decoration:none; }
.back-link:hover { color:var(--accent); }
.back-link:focus-visible,.context-link:focus-visible { outline:3px solid #78d6b7; outline-offset:4px; }
```

Continue with the issue-specific hierarchy:

```css
.issue-heading { margin-top:40px; padding-bottom:32px; border-bottom:1px solid var(--border-strong); }
.status { display:inline-flex; padding:5px 9px; border-radius:999px; background:var(--accent-soft); color:#075b43; font-size:12px; font-weight:800; line-height:1.2; }
h1 { max-width:780px; margin:18px 0 0; overflow-wrap:anywhere; font-size:clamp(36px,6vw,56px); line-height:1.02; letter-spacing:-.04em; }
.issue-layout { display:grid; grid-template-columns:minmax(0,1fr) 260px; gap:56px; align-items:start; margin-top:48px; }
h2 { margin:0; font-size:18px; letter-spacing:-.02em; }
.description { max-width:68ch; margin:18px 0 0; color:var(--text); font-size:16px; line-height:1.75; overflow-wrap:anywhere; white-space:pre-wrap; }
.description-empty { color:var(--muted); font-style:italic; }
.context { padding-top:18px; border-top:1px solid var(--border-strong); }
.context-list { margin:18px 0 0; padding:0; list-style:none; }
.context-list li + li { margin-top:18px; }
.context-label { display:block; color:var(--muted); font-size:12px; font-weight:700; }
.context-link { display:inline-block; margin-top:5px; color:var(--text); font-size:14px; font-weight:700; overflow-wrap:anywhere; text-decoration-color:#9ba5b1; text-underline-offset:3px; }
.context-link:hover { color:var(--accent); text-decoration-color:var(--accent); }
```

The issue itself owns the page's largest type. Its status stays visible without
competing with the title, and the context column answers where this work belongs.
Descriptions preserve line breaks and remain within a readable measure.

Finish the styles with the mobile change:

```css
@media (max-width:600px) {
  .header-inner,.content { width:min(100% - 32px,960px); }
  .content { padding:40px 0 56px; }
  .issue-heading { margin-top:32px; }
  .issue-layout { grid-template-columns:1fr; gap:40px; margin-top:40px; }
}
```

On a narrow screen the description and context become one natural reading column.
Close `</style>` and `</head>`, then render the shared header and issue identity:

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
    <a class="back-link" href="/workspaces/<?= $workspaceId ?>/projects/<?= $projectId ?>">Back to <?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') ?></a>

    <header class="issue-heading">
      <span class="status"><?= ucfirst(htmlspecialchars($issueStatus, ENT_QUOTES, 'UTF-8')) ?></span>
      <h1><?= htmlspecialchars($issueTitle, ENT_QUOTES, 'UTF-8') ?></h1>
    </header>
```

The back link returns to the exact project that owns the issue. Every dynamic value
is escaped at the HTML boundary, including the status that currently came from our
own insert controller.

Finish the page with the description and resource context:

```php
    <div class="issue-layout">
      <article aria-labelledby="description-title">
        <h2 id="description-title">Description</h2>
        <?php if ($issueDescription === ''): ?>
          <p class="description description-empty">No description was added.</p>
        <?php else: ?>
          <p class="description"><?= htmlspecialchars($issueDescription, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
      </article>

      <aside class="context" aria-labelledby="context-title">
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
      </aside>
    </div>
  </main>
</body>
</html>
```

An issue without a description receives an honest empty message rather than a blank
region. The context links make both parent resources reachable while the back link
keeps the most common return path immediate.

## Follow and challenge the path

Open issues from at least two projects. Each row should lead to a page with its own
title, **Open** status, description or empty-description message, project, and
workspace. Refresh the detail URL directly and use all three return paths: **Back
to…**, Project, and Workspace.

Then challenge the ownership chain. Starting with a real URL, change only the
workspace ID, then only the project ID, then only the issue ID. A mismatched parent,
an unknown issue, or a non-numeric segment must return 404. A real issue should never
appear under another project's URL.

If you are using Git, save the complete navigation step:

```bash
git add routes/routes.php app/Http/controllers/issues/show.php resources/views/projects/show.view.php resources/views/issues/show.view.php
git commit -m "Add issue detail pages"
```

Our issue tracker now has a complete read path from workspace to project to issue.
Next, we will make the detail page useful for action by letting an issue move between
open and closed states.
