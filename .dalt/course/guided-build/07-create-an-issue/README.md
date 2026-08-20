Our project page has an Issues section, but it cannot record any work yet. In this
lesson we will give an issue a database table, create it inside a specific project,
and replace the empty state with the project's real issue list.

This is our first resource three levels deep: an issue belongs to a project, and the
project belongs to a workspace. We will keep that entire hierarchy truthful on both
the write and read paths.

## Give issues their own table

Create `database/migrations/004_create_issues_table.sql`:

```sql
CREATE TABLE issues (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX issues_project_id_index ON issues (project_id);
```

`project_id` records which project owns the issue. The index supports the query we
will soon run whenever a project page asks for all of its issues.

The title is required and short enough to scan in a list. The description is
optional in our form, but the database represents “no description” as an empty
string instead of two possible empty values, `NULL` and `''`. Every new issue begins
with the status `open`, and `created_at` records when SQLite inserted it.

SQLite accepts the `VARCHAR(100)` declaration but does not use the number to enforce
a 100-character limit. Our server validation will enforce that product rule. Later,
when we move this application to PostgreSQL, the database's type rules will be
stricter too. For now, the schema communicates intent while PHP protects the input.

Run the new migration:

```bash
php artisan migrate
```

The first run should include:

```text
Running migration: 004_create_issues_table.sql
✓ Success

Ran 1 migrations.
Migration process completed.
```

Run the command once more. DALT should report that there are no migrations to run,
because it records which migration files have already been applied.

## Route creation through the full hierarchy

Open `routes/routes.php` and add a POST route beside the project detail route:

```php
$router->get('/workspaces/{workspace}/projects/{project}', 'projects/show.php');
$router->post('/workspaces/{workspace}/projects/{project}/issues', 'issues/store.php')->only('csrf');
```

The form will post to the project currently on screen. Both route values must reach
the controller because a project ID is only valid here when it belongs to the named
workspace. The `csrf` middleware protects this state-changing request in the same
way it protects workspace and project creation.

## Verify the parent resources before inserting

Create `app/Http/controllers/issues/store.php`. Start by resolving the request and
validating both dynamic URL segments:

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

This is the same boundary used by the project page. A POST request does not get to
trust route values merely because they came from our own form; a request can be
constructed without using the browser interface at all.

Continue by loading the workspace, then loading the project through that workspace:

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
        'SELECT id, name FROM projects WHERE id = :id AND workspace_id = :workspace_id',
        [
            'id' => (int) $projectId,
            'workspace_id' => $workspace['id'],
        ],
    )
    ->findOrFail();
```

The second query is deliberately scoped by both IDs. Posting to
`/workspaces/2/projects/1/issues` cannot add an issue to project `1` if that project
belongs to workspace `1`. A mismatched or missing parent returns 404 before any issue
data is considered.

## Validate all issue fields together

Read and normalize the two form values, then collect their errors:

```php
$titleInput = $request->input('title');
$descriptionInput = $request->input('description');
$title = is_string($titleInput) ? trim($titleInput) : '';
$description = is_string($descriptionInput) ? trim($descriptionInput) : '';
$errors = [];

if (!Validator::string($title, 2, 100)) {
    $errors['title'] = 'Use between 2 and 100 characters.';
}

if (!Validator::string($description, 0, 1000)) {
    $errors['description'] = 'Keep the description under 1,000 characters.';
}

if ($errors !== []) {
    ValidationException::throw($errors, [
        'title' => $title,
        'description' => $description,
    ]);
}
```

Unlike our earlier single-field forms, we do not throw immediately after checking
the title. Both checks run first, so one response can explain every invalid field.
The description's minimum is `0`, which makes an empty description valid, while its
maximum prevents an unexpectedly large value. Both trimmed values travel back as old
input if validation fails.

Now insert the issue and return to its project:

```php
$database->query(
    'INSERT INTO issues (project_id, title, description, status)
     VALUES (:project_id, :title, :description, :status)',
    [
        'project_id' => $project['id'],
        'title' => $title,
        'description' => $description,
        'status' => 'open',
    ],
);

Session::flash('success', "{$title} was created.");

