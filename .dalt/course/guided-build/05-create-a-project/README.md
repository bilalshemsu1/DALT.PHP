Our workspace page has a Projects section, but its count and empty state are still
static. In this lesson we will model the first relationship in our application: a
workspace can have many projects, while each project belongs to one workspace.

We already know the pieces separately—migrations, protected forms, validation,
database queries, flash data, and redirects. Now we will combine them around a nested
resource and make sure one workspace never displays another workspace's projects.

## Give projects their own table

Create `database/migrations/003_create_projects_table.sql`:

```sql
CREATE TABLE projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    workspace_id INTEGER NOT NULL,
    name VARCHAR(60) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX projects_workspace_id_index ON projects (workspace_id);
```

`workspace_id` carries the relationship. A project row with `workspace_id` equal to
`1` belongs to the workspace whose `id` is `1`. We store the ID instead of repeating
the workspace name, so renaming a workspace later will not require rewriting all of
its projects.

Our detail page will repeatedly ask for projects with one `workspace_id`. The index
gives SQLite an index over that column for those lookups instead of leaving the
relationship as an unindexed value in every row.

At this stage the column records the relationship, but the database does not yet
declare a foreign-key constraint. Our controller will verify the parent workspace
before every insert. When we move the project to PostgreSQL, we will move that
invariant into the database too and learn why application checks alone are not the
final concurrency boundary.

Apply the new migration:

```bash
php artisan migrate
```

DALT reports the one pending file:

```text
Running migration: 003_create_projects_table.sql
✓ Success

Ran 1 migrations.
Migration process completed.
```

## Nest project creation under its workspace

A project cannot be created without knowing which workspace owns it. Open
`routes/routes.php` and add this route below the workspace detail route:

```php
$router->post('/workspaces/{workspace}/projects', 'projects/store.php')->only('csrf');
```

The URL carries the parent workspace ID while the form body will carry the new
project's name. It is a POST route because it changes application state, and it uses
the same CSRF middleware as workspace creation.

Create `app/Http/controllers/projects/store.php`:

```php
<?php

declare(strict_types=1);

use Core\App;
use Core\Database;
use Core\Request;
use Core\Session;
use Core\ValidationException;
use Core\Validator;

$request = App::resolve(Request::class);
$workspaceId = $request->route('workspace');

if (!is_string($workspaceId) || preg_match('/\A[1-9]\d*\z/', $workspaceId) !== 1) {
    abort(404);
}
```

This first boundary is familiar from the workspace detail controller. Even though
our own form will submit a numeric ID, the route remains public input and must not be
trusted because we generated the link.

Continue by resolving the database and loading the parent:

```php
$database = App::resolve(Database::class);
$workspace = $database
    ->query(
        'SELECT id, name FROM workspaces WHERE id = :id',
        ['id' => (int) $workspaceId],
    )
    ->findOrFail();
```

This is more than fetching a value for the redirect. It proves the parent exists
before we create a child row. A POST to `/workspaces/999999/projects` stops with 404
instead of creating a project that belongs nowhere.

Read and validate the name below that query:

```php
$nameInput = $request->input('name');
$name = is_string($nameInput) ? trim($nameInput) : '';

if (!Validator::string($name, 2, 60)) {
    ValidationException::throw(
        ['name' => 'Use between 2 and 60 characters.'],
        ['name' => $name],
    );
}
```

Workspace and project names are different fields in different forms, so they do not
need the same length. The mechanics stay consistent: normalize external input,
validate on the server, and flash only safe old input when validation fails.

Finish the controller with the insert and redirect:

```php
$database->query(
    'INSERT INTO projects (workspace_id, name) VALUES (:workspace_id, :name)',
    [
        'workspace_id' => $workspace['id'],
        'name' => $name,
    ],
);

Session::flash('success', "{$name} was created.");

return redirect("/workspaces/{$workspace['id']}", 303);
```

Both values are bound separately from the SQL. The project receives its parent ID
from the workspace row we actually found, not directly from unchecked form data.
After insertion, the browser follows the 303 back to that workspace's GET page.

## Load only this workspace's projects

Open `app/Http/controllers/workspaces/show.php`. Keep the workspace lookup, then add
the project query directly below it:

```php
$projects = $database
    ->query(
        'SELECT id, name FROM projects WHERE workspace_id = :workspace_id ORDER BY id DESC',
        ['workspace_id' => $workspace['id']],
    )
    ->get();
```

The `WHERE` clause is the read side of our relationship. We do not select every
project and ask PHP to filter the array; the database returns only rows owned by the
workspace on this page. `ORDER BY id DESC` keeps the newest project first.

Replace the existing call to `view()` so it passes both results:

```php
view('workspaces/show.view.php', [
    'workspace' => $workspace,
    'projects' => $projects,
]);
```

The view can now derive its empty state, count, and rows from one real `$projects`
array.

## Prepare form and list state

At the top of `resources/views/workspaces/show.view.php`, extend the existing PHP
setup block:

```php
$workspaceId = (int) ($workspace['id'] ?? 0);
$workspaceName = (string) ($workspace['name'] ?? '');
$errors = Core\Session::get('errors', []);
$errors = is_array($errors) ? $errors : [];
$oldName = old('name');
$oldName = is_string($oldName) ? $oldName : '';
$success = Core\Session::get('success');
$success = is_string($success) ? $success : null;
$projectCount = count($projects);
```

The validation and flash values follow the same next-request lifecycle as the
workspace form. `$projectCount` is ordinary page data: it changes whenever the
controller's query returns a different number of rows.

Below `.workspace-heading`, show successful creation when a notice exists:

```php
<?php if ($success !== null): ?>
  <p class="notice" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
```

