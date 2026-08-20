Projects now belong to workspaces, but their rows still stop at the workspace page.
In this lesson we will give each project a nested URL, prove that it belongs to the
workspace named by that URL, and establish the screen where its issues will live.

The visible change is navigation. The important server-side change is a stricter
lookup: `/workspaces/1/projects/2` should work only when project `2` actually belongs
to workspace `1`.

## Add both identities to the route

Open `routes/routes.php` and register the project detail route below project
creation:

```php
$router->get('/workspaces/{workspace}', 'workspaces/show.php');
$router->post('/workspaces/{workspace}/projects', 'projects/store.php')->only('csrf');
$router->get('/workspaces/{workspace}/projects/{project}', 'projects/show.php');
```

This pattern captures two route parameters from one URL. For
`/workspaces/1/projects/2`, DALT records `workspace` as `1` and `project` as `2`.

Keeping the workspace in the URL preserves the product's hierarchy. The project is
not merely item `2`; it is project `2` inside workspace `1`. The controller must now
make the database agree with that statement.

## Validate both changing segments

Create `app/Http/controllers/projects/show.php`:

```php
<?php

declare(strict_types=1);

use Core\App;
use Core\Database;
use Core\Request;

$request = App::resolve(Request::class);
$workspaceId = $request->route('workspace');
$projectId = $request->route('project');

if (
    !is_string($workspaceId)
    || preg_match('/\A[1-9]\d*\z/', $workspaceId) !== 1
    || !is_string($projectId)
    || preg_match('/\A[1-9]\d*\z/', $projectId) !== 1
) {
    abort(404);
}
```

Both values came from the request path, so both cross the same boundary. A malformed
workspace or project segment stops before a database query. We do not cast arbitrary
text to `0` and hope the lookup happens to fail later.

Continue by loading the workspace:

```php
$database = App::resolve(Database::class);
$workspace = $database
    ->query(
        'SELECT id, name FROM workspaces WHERE id = :id',
        ['id' => (int) $workspaceId],
    )
    ->findOrFail();
```

The row gives the page its workspace name and proves the first resource in the path
exists. Now add the project query:

```php
$project = $database
    ->query(
        'SELECT id, workspace_id, name, created_at
         FROM projects
         WHERE id = :id AND workspace_id = :workspace_id',
        [
            'id' => (int) $projectId,
            'workspace_id' => $workspace['id'],
        ],
    )
    ->findOrFail();
```

The `WHERE` clause requires both statements in the URL to be true. Matching only
`id = :id` would load the project even if someone moved its URL under another
workspace. With both conditions, an existing project paired with the wrong workspace
produces no row, and `findOrFail()` returns 404.

This is structural ownership between application records. It is not user
authorization yet—we have not added accounts or permissions. Later, authorization
will add another condition: whether the signed-in user may access the matched
workspace and project at all.

Finish the controller by passing both verified rows to the view:

```php
view('projects/show.view.php', [
    'workspace' => $workspace,
    'project' => $project,
]);
```

## Build the project page

Create `resources/views/projects/show.view.php`. Start with the project and workspace
values, document head, and shared application styles:

```php
<!doctype html>
<?php
$workspaceId = (int) ($workspace['id'] ?? 0);
$workspaceName = (string) ($workspace['name'] ?? '');
$projectName = (string) ($project['name'] ?? '');
?>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title><?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') ?> · DALT Issues</title>
```

The page needs both resources. The project supplies the main identity; the workspace
supplies the route back to its parent. Escape their names at every HTML boundary,
including the browser title.

Continue inside `<head>` with the complete page styles:

```html
  <style>
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
    .back-link:focus-visible { outline:3px solid #78d6b7; outline-offset:4px; }
    .project-heading { margin-top:40px; padding-bottom:28px; border-bottom:1px solid var(--border-strong); }
    h1 { max-width:760px; margin:0; overflow-wrap:anywhere; font-size:clamp(36px,6vw,56px); line-height:1.02; letter-spacing:-.04em; }
    .introduction { max-width:620px; margin:16px 0 0; color:var(--muted); font-size:16px; line-height:1.65; }
    .workspace-context { margin:18px 0 0; color:var(--muted); font-size:13px; }
    .workspace-context a { color:var(--accent); font-weight:700; text-underline-offset:3px; }
    .workspace-context a:focus-visible { outline:3px solid #78d6b7; outline-offset:3px; }
    .section-heading { display:flex; align-items:end; justify-content:space-between; gap:20px; margin-top:48px; }
    h2 { margin:0; font-size:22px; letter-spacing:-.025em; }
    .count { color:var(--muted); font-size:13px; font-weight:650; }
    .empty-state { margin-top:18px; padding:48px 24px; border:1px dashed var(--border-strong); border-radius:14px; background:var(--surface); text-align:center; }
    .empty-mark { display:grid; width:44px; height:44px; margin:0 auto 18px; place-items:center; border-radius:12px; background:var(--accent-soft); color:var(--accent); }
    .empty-mark svg { width:22px; height:22px; }
    .empty-state h3 { margin:0; font-size:18px; letter-spacing:-.015em; }
    .empty-state p { max-width:430px; margin:10px auto 0; color:var(--muted); font-size:14px; line-height:1.65; }
    @media (max-width:600px) {
      .header-inner,.content { width:min(100% - 32px,960px); }
      .content { padding:40px 0 56px; }
      .project-heading { margin-top:32px; }
      .section-heading { margin-top:40px; }
      .empty-state { padding:40px 20px; }
    }
  </style>
</head>
```