return redirect(
    "/workspaces/{$workspace['id']}/projects/{$project['id']}",
    303,
);
```

The bound parameters keep data separate from SQL. We explicitly insert `open` so
the application's decision is visible here, while the database default remains a
safe fallback for other insert paths. The 303 redirect turns a successful POST into
a fresh GET, so refreshing the project page does not create the issue again.

## Read only this project's issues

Open `app/Http/controllers/projects/show.php`. After the existing scoped project
query, load its issues newest first:

```php
$issues = $database
    ->query(
        'SELECT id, title, description, status
         FROM issues
         WHERE project_id = :project_id
         ORDER BY id DESC',
        ['project_id' => $project['id']],
    )
    ->get();
```

`WHERE project_id = :project_id` gives each project an isolated list. `ORDER BY id
DESC` puts the issue inserted most recently at the top. We use `get()` because zero,
one, or many rows are all valid outcomes.

Pass the collection to the view:

```php
view('projects/show.view.php', [
    'workspace' => $workspace,
    'project' => $project,
    'issues' => $issues,
]);
```

## Prepare the project page for the flow

Open `resources/views/projects/show.view.php`. Extend its opening PHP block with the
form state and issue count:

```php
$errors = Core\Session::get('errors', []);
$errors = is_array($errors) ? $errors : [];
$oldTitle = old('title');
$oldTitle = is_string($oldTitle) ? $oldTitle : '';
$oldDescription = old('description');
$oldDescription = is_string($oldDescription) ? $oldDescription : '';
$success = Core\Session::get('success');
$success = is_string($success) ? $success : null;
$issueCount = count($issues);
```

Errors, old input, and success are temporary session values produced by the
redirects. The issues themselves come from the database and therefore remain after
the session values disappear.

Below the project heading, render the success message and begin a two-column issues
area:

```php
<?php if ($success !== null): ?>
  <p class="notice" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<div class="issues-layout">
  <section aria-labelledby="issues-title">
    <div class="section-heading">
      <h2 id="issues-title">Issues</h2>
      <span class="count"><?= $issueCount ?> issue<?= $issueCount === 1 ? '' : 's' ?></span>
    </div>
```

The count now comes from the same collection we will render, including correct
singular and plural labels. `role="status"` lets assistive technology announce the
confirmation without moving focus.

Replace the old fixed empty state with a branch for empty and populated projects:

```php
<?php if ($issues === []): ?>
  <div class="empty-state">
    <span class="empty-mark" aria-hidden="true">
      <svg viewBox="0 0 24 24" focusable="false">
        <path d="M7 7.5h10M7 12h7M7 16.5h5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
      </svg>
    </span>
    <h3>No issues yet</h3>
    <p>Create the first issue to capture a concrete piece of work.</p>
  </div>
