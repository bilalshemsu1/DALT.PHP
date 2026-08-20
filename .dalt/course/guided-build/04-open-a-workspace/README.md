Our home page lists application-owned workspaces, but the rows do not lead anywhere.
In this lesson we will give every workspace a stable URL, load the requested row from
the database, and render the first page that belongs to one workspace.

This is the first time one route will serve many resources. `/workspaces/1` and
`/workspaces/2` use the same controller and view, while the final path segment tells
our application which workspace the browser requested.

## Register a route with a changing segment

Open `routes/routes.php` and add the new GET route below the two workspace routes we
already have:

```php
$router->get('/', 'welcome.php');
$router->post('/workspaces', 'workspaces/store.php')->only('csrf');
$router->get('/workspaces/{workspace}', 'workspaces/show.php');
```

The braces give the changing segment a name. A request for `/workspaces/1` matches
this pattern and DALT records `1` as the `workspace` route parameter. The route then
dispatches `workspaces/show.php`, just as our fixed routes dispatch their controllers.

The URL describes a resource rather than a PHP filename. There will not be separate
controllers named `1.php`, `2.php`, and `3.php`; one controller uses the captured
value to load the right row.

## Load the workspace named by the URL

Create `app/Http/controllers/workspaces/show.php`:

```php
<?php

declare(strict_types=1);

use Core\App;
use Core\Database;
use Core\Request;

$request = App::resolve(Request::class);
$workspaceId = $request->route('workspace');

if (!is_string($workspaceId) || preg_match('/\A[1-9]\d*\z/', $workspaceId) !== 1) {
    abort(404);
}

$database = App::resolve(Database::class);
$workspace = $database
    ->query(
        'SELECT id, name, created_at FROM workspaces WHERE id = :id',
        ['id' => (int) $workspaceId],
    )
    ->findOrFail();

view('workspaces/show.view.php', ['workspace' => $workspace]);
```

`route('workspace')` reads the value captured by `{workspace}`. A dynamic route can
receive any text that fits in that one URL segment, so we check that it is a positive
integer before treating it as one of our database IDs. `/workspaces/not-a-number`
therefore stops with a 404 instead of becoming a strange query.

For a valid-looking ID, the query binds the integer to `:id` and asks for one row.
`findOrFail()` gives us two honest outcomes: it returns the row when it exists, or
ends the request with 404 when it does not. A made-up URL such as
`/workspaces/999999` should not show an empty but apparently real workspace.

Finally, the controller passes the matched row to a dedicated view. As on our home
page, the controller loads data and the view renders it.

## Build the workspace page

Create `resources/views/workspaces/show.view.php`. Begin with the document setup and
the same small application header as our home page:

```php
<!doctype html>
<?php
$workspaceId = (int) ($workspace['id'] ?? 0);
$workspaceName = (string) ($workspace['name'] ?? '');
?>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title><?= htmlspecialchars($workspaceName, ENT_QUOTES, 'UTF-8') ?> · DALT Issues</title>
```

The view prepares the two values it will display. The workspace name appears in the
browser title, so we escape it there just as we escape user-controlled text in the
page body.