The project name receives the visual weight, while the workspace remains useful
context rather than competing as a second heading. The responsive rules preserve
that hierarchy on a phone and protect long project names from overflowing.

Finish the file with the application header and project content:

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
    <a class="back-link" href="/workspaces/<?= $workspaceId ?>">Back to <?= htmlspecialchars($workspaceName, ENT_QUOTES, 'UTF-8') ?></a>

    <header class="project-heading">
      <h1><?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="introduction">Track the work, decisions, and progress for this project through issues.</p>
      <p class="workspace-context">
        Workspace
        <a href="/workspaces/<?= $workspaceId ?>"><?= htmlspecialchars($workspaceName, ENT_QUOTES, 'UTF-8') ?></a>
      </p>
    </header>

    <div class="section-heading">
      <h2>Issues</h2>
      <span class="count">0 issues</span>
    </div>

    <section class="empty-state" aria-labelledby="issues-empty-title">
      <span class="empty-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" focusable="false">
          <path d="M7 7.5h10M7 12h7M7 16.5h5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
      </span>
      <h3 id="issues-empty-title">No issues yet</h3>
      <p>Our next step is to capture the first piece of work for this project.</p>
    </section>
  </main>
</body>
</html>
```

The Issues section is intentionally empty and has no dead button. It identifies the
next real product capability without pretending issue creation already exists. The
inline SVG is decorative, so its wrapper is hidden from assistive technology.

## Link projects to their nested URLs

Return to `resources/views/workspaces/show.view.php`. Replace each project row inside
the existing `foreach` loop with:

```php
<li>
  <a class="project-link" href="/workspaces/<?= $workspaceId ?>/projects/<?= (int) ($project['id'] ?? 0) ?>">
    <span class="project-name"><?= htmlspecialchars((string) ($project['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
    <span class="project-summary">
      <span class="project-meta">0 issues</span>
      <svg class="project-arrow" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <path d="M4 10h11m-4-4 4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </span>
  </a>
</li>
```

The link includes both IDs that the new route requires. Its readable name is the
project name; the issue count and arrow supply secondary context without replacing
that label.

Replace the old project-row styles with the complete linked-row rules:

```css
.project-list li { min-width:0; border-bottom:1px solid var(--border); }
.project-link { min-width:0; display:flex; align-items:center; justify-content:space-between; gap:20px; padding:20px 8px 20px 4px; border-radius:8px; color:var(--text); text-decoration:none; }
.project-link:hover { background:#f0f3f5; }
.project-link:focus-visible { outline:3px solid #78d6b7; outline-offset:3px; }
.project-name { min-width:0; overflow-wrap:anywhere; font-weight:700; }
.project-summary { flex:none; display:inline-flex; align-items:center; gap:12px; }
.project-meta { color:var(--muted); font-size:13px; }
.project-arrow { width:18px; height:18px; color:var(--accent); }
```

The whole row becomes one keyboard-accessible target, matching the workspace list.
The arrow is an SVG rather than text, and `currentColor` keeps it synchronized with
the existing accent color.

## Test the hierarchy, not only the happy path

Open projects from two different workspaces. Each URL should show the matching
project name, the owning workspace, and `0 issues`. Refresh a project URL directly
and use **Back to…** to return to its workspace.

Then change only the workspace ID in a real project URL. If project `1` belongs to
workspace `1`, `/workspaces/2/projects/1` must return 404 even when workspace `2`
also exists. Also try an unknown project ID and non-numeric segments. These paths
exercise the two conditions that make the nested URL truthful.

If you are using Git, save the project navigation step:

```bash
git add routes/routes.php app/Http/controllers/projects/show.php resources/views/workspaces/show.view.php resources/views/projects/show.view.php
git commit -m "Add project detail pages"
```

Our application now has a real hierarchy from workspace list to workspace to project.
Next, we will give the Issues section its first database-backed creation flow so the
tracker can begin recording actual work.
