By the end of this lesson, the generic framework welcome page will be gone. In its
place, we will have the first screen of **DALT Issues**: an honest empty workspace
list that runs in the browser.

We are keeping this first step small. There is no database, React application, or
workspace form yet. We will add each of those when the product needs it.

## Open the project

Open the terminal for your project. The terminal should be in the directory that
contains `artisan`, `routes/`, and `resources/`.

Run DALT's development server:

```bash
php artisan serve
```

DALT prints the address it selected:

```text
Starting development server: http://127.0.0.1:8000
```

Open that address in the browser. If port 8000 was already busy, DALT selects the
next free port; use the address printed in your terminal.

You should see the DALT.PHP welcome page. That confirms the project can already
receive a browser request and return HTML. Keep this terminal running while we edit.

## Give the project a starting point

Git lets us save named snapshots as the application grows. It is useful, but it is
not a gate for this course.

Check whether it is available:

```bash
git --version
```

If your terminal says that `git` is not found, continue to the next section. The
**Install Git** link above is there when you want it; the application does not need
Git in order to run.

If Git is available and this project is not already a repository, initialize it:

```bash
git init
git add .
git commit -m "Start DALT Issues"
```

`git init` creates the private `.git` directory where Git stores the project's
history. `git add .` selects the current files, and `git commit` records the first
snapshot. None of this uploads the project anywhere.

If Git asks for your name and email, follow the short setup message it prints and
then run the commit again. If `git status` already works in this project, somebody
has initialized it for you, so you can skip `git init`.

## Follow the first request

Our browser requested `/`. DALT found the matching line in
`routes/routes.php`:

```php
$router->get('/', 'welcome.php');
```

The first argument is the URL. The second tells DALT which controller should handle
it. Open `app/Http/controllers/welcome.php` and we find one instruction:

```php
view('welcome.view.php');
```

The controller renders `resources/views/welcome.view.php`. That view contains the
HTML currently visible in the browser. We do not need a new route or controller for
our first screen—the existing request already reaches exactly the file we need.

## Rename the page

Open `resources/views/welcome.view.php`. In its `<head>`, change the color scheme and
title:

```html
<meta name="color-scheme" content="light">
<title>Workspaces · DALT Issues</title>
```

The title appears in the browser tab. The color-scheme hint also tells the browser
that this page uses light controls and surfaces.

Now empty the existing `<style>` element. We will rebuild it in four focused pieces.
Start with the page palette and defaults:

```css
  :root { color-scheme:light; --canvas:#f7f8fa; --surface:#fff; --border:#dfe3e8; --border-strong:#c8ced6; --text:#17202a; --muted:#52606d; --accent:#087f5b; --accent-soft:#dff7ed; }
  * { box-sizing: border-box; }
  body { margin:0; min-height:100vh; background:var(--canvas); color:var(--text); font-family:ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
```

The variables name the small palette we will reuse. The page has two main surfaces:
a white header and a quiet gray canvas. The system font stack works without a font
download.

Below those rules, add the header and page-heading layout:

```css
  .site-header { background: var(--surface); border-bottom: 1px solid var(--border); }
  .header-inner, .content { width: min(960px, calc(100% - 40px)); margin: 0 auto; }
  .header-inner { min-height:64px; display:flex; align-items:center; justify-content:space-between; gap:20px; }
  .brand { display:inline-flex; align-items:center; gap:10px; color:var(--text); font-size:15px; font-weight:760; letter-spacing:-.02em; text-decoration:none; }
  .brand-mark { width: 10px; height: 24px; border-radius: 3px; background: var(--accent); }
  .environment { color: var(--muted); font-size: 13px; }
  .content { padding: 72px 0; }
  .page-heading { display:flex; align-items:end; justify-content:space-between; gap:24px; padding-bottom:24px; border-bottom:1px solid var(--border-strong); }
  h1 { margin:0; font-size:clamp(32px,5vw,46px); line-height:1.05; letter-spacing:-.035em; }
  .introduction { max-width:620px; margin:14px 0 0; color:var(--muted); font-size:16px; line-height:1.65; }
  .count { flex: none; color: var(--muted); font-size: 14px; font-weight: 650; }
```

Both `.header-inner` and `.content` share the same width, so the brand and workspace
heading line up. Flexbox places the count opposite the title while there is room.

Add the empty-state rules next:

```css
  .empty-state { margin-top:40px; padding:56px 24px; border:1px dashed var(--border-strong); border-radius:14px; background:var(--surface); text-align:center; }
  .empty-mark { display:grid; width:44px; height:44px; margin:0 auto 18px; place-items:center; border-radius:12px; background:var(--accent-soft); color:var(--accent); font-size:22px; font-weight:700; }
  .empty-state h2 { margin: 0; font-size: 18px; letter-spacing: -0.015em; }
  .empty-state p { max-width:460px; margin:10px auto 0; color:var(--muted); font-size:14px; line-height:1.65; }
```

The dashed boundary distinguishes “nothing here yet” from a normal workspace row.
The plus is decorative, so our HTML will hide it from screen readers.

Finish the `<style>` element with the narrow-screen adjustment:

```css
  @media (max-width: 600px) {
    .header-inner, .content { width: min(100% - 32px, 960px); }
    .content { padding: 48px 0; }
    .page-heading { align-items: start; flex-direction: column; gap: 16px; }
    .empty-state { margin-top: 28px; padding: 44px 20px; }
  }
```

This media query moves the workspace count below the title on narrow screens instead
of squeezing both onto one line.

## Render the empty workspace list

Replace the old `<body>` element with this product shell:

```html
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
    <header class="page-heading">
      <div>
        <h1>Your workspaces</h1>
        <p class="introduction">Workspaces keep a team’s projects, members, and issues together.</p>
      </div>
      <span class="count">0 workspaces</span>
    </header>

    <section class="empty-state" aria-labelledby="empty-title">
      <span class="empty-mark" aria-hidden="true">+</span>
      <h2 id="empty-title">No workspaces yet</h2>
      <p>Your first workspace will appear here. From there, we will organize projects and track their issues.</p>
    </section>
  </main>
</body>
```

Refresh the browser. The development server reads the PHP view again for every
request, so there is no frontend build command in this lesson. We have also avoided
adding a button that cannot work yet. The empty state tells the truth about the
current product: the workspace count is zero, and there is nothing to list.

Resize the browser until it is narrow. The count should move under the introduction,
and the empty state should remain readable without horizontal scrolling.

## Save the first product change

If you are using Git, record the one application file we changed:

```bash
git add resources/views/welcome.view.php
git commit -m "Build the workspace empty state"
```

That focused commit gives us a safe point before the project gains behavior.

Our screen now has an obvious missing capability: it describes where workspaces will
appear, but there is no way to create one. Next, we will make that need real by adding
our first form and POST route.
