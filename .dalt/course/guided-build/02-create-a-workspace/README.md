Our workspace screen can describe an empty list, but it cannot change that list. In
this lesson we will submit our first form, reject a bad name, create a workspace, and
still see it after the browser follows a redirect.

We will keep the workspaces in the PHP session for now. That gives us real behavior
without introducing a database before we need one. Its limitation will become the
reason for our next change.

## Give the form somewhere to submit

The browser uses `GET /` when it displays our page. Creating something changes
server state, so we will give that action a separate POST route.

Open `routes/routes.php` and add this line below the existing GET route:

```php
$router->post('/workspaces', 'workspaces/store.php')->only('csrf');
```

The route connects `POST /workspaces` to a new controller. `only('csrf')` sends the
request through DALT's CSRF middleware first. A request with no valid token stops
there with status 419; it never reaches our controller.

Create `app/Http/controllers/workspaces/store.php`. Start by reading and normalizing
the submitted name:

```php
<?php

declare(strict_types=1);

use Core\App;
use Core\Request;
use Core\Session;
use Core\ValidationException;
use Core\Validator;

$request = App::resolve(Request::class);
$nameInput = $request->input('name');
$name = is_string($nameInput) ? trim($nameInput) : '';
```

DALT captured the request before routing it, so the controller resolves that same
`Request` object from the application container. `input('name')` reads the field the
form will send. We do not assume it is a string: requests are external input, even
when our own form produced them.

## Reject a name we cannot use

Add the validation immediately below the normalization:

```php
if (!Validator::string($name, 2, 50)) {
    ValidationException::throw(
        ['name' => 'Use between 2 and 50 characters.'],
        ['name' => $name],
    );
}
```

The first array is the error bag. The second is the safe input DALT should remember.
When this exception reaches the front controller, DALT flashes both arrays to the
session and redirects to the previous page. On that next request we can show the
message beside the field and put the learner's text back into it.

We validate in HTML too, but server validation remains necessary. A caller can send
`POST /workspaces` without using our page at all.

## Store the workspace for this session

Continue in the same controller. Read the current list, append the new workspace,
and put the list back:

```php
$workspaces = Session::get('workspaces', []);
$workspaces = is_array($workspaces) ? $workspaces : [];
$workspaces[] = [
    'id' => bin2hex(random_bytes(6)),
    'name' => $name,
];

Session::put('workspaces', $workspaces);
```

Each workspace gets a random identifier now, even though our screen does not link to
it yet. The name is product data. The identifier gives future routes a stable value
that does not depend on the workspace's position in the array.

Finish the controller with a one-request notice and a redirect:

```php
Session::flash('success', "{$name} was created.");

return redirect('/', 303);
```

The response does not render the workspace page directly. Status 303 tells the
browser to make a fresh GET request to `/`. This small pattern matters: refreshing
the finished page repeats the GET, not the workspace-creation POST.

`Session::put` keeps the workspace available across requests in this browser
session. `Session::flash` keeps the notice for only the next request. That is why the
workspace survives a second refresh while “Platform was created” disappears.

## Load workspaces for the page

Our GET controller must now pass the stored list to the view. Replace the contents
of `app/Http/controllers/welcome.php` after `declare(strict_types=1);` with:

```php
use Core\Session;

$workspaces = Session::get('workspaces', []);
$workspaces = is_array($workspaces) ? $workspaces : [];

view('welcome.view.php', ['workspaces' => $workspaces]);
```

The controller owns data loading. The view receives a `$workspaces` variable and can
concentrate on turning that data into HTML.

## Read the next-request state

Open `resources/views/welcome.view.php`. Directly below `<!doctype html>`, add this
small PHP block:

```php
<?php
$errors = Core\Session::get('errors', []);
$errors = is_array($errors) ? $errors : [];
$oldName = old('name');
$oldName = is_string($oldName) ? $oldName : '';
$success = Core\Session::get('success');
$success = is_string($success) ? $success : null;
$workspaceCount = count($workspaces);
?>
```

`errors` and `oldName` exist after failed validation. `success` exists after a
successful creation. On an ordinary request their defaults keep the same template
safe to render.

Change the static count in `.page-heading` to use the real list:

```php
<span class="count"><?= $workspaceCount ?> workspace<?= $workspaceCount === 1 ? '' : 's' ?></span>
```

The small condition gives us “1 workspace” but “0 workspaces” and “2 workspaces”.

## Add the creation form

Inside `<main>`, after `.page-heading`, render the success notice when one exists:

```php
<?php if ($success !== null): ?>
  <p class="notice" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
```

We escape the message because it contains the submitted name. `role="status"` also
lets assistive technology announce the result without moving keyboard focus.

