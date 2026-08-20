An issue's first title and description are not always its final ones. Requirements
become clearer, mistakes are corrected, and the description grows with what the team
learns. In this lesson we will add a dedicated edit page and update the existing
record without changing its status, project, or identity.

We already know how to create and validate these two fields. Editing adds a new
question: how do we prefill the stored values while still preserving the learner's
attempted values when an update fails validation?

## Separate reading from editing

Open `routes/routes.php`. Add a GET route for the form and a protected POST route for
the update beside the existing issue routes:

```php
$router->get('/workspaces/{workspace}/projects/{project}/issues/{issue}', 'issues/show.php');
$router->get('/workspaces/{workspace}/projects/{project}/issues/{issue}/edit', 'issues/edit.php');
$router->post('/workspaces/{workspace}/projects/{project}/issues/{issue}', 'issues/update.php')->only('csrf');
$router->post('/workspaces/{workspace}/projects/{project}/issues/{issue}/status', 'issues/status.php')->only('csrf');
```

The detail URL remains read mode. Appending `/edit` opens a form without changing
anything, so it is a GET request. Submitting that form posts to the issue's own URL
and passes through CSRF middleware because it changes stored data.

We keep content editing separate from the status endpoint. Updating a title should
not accidentally reopen or close an issue, and changing status should not require
resending the title and description.

## Load the edit form through every parent

Create `app/Http/controllers/issues/edit.php`. Begin with the familiar route
boundary:

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

An edit URL carries the same three external values as a detail URL. Opening a form
does not make those values trusted; malformed segments stop before any query.

Load the workspace, then the project within it:

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

This view needs both names for navigation and context. More importantly, the second
query proves the project belongs to the workspace named in the URL.

Now load the editable fields through that verified project:

```php
$issue = $database
    ->query(
        'SELECT id, title, description FROM issues WHERE id = :id AND project_id = :project_id',
        [
            'id' => (int) $issueId,
            'project_id' => $project['id'],
        ],
    )
    ->findOrFail();

view('issues/edit.view.php', [
    'workspace' => $workspace,
    'project' => $project,
    'issue' => $issue,
]);
```

The form receives only an issue that belongs to the verified project. A real issue
placed under a different project or workspace returns 404 instead of exposing its
content in an untruthful URL.

## Protect the update independently

Create `app/Http/controllers/issues/update.php`. Import the classes needed for
validation and feedback, then repeat the three route checks:

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

The POST request must prove ownership for itself. A visitor may leave an edit page
open while records change, and a forged request never visited that page at all.

Resolve the database and walk down the hierarchy, selecting only the IDs the update
needs:

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
        'SELECT id FROM issues WHERE id = :id AND project_id = :project_id',
        [
            'id' => (int) $issueId,
            'project_id' => $project['id'],
        ],
    )
    ->findOrFail();
```

Only after all three records agree with the URL do we inspect the proposed content.
This ordering means a mismatched resource remains a 404 boundary instead of being
treated as somebody else's editable form.

## Reuse the two-field validation

Normalize the submitted values and collect both errors before throwing:

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

These are the same product rules used during creation: a 2–100 character title and
an optional description up to 1,000 characters. DALT catches the exception, flashes
the errors and old input, then returns to the edit page. Collecting first lets the
form show both problems in one response.

Update the existing row and redirect to read mode:

```php
$database->query(
    'UPDATE issues SET title = :title, description = :description WHERE id = :id',
    [
        'title' => $title,
        'description' => $description,
        'id' => $issue['id'],
    ],
);

Session::flash('success', "{$title} was updated.");

