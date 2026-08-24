<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title>DALT.PHP</title>
  <style>
    :root {
      color-scheme: light;
      --canvas: #f7f6f3;
      --surface: #ffffff;
      --text: #242320;
      --muted: #74716b;
      --border: #e5e2dc;
      --button: #242320;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      min-height: 100vh;
      min-height: 100svh;
      background: var(--canvas);
      color: var(--text);
      font-family: "Helvetica Neue", Arial, sans-serif;
    }

    main {
      min-height: 100vh;
      min-height: 100svh;
      display: grid;
      place-items: center;
      padding: 24px;
    }

    .welcome {
      width: min(100%, 520px);
      text-align: center;
    }

    .brand {
      margin: 0 0 28px;
      color: var(--muted);
      font-size: 12px;
      font-weight: 700;
      letter-spacing: .14em;
      text-transform: uppercase;
    }

    h1 {
      margin: 0;
      font-family: Georgia, "Times New Roman", serif;
      font-size: clamp(38px, 7vw, 58px);
      font-weight: 500;
      letter-spacing: -.035em;
      line-height: 1.05;
    }

    .intro {
      max-width: 440px;
      margin: 20px auto 0;
      color: var(--muted);
      font-size: 16px;
      line-height: 1.65;
    }

    .learn {
      display: inline-block;
      margin-top: 28px;
      border-radius: 6px;
      background: var(--button);
      color: #fff;
      padding: 12px 18px;
      font-size: 14px;
      font-weight: 700;
      text-decoration: none;
    }

    .learn:hover { background: #403e39; }
    .learn:focus-visible { outline: 3px solid #b8b3aa; outline-offset: 3px; }

    .remove {
      margin-top: 34px;
      border-top: 1px solid var(--border);
      padding-top: 24px;
    }

    .remove p {
      margin: 0 0 10px;
      color: var(--muted);
      font-size: 13px;
      line-height: 1.5;
    }

    code {
      display: inline-block;
      border: 1px solid var(--border);
      border-radius: 6px;
      background: var(--surface);
      padding: 10px 13px;
      color: var(--text);
      font: 13px/1.4 "SFMono-Regular", Consolas, "Liberation Mono", monospace;
    }

    @media (max-height: 520px) {
      main { place-items: start center; }
      .brand { margin-bottom: 18px; }
      .remove { margin-top: 24px; padding-top: 18px; }
    }
  </style>
</head>
<body>
  <?php $platformInstalled = function_exists('base_path') && is_dir(base_path('.dalt')); ?>
  <main>
    <section class="welcome" aria-labelledby="welcome-title">
      <p class="brand">DALT.PHP</p>
      <h1 id="welcome-title">Your project is ready.</h1>

      <?php if ($platformInstalled): ?>
        <p class="intro">Learn how the framework works, or remove the learning platform and start building your application.</p>
        <a class="learn" href="/learn">Open learning</a>
        <div class="remove">
          <p>Ready to use only the framework?</p>
          <code>php artisan platform:remove</code>
        </div>
      <?php else: ?>
        <p class="intro">The learning platform has been removed. Replace this view with your application.</p>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