Replace the old empty-state section with the new two-column region. Start the region
and keep the empty state on its left:

```php
<div class="workspace-layout">
  <?php if ($workspaces === []): ?>
    <section class="empty-state" aria-labelledby="empty-title">
      <span class="empty-mark" aria-hidden="true">+</span>
      <h2 id="empty-title">No workspaces yet</h2>
      <p>Create the first shared space for your team’s projects and issues.</p>
    </section>
  <?php else: ?>
    <ol class="workspace-list" aria-label="Workspaces">
      <?php foreach ($workspaces as $workspace): ?>
        <li>
          <span class="workspace-name"><?= htmlspecialchars((string) ($workspace['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
          <span class="workspace-meta">0 projects</span>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>
```

There are now two honest display states. An empty array renders the empty message.
Once the array has entries, `foreach` produces one row per workspace. Names are
escaped at the HTML boundary, including names we previously stored in the session.

Below that conditional, add the form and close `.workspace-layout`:

```php
  <section class="create-panel" aria-labelledby="create-title">
    <h2 id="create-title">Create a workspace</h2>
    <p>Start with the team or product that will own the work.</p>

    <form method="POST" action="/workspaces">
      <?= csrf_field() ?>
      <label for="workspace-name">Workspace name</label>
      <input
        id="workspace-name"
        name="name"
        type="text"
        value="<?= htmlspecialchars($oldName, ENT_QUOTES, 'UTF-8') ?>"
        minlength="2"
        maxlength="50"
        autocomplete="organization"
        required
        aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>"
        <?= isset($errors['name']) ? 'aria-describedby="workspace-name-error"' : '' ?>
      >
      <?php if (is_string($errors['name'] ?? null)): ?>
        <p class="field-error" id="workspace-name-error" role="alert"><?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>
      <button type="submit">Create workspace</button>
    </form>
  </section>
</div>
```

`method="POST"` and `action="/workspaces"` match the route we registered.
`csrf_field()` writes the hidden token that the middleware compares with the token in
the session. The visible input and its error share an ID only when an error exists,
so the browser can describe the invalid field with the server's message.

## Make room for both states

Inside the existing `<style>` element, add the layout and workspace-list rules after
`.count`:

```css
.workspace-layout { display:grid; grid-template-columns:minmax(0,1fr) 320px; gap:28px; align-items:start; margin-top:40px; }
.workspace-layout .empty-state { margin-top:0; }
.workspace-list { margin:0; padding:0; list-style:none; border-top:1px solid var(--border-strong); }
.workspace-list li { min-width:0; display:flex; align-items:center; justify-content:space-between; gap:20px; padding:20px 4px; border-bottom:1px solid var(--border); }
.workspace-name { min-width:0; overflow-wrap:anywhere; font-weight:700; }
.workspace-meta { color:var(--muted); font-size:13px; }
```

`minmax(0, 1fr)` allows the list column to shrink. The two `min-width:0` rules and
`overflow-wrap:anywhere` keep even a valid 50-character name inside a phone screen.

Add the form and feedback styles as a second group:

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
.notice { margin:24px 0 0; padding:12px 14px; border-radius:10px; background:var(--accent-soft); color:#075b43; font-size:14px; font-weight:650; overflow-wrap:anywhere; }
```

Finally, inside the existing `@media (max-width: 600px)` block, replace the old
`.empty-state` rule with these two rules:

```css
.workspace-layout { grid-template-columns:1fr; margin-top:28px; }
.empty-state { padding:44px 20px; }
```

On a narrow screen the form moves below the list instead of squeezing beside it.

## Use the complete flow

Refresh the browser. Enter `Platform` and select **Create workspace**.

The browser sends a protected POST request. The controller validates and stores the
workspace, then returns a redirect. The following GET renders:

- `1 workspace` in the heading;
- a `Platform` row with `0 projects`;
- `Platform was created.` above the list.

Refresh once more. The row remains because it is normal session data. The success
notice disappears because flash data was intended for one following request.

Try a one-character name. The browser's `minlength` stops the normal form submission.
The server repeats the same boundary independently, so callers that bypass the HTML
still receive “Use between 2 and 50 characters.” and get their safe input back.

If you are using Git, save this working behavior:

```bash
git add routes/routes.php app/Http/controllers/welcome.php app/Http/controllers/workspaces/store.php resources/views/welcome.view.php
git commit -m "Create workspaces in the session"
```

We now have our first complete write cycle, but session storage belongs to one
browser session. A different browser cannot see these workspaces, and an expired
session loses them. Next, we will move workspace storage into a database so the
application owns the data instead of one browser.