Continue inside `<head>` with the page styles and then close it:

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
    .back-link { display:inline-flex; align-items:center; gap:8px; color:var(--muted); font-size:14px; font-weight:650; text-decoration:none; }
    .back-link:hover { color:var(--accent); }
    .back-link:focus-visible { outline:3px solid #78d6b7; outline-offset:4px; }
    .workspace-heading { margin-top:40px; padding-bottom:28px; border-bottom:1px solid var(--border-strong); }
    .eyebrow { margin:0 0 12px; color:var(--accent); font-size:12px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; }
    h1 { max-width:760px; margin:0; overflow-wrap:anywhere; font-size:clamp(36px,6vw,56px); line-height:1.02; letter-spacing:-.04em; }
    .introduction { max-width:620px; margin:16px 0 0; color:var(--muted); font-size:16px; line-height:1.65; }
    .section-heading { display:flex; align-items:end; justify-content:space-between; gap:20px; margin-top:48px; }
    h2 { margin:0; font-size:22px; letter-spacing:-.025em; }
    .count { color:var(--muted); font-size:13px; font-weight:650; }
    .empty-state { margin-top:18px; padding:48px 24px; border:1px dashed var(--border-strong); border-radius:14px; background:var(--surface); text-align:center; }
    .empty-mark { display:grid; width:44px; height:44px; margin:0 auto 18px; place-items:center; border-radius:12px; background:var(--accent-soft); color:var(--accent); font-size:21px; font-weight:800; }
    .empty-state h3 { margin:0; font-size:18px; letter-spacing:-.015em; }
    .empty-state p { max-width:430px; margin:10px auto 0; color:var(--muted); font-size:14px; line-height:1.65; }
    @media (max-width:600px) {
      .header-inner,.content { width:min(100% - 32px,960px); }
      .content { padding:40px 0 56px; }
      .workspace-heading { margin-top:32px; }
      .section-heading { margin-top:40px; }
      .empty-state { padding:40px 20px; }
    }
  </style>
</head>
```

These rules preserve the visual language we already established while giving the
workspace name room to grow. `overflow-wrap:anywhere` protects the heading on a
narrow screen, and the media query tightens the page without changing its structure.

Finish the file with the visible page:

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
    <a class="back-link" href="/">← All workspaces</a>

    <header class="workspace-heading">
      <p class="eyebrow">Workspace <?= $workspaceId ?></p>
      <h1><?= htmlspecialchars($workspaceName, ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="introduction">Projects and issues for this workspace will live here.</p>
    </header>

    <div class="section-heading">
      <h2>Projects</h2>
      <span class="count">0 projects</span>
    </div>

    <section class="empty-state" aria-labelledby="projects-empty-title">
      <span class="empty-mark" aria-hidden="true">+</span>
      <h3 id="projects-empty-title">No projects yet</h3>
      <p>Our next step is to give this workspace its first project.</p>
    </section>
  </main>
</body>
</html>
```

The page is intentionally honest about the product we have today. It has a real
workspace identity and a Projects section, but it does not show a fake create button
before that behavior exists. The back link also uses an ordinary URL, so browser
navigation, refresh, and copied links all work without JavaScript.

## Turn each list row into a link

Return to `resources/views/welcome.view.php`. Replace the workspace row inside the
existing `foreach` loop with:

```php
<li>
  <a class="workspace-link" href="/workspaces/<?= (int) ($workspace['id'] ?? 0) ?>">
    <span class="workspace-name"><?= htmlspecialchars((string) ($workspace['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
    <span class="workspace-summary">
      <span class="workspace-meta">0 projects</span>
      <span class="workspace-arrow" aria-hidden="true">→</span>
    </span>
  </a>
</li>
```

The database ID becomes part of the link while the workspace name remains the
link's readable text. The arrow is decorative, so `aria-hidden="true"` prevents it
from adding noise to the link's accessible name.

Update the existing workspace-list styles to make the complete row interactive:

```css
.workspace-list li { min-width:0; border-bottom:1px solid var(--border); }
.workspace-link { min-width:0; display:flex; align-items:center; justify-content:space-between; gap:20px; padding:20px 8px 20px 4px; border-radius:8px; color:var(--text); text-decoration:none; }
.workspace-link:hover { background:#f0f3f5; }
.workspace-link:focus-visible { outline:3px solid #78d6b7; outline-offset:3px; }
.workspace-name { min-width:0; overflow-wrap:anywhere; font-weight:700; }
.workspace-summary { flex:none; display:inline-flex; align-items:center; gap:12px; }
.workspace-meta { color:var(--muted); font-size:13px; }
.workspace-arrow { color:var(--accent); font-size:18px; line-height:1; }
```

Using the anchor as the layout container gives pointer and keyboard users the same
large target. The hover treatment and visible focus outline make that behavior clear
without turning the row into a fake button.

## Follow the resource through the application

Refresh the home page and open a workspace. Its ID appears in the address bar, its
name appears in both the page heading and browser title, and **All workspaces** returns
to the list. Refresh the detail page directly: the URL contains everything the server
needs to load the same database row again.

If you have more than one workspace, open both rows and notice that the controller
and view stay the same while the ID and database result change. Then visit
`/workspaces/999999` and `/workspaces/not-a-number`. Both return 404, but for different
reasons: the first ID has no row, while the second is not a valid workspace ID.

If you are using Git, save this navigation step:

```bash
git add routes/routes.php app/Http/controllers/workspaces/show.php resources/views/welcome.view.php resources/views/workspaces/show.view.php
git commit -m "Add workspace detail pages"
```

Our application now has two real locations: a workspace index and a workspace detail
page. The detail page also exposes the next product need clearly. Next, we will let a
workspace create and keep its own projects.