<?php else: ?>
  <ol class="issue-list">
    <?php foreach ($issues as $issue): ?>
      <li>
        <div class="issue-line">
          <h3 class="issue-title"><?= htmlspecialchars((string) ($issue['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
          <span class="status"><?= ucfirst(htmlspecialchars((string) ($issue['status'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></span>
        </div>
        <?php if (($issue['description'] ?? '') !== ''): ?>
          <p class="issue-description"><?= htmlspecialchars((string) $issue['description'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
<?php endif; ?>
  </section>
```

Every database value is escaped before entering HTML. An empty description produces
no empty paragraph, and `white-space: pre-wrap` in the styles below will preserve
meaningful line breaks in descriptions that do exist.

## Add the creation form

Still inside `.issues-layout`, add the form after the list section:

```php
<section class="create-panel" aria-labelledby="create-issue-title">
  <h2 id="create-issue-title">Create an issue</h2>
  <p>Capture one clear piece of work for this project.</p>

  <form method="POST" action="/workspaces/<?= $workspaceId ?>/projects/<?= (int) ($project['id'] ?? 0) ?>/issues">
    <?= csrf_field() ?>
    <label for="issue-title">Title</label>
    <input
      id="issue-title"
      name="title"
      type="text"
      value="<?= htmlspecialchars($oldTitle, ENT_QUOTES, 'UTF-8') ?>"
      minlength="2"
      maxlength="100"
      required
      aria-invalid="<?= isset($errors['title']) ? 'true' : 'false' ?>"
      <?= isset($errors['title']) ? 'aria-describedby="issue-title-error"' : '' ?>
    >
    <?php if (is_string($errors['title'] ?? null)): ?>
      <p class="field-error" id="issue-title-error" role="alert"><?= htmlspecialchars($errors['title'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <label for="issue-description">Description <span class="field-hint">(optional)</span></label>
    <textarea
      id="issue-description"
      name="description"
      maxlength="1000"
      aria-invalid="<?= isset($errors['description']) ? 'true' : 'false' ?>"
      <?= isset($errors['description']) ? 'aria-describedby="issue-description-error"' : 'aria-describedby="issue-description-hint"' ?>
    ><?= htmlspecialchars($oldDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
    <?php if (is_string($errors['description'] ?? null)): ?>
      <p class="field-error" id="issue-description-error" role="alert"><?= htmlspecialchars($errors['description'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php else: ?>
      <p class="field-hint" id="issue-description-hint">Up to 1,000 characters.</p>
    <?php endif; ?>

    <button type="submit">Create issue</button>
  </form>
</section>
</div>
```

The `<textarea>` gives a multi-line description a control designed for longer text.
Browser constraints provide quick feedback, while the PHP checks remain the trusted
rules because HTML attributes can be bypassed. When PHP rejects the request, each
message is connected to its field with `aria-describedby` and both old values are
restored.

Add these rules to the existing `<style>` block. They turn the Issues area into a
list and form on wider screens, then place the form below the list on a phone:

```css
.notice { margin:24px 0 0; padding:12px 14px; border-radius:10px; background:var(--accent-soft); color:#075b43; font-size:14px; font-weight:650; overflow-wrap:anywhere; }
.issues-layout { display:grid; grid-template-columns:minmax(0,1fr) 340px; gap:28px; align-items:start; margin-top:48px; }
.issue-list { margin:18px 0 0; padding:0; list-style:none; border-top:1px solid var(--border-strong); }
.issue-list li { min-width:0; padding:20px 4px; border-bottom:1px solid var(--border); }
.issue-line { display:flex; align-items:start; justify-content:space-between; gap:18px; }
.issue-title { min-width:0; margin:0; overflow-wrap:anywhere; font-size:15px; font-weight:750; line-height:1.45; }
.status { flex:none; padding:4px 8px; border-radius:999px; background:var(--accent-soft); color:#075b43; font-size:11px; font-weight:800; line-height:1.2; }
.issue-description { margin:8px 0 0; color:var(--muted); font-size:13px; line-height:1.6; overflow-wrap:anywhere; white-space:pre-wrap; }
.create-panel { padding:22px; border:1px solid var(--border); border-radius:14px; background:var(--surface); }
.create-panel h2 { margin:0; font-size:18px; letter-spacing:-.015em; }
.create-panel > p { margin:8px 0 20px; color:var(--muted); font-size:14px; line-height:1.55; }
label { display:block; margin:16px 0 8px; font-size:14px; font-weight:700; }
label:first-of-type { margin-top:0; }
input,textarea { width:100%; border:1px solid var(--border-strong); border-radius:9px; padding:10px 12px; color:var(--text); background:var(--surface); font:inherit; }
input { min-height:44px; }
textarea { min-height:116px; resize:vertical; line-height:1.5; }
input:focus-visible,textarea:focus-visible { outline:3px solid #78d6b7; outline-offset:2px; border-color:var(--accent); }
input[aria-invalid="true"],textarea[aria-invalid="true"] { border-color:#b42318; }
.field-hint,.field-error { margin:7px 0 0; font-size:12px; line-height:1.5; }
.field-hint { color:var(--muted); }
.field-error { color:#a51d14; }
button { width:100%; min-height:44px; margin-top:18px; border:0; border-radius:9px; padding:10px 14px; background:var(--accent); color:#fff; font:inherit; font-weight:750; cursor:pointer; }
button:hover { background:#066c4d; }
button:focus-visible { outline:3px solid #78d6b7; outline-offset:3px; }
```

Inside the existing mobile media query, add:

```css
.issues-layout { grid-template-columns:1fr; margin-top:40px; }
```

## Use the complete creation flow

Open a project and create an issue with a title and a multi-line description. It
should appear at the top of the list with an **Open** status, the count should become
`1 issue`, and the success notice should appear once. Refresh: the notice should
disappear while the database-backed issue remains.

Create another issue without a description, then open a project in another
workspace. Each page should show only its own issues. Try a one-character title; the
form should explain the title rule and preserve the description you entered.

If you are using Git, save this complete vertical slice:

```bash
git add database/migrations/004_create_issues_table.sql routes/routes.php app/Http/controllers/issues/store.php app/Http/controllers/projects/show.php resources/views/projects/show.view.php
git commit -m "Create issues inside projects"
```

Our tracker can now capture durable work inside the correct project. Next, we will
give each issue its own nested page so selecting a row can open the full record and
prepare us for changing its state.