return redirect(
    "/workspaces/{$workspace['id']}/projects/{$project['id']}/issues/{$issue['id']}",
    303,
);
```

The statement changes only `title` and `description`. It does not alter `status`,
`project_id`, or `id`, so the issue keeps its workflow state, owner, URL, and place in
the project count. Bound parameters keep the submitted content separate from SQL.

## Add an edit path from the detail page

Open `resources/views/issues/show.view.php`. Add an outline-link style beside the
existing context and status styles:

```css
.edit-link { display:flex; min-height:42px; margin-top:24px; align-items:center; justify-content:center; border:1px solid var(--border-strong); border-radius:9px; padding:8px 12px; color:var(--text); font-size:14px; font-weight:750; text-decoration:none; }
.edit-link:hover { border-color:#9ba5b1; background:#f0f3f5; }
.edit-link:focus-visible { outline:3px solid #78d6b7; outline-offset:3px; }
```

The primary green control remains the state-changing Close or Reopen action. Editing
is a navigational link, so its quieter outline treatment communicates that it opens
another page before anything is saved.

Inside the Context section, directly after its list, add:

```php
<a class="edit-link" href="/workspaces/<?= $workspaceId ?>/projects/<?= $projectId ?>/issues/<?= (int) ($issue['id'] ?? 0) ?>/edit">Edit issue</a>
```

The link carries the same verified IDs as every other issue action and becomes a
full-width target on both desktop and mobile.

## Prepare stored and attempted form values

Create `resources/views/issues/edit.view.php`. Start by naming the controller values,
then combine database defaults with validation input:

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
$errors = Core\Session::get('errors', []);
$errors = is_array($errors) ? $errors : [];
$titleValue = old('title', $issueTitle);
$titleValue = is_string($titleValue) ? $titleValue : $issueTitle;
$descriptionValue = old('description', $issueDescription);
$descriptionValue = is_string($descriptionValue) ? $descriptionValue : $issueDescription;
?>
```

On the first visit, `old()` has nothing and returns the stored issue values supplied
as defaults. After failed validation, the flashed attempted values win—even an empty
description—so the form never silently replaces the learner's work with older data.

Add the document head and shared application shell:

```php
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title>Edit <?= htmlspecialchars($issueTitle, ENT_QUOTES, 'UTF-8') ?> · DALT Issues</title>
  <style>
    :root { color-scheme:light; --canvas:#f7f8fa; --surface:#fff; --border:#dfe3e8; --border-strong:#c8ced6; --text:#17202a; --muted:#52606d; --accent:#087f5b; }
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

The narrower content width gives the task a focused reading measure while keeping
the same header, colors, and typography as the rest of the application.

Continue inside the style block with the heading and form rules:

```css
.edit-heading { margin-top:40px; padding-bottom:28px; border-bottom:1px solid var(--border-strong); }
h1 { margin:0; overflow-wrap:anywhere; font-size:clamp(36px,6vw,52px); line-height:1.03; letter-spacing:-.04em; }
.introduction { max-width:620px; margin:14px 0 0; color:var(--muted); font-size:15px; line-height:1.65; }
.context { margin:16px 0 0; color:var(--muted); font-size:13px; }
.context a { color:var(--accent); font-weight:700; text-underline-offset:3px; }
.context a:focus-visible { outline:3px solid #78d6b7; outline-offset:3px; }
form { margin-top:36px; }
label { display:block; margin:22px 0 8px; font-size:14px; font-weight:750; }
label:first-of-type { margin-top:0; }
input,textarea { width:100%; border:1px solid var(--border-strong); border-radius:9px; padding:11px 12px; color:var(--text); background:var(--surface); font:inherit; }
input { min-height:46px; }
textarea { min-height:220px; resize:vertical; line-height:1.6; }
input:focus-visible,textarea:focus-visible { outline:3px solid #78d6b7; outline-offset:2px; border-color:var(--accent); }
input[aria-invalid="true"],textarea[aria-invalid="true"] { border-color:#b42318; }
.field-hint,.field-error { margin:7px 0 0; font-size:12px; line-height:1.5; }
.field-hint { color:var(--muted); }
.field-error { color:#a51d14; }
```

The controls match issue creation, including browser limits, server-error styling,
and visible keyboard focus. The taller description field gives existing content
room to be reviewed rather than treating editing like a short reply.

Add the form actions and mobile behavior:

```css
.form-actions { display:flex; align-items:center; gap:18px; margin-top:28px; }
button { min-height:44px; border:0; border-radius:9px; padding:10px 22px; background:var(--accent); color:#fff; font:inherit; font-weight:750; cursor:pointer; }
button:hover { background:#066c4d; }
button:focus-visible { outline:3px solid #78d6b7; outline-offset:3px; }
.cancel-link { color:var(--muted); font-size:14px; font-weight:700; text-underline-offset:3px; }
.cancel-link:hover { color:var(--text); }
@media (max-width:600px) {
  .header-inner,.content { width:min(100% - 32px,760px); }
  .content { padding:40px 0 56px; }
  .edit-heading { margin-top:32px; }
  textarea { min-height:180px; }
  .form-actions { align-items:stretch; flex-direction:column; }
  button { width:100%; }
  .cancel-link { align-self:center; padding:6px; }
}
```

On mobile, Save becomes one large target and Cancel remains visibly separate. Close
the style and head, then render the application header and edit-page identity:

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

    <header class="edit-heading">
      <h1>Edit issue</h1>
      <p class="introduction">Refine the title and description without changing this issue's status or history.</p>
      <p class="context">
        In <a href="/workspaces/<?= $workspaceId ?>/projects/<?= $projectId ?>"><?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') ?></a>
        · <?= htmlspecialchars($workspaceName, ENT_QUOTES, 'UTF-8') ?>
      </p>
    </header>
```

The page states exactly what will and will not change. Both Back and the project link
provide safe navigation before the form begins.

## Render the editable fields

Add the protected form beneath the heading:

```php
<form method="POST" action="/workspaces/<?= $workspaceId ?>/projects/<?= $projectId ?>/issues/<?= $issueId ?>">
  <?= csrf_field() ?>
  <label for="issue-title">Title</label>
  <input
    id="issue-title"
    name="title"
    type="text"
    value="<?= htmlspecialchars($titleValue, ENT_QUOTES, 'UTF-8') ?>"
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
  ><?= htmlspecialchars($descriptionValue, ENT_QUOTES, 'UTF-8') ?></textarea>
  <?php if (is_string($errors['description'] ?? null)): ?>
    <p class="field-error" id="issue-description-error" role="alert"><?= htmlspecialchars($errors['description'], ENT_QUOTES, 'UTF-8') ?></p>
  <?php else: ?>
    <p class="field-hint" id="issue-description-hint">Up to 1,000 characters.</p>
  <?php endif; ?>
```

Every value and error is escaped. The HTML constraints provide immediate browser
feedback, while the controller remains the trusted boundary. Each server error is
connected to its field for assistive technology.

Finish the form and document with Save and Cancel:

```php
  <div class="form-actions">
    <button type="submit">Save changes</button>
    <a class="cancel-link" href="/workspaces/<?= $workspaceId ?>/projects/<?= $projectId ?>/issues/<?= $issueId ?>">Cancel</a>
  </div>
</form>
  </main>
</body>
</html>
```

Save is the only state-changing control. Cancel is a normal link back to the issue,
so it discards the unsent browser values without making a request to the database.

## Edit without changing identity

Open an issue and select **Edit issue**. Change both fields and save. The detail page
should show a one-time confirmation and the revised content; the project list should
show the same title and description. Refresh the detail page: the message disappears
while the update remains.

Edit again with a one-character title and an overlong description. Both messages
should appear together and both attempted values should remain. Save an empty
description to confirm it is optional, and use **Cancel** once to verify unsaved
changes do not reach the database.

Throughout the flow, the issue's URL, project, status, and the project's issue count
must remain unchanged. Missing CSRF returns 419, and mismatching any parent ID in a
real edit or update URL returns 404.

If you are using Git, save the issue-editing slice:

```bash
git add routes/routes.php app/Http/controllers/issues/edit.php app/Http/controllers/issues/update.php resources/views/issues/show.view.php resources/views/issues/edit.view.php
git commit -m "Edit issue details"
```

Our issue content can now evolve without losing its workflow state or history. Next,
we will add deletion carefully, including an explicit confirmation and a redirect to
the owning project after the issue is removed.