## Replace the static Projects section

Remove the old section heading and empty-state block. In their place, add a layout
that can show the real list beside the creation form:

```php
<div class="projects-layout">
  <section aria-labelledby="projects-title">
    <div class="section-heading">
      <h2 id="projects-title">Projects</h2>
      <span class="count"><?= $projectCount ?> project<?= $projectCount === 1 ? '' : 's' ?></span>
    </div>

    <?php if ($projects === []): ?>
      <div class="empty-state">
        <span class="empty-mark" aria-hidden="true">+</span>
        <h3>No projects yet</h3>
        <p>Create the first project to start organizing this workspace’s issues.</p>
      </div>
    <?php else: ?>
      <ol class="project-list">
        <?php foreach ($projects as $project): ?>
          <li>
            <span class="project-name"><?= htmlspecialchars((string) ($project['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="project-meta">0 issues</span>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </section>
```

An empty query result keeps the useful first-project guidance. A non-empty result
renders one escaped name per row and replaces the static count. `0 issues` remains
honest because we have not built issues yet.

Continue inside `.projects-layout` with the form, then close the layout:

```php
  <section class="create-panel" aria-labelledby="create-project-title">
    <h2 id="create-project-title">Create a project</h2>
    <p>Give this workspace a focused area for related issues.</p>

    <form method="POST" action="/workspaces/<?= $workspaceId ?>/projects">
      <?= csrf_field() ?>
      <label for="project-name">Project name</label>
      <input
        id="project-name"
        name="name"
        type="text"
        value="<?= htmlspecialchars($oldName, ENT_QUOTES, 'UTF-8') ?>"
        minlength="2"
        maxlength="60"
        autocomplete="off"
        required
        aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>"
        <?= isset($errors['name']) ? 'aria-describedby="project-name-error"' : '' ?>
      >
      <?php if (is_string($errors['name'] ?? null)): ?>
        <p class="field-error" id="project-name-error" role="alert"><?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>
      <button type="submit">Create project</button>
    </form>
  </section>
</div>
```

The workspace ID in the form action connects this particular page to its nested POST
route. The CSRF token protects the state-changing request, and the field connects
server errors back to the same accessible input.

## Style both project states

Inside the existing `<style>` element, add the feedback, two-column layout, and list
rules:

```css
.notice { margin:24px 0 0; padding:12px 14px; border-radius:10px; background:var(--accent-soft); color:#075b43; font-size:14px; font-weight:650; overflow-wrap:anywhere; }
.projects-layout { display:grid; grid-template-columns:minmax(0,1fr) 320px; gap:28px; align-items:start; margin-top:48px; }
.section-heading { display:flex; align-items:end; justify-content:space-between; gap:20px; }
.project-list { margin:18px 0 0; padding:0; list-style:none; border-top:1px solid var(--border-strong); }
.project-list li { min-width:0; display:flex; align-items:center; justify-content:space-between; gap:20px; padding:20px 4px; border-bottom:1px solid var(--border); }
.project-name { min-width:0; overflow-wrap:anywhere; font-weight:700; }
.project-meta { flex:none; color:var(--muted); font-size:13px; }
```

Add the form-panel and field rules after them:

```css
.create-panel { padding:22px; border:1px solid var(--border); border-radius:14px; background:var(--surface); }
.create-panel h2 { margin:0; font-size:18px; letter-spacing:-.015em; }
.create-panel > p { margin:8px 0 20px; color:var(--muted); font-size:14px; line-height:1.55; }
label { display:block; margin-bottom:8px; font-size:14px; font-weight:700; }
input { width:100%; min-height:44px; border:1px solid var(--border-strong); border-radius:9px; padding:10px 12px; color:var(--text); font:inherit; }
input:focus-visible { outline:3px solid #78d6b7; outline-offset:2px; border-color:var(--accent); }
input[aria-invalid="true"] { border-color:#b42318; }
.field-error { margin:8px 0 0; color:#a51d14; font-size:13px; line-height:1.5; }
button { width:100%; min-height:44px; margin-top:16px; border:0; border-radius:9px; padding:10px 14px; background:var(--accent); color:#fff; font:inherit; font-weight:750; cursor:pointer; }
button:hover { background:#066c4d; }
button:focus-visible { outline:3px solid #78d6b7; outline-offset:3px; }
```

Finally, replace the old `.section-heading` mobile adjustment inside the existing
media query with:

```css
.projects-layout { grid-template-columns:1fr; margin-top:40px; }
```

The project list remains the primary content. On wide screens the form sits beside
it; on a phone the form moves below the list and keeps its full-width input and
button.

## Create projects in separate workspaces

Refresh a workspace page and create `Website Launch`. The POST inserts the project,
redirects back to the workspace, and the following GET shows:

- `Website Launch was created.` once;
- `1 project` in the real count;
- a `Website Launch` row with `0 issues`.

Refresh again. The project remains while the flash notice disappears. Try a
one-character name: normal browser validation stops it, and the server boundary also
returns “Use between 2 and 60 characters.” when HTML validation is bypassed.

Open a different workspace. Its count remains `0 projects` until you create a project
there. This is the visible proof that both the INSERT and SELECT carry the owning
workspace ID.

If you are using Git, save the complete relationship and creation flow:

```bash
git add database/migrations/003_create_projects_table.sql routes/routes.php app/Http/controllers/projects/store.php app/Http/controllers/workspaces/show.php resources/views/workspaces/show.view.php
git commit -m "Create projects inside workspaces"
```

We now have two levels of real application data: workspaces and their projects. The
project rows are not destinations yet, and issues still have nowhere to belong. Next,
we will open an individual project and establish the location where its issues can
grow.
