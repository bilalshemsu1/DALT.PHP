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
      --canvas: #cbc4b8;
      --paper: #ddd7cc;
      --ink: #20221e;
      --muted: #66675f;
      --line: #b7b0a5;
      --accent: #255c48;
      --accent-soft: #c5d1c5;
    }

    * { box-sizing: border-box; }

    html { background: var(--canvas); }

    body {
      margin: 0;
      min-height: 100vh;
      min-height: 100svh;
      background: var(--canvas);
      color: var(--ink);
      font-family: ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      padding: 14px;
    }

    a { color: inherit; }

    .page {
      width: min(1180px, 100%);
      min-height: calc(100vh - 28px);
      min-height: calc(100svh - 28px);
      margin: 0 auto;
      border: 1px solid var(--line);
      background: var(--paper);
      display: grid;
      grid-template-rows: auto 1fr auto auto;
      padding: clamp(22px, 3.2vw, 42px);
    }

    .topbar,
    .foot,
    .status,
    .community {
      display: flex;
      align-items: center;
    }

    .topbar {
      justify-content: space-between;
      gap: 24px;
    }

    .brand {
      font-size: 14px;
      font-weight: 800;
      letter-spacing: -.02em;
      text-decoration: none;
    }

    .status {
      gap: 8px;
      color: var(--accent);
      font: 700 11px/1.2 ui-monospace, "SFMono-Regular", Consolas, monospace;
      letter-spacing: .06em;
      text-transform: uppercase;
    }

    .status-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: var(--accent);
      animation: breathe 2.6s ease-out infinite;
    }

    @keyframes breathe {
      0%, 100% { box-shadow: 0 0 0 0 rgb(37 92 72 / 0); }
      40% { box-shadow: 0 0 0 5px rgb(37 92 72 / .12); }
    }

    .content {
      display: grid;
      grid-template-columns: minmax(0, .88fr) minmax(390px, 1.12fr);
      align-items: center;
      gap: clamp(44px, 8vw, 112px);
      padding: clamp(34px, 6vh, 68px) 0;
    }

    h1 {
      max-width: 590px;
      margin: 0;
      font-size: clamp(44px, 6vw, 76px);
      line-height: .98;
      letter-spacing: -.04em;
      text-wrap: balance;
    }

    .intro {
      max-width: 540px;
      margin: 22px 0 0;
      color: var(--muted);
      font-size: clamp(15px, 1.6vw, 18px);
      line-height: 1.65;
    }

    .paths {
      border-top: 1px solid var(--line);
    }

    .path {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      align-items: center;
      gap: 18px;
      border-bottom: 1px solid var(--line);
      padding: 18px 2px;
      text-decoration: none;
      transition: color 160ms ease-out, transform 160ms ease-out;
    }

    .path:hover {
      color: var(--accent);
      transform: translateX(4px);
    }

    .path strong {
      display: block;
      font-size: 15px;
      line-height: 1.3;
    }

    .path span {
      display: block;
      margin-top: 5px;
      color: var(--muted);
      font-size: 12.5px;
      line-height: 1.45;
    }

    .path svg {
      width: 18px;
      height: 18px;
      color: var(--accent);
      transition: transform 160ms ease-out;
    }

    .path:hover svg { transform: translateX(3px); }

    .remove {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 22px;
      border-top: 1px solid var(--line);
      padding: 18px 0;
    }

    .remove p {
      margin: 0;
      color: var(--muted);
      font-size: 12.5px;
      line-height: 1.45;
    }

    code {
      display: inline-block;
      flex: none;
      border-radius: 5px;
      background: var(--ink);
      color: var(--paper);
      padding: 10px 13px;
      font: 12px/1.3 ui-monospace, "SFMono-Regular", Consolas, monospace;
      user-select: all;
    }

    .foot {
      justify-content: space-between;
      gap: 28px;
      border-top: 1px solid var(--line);
      padding-top: 18px;
      color: var(--muted);
      font-size: 11.5px;
    }

    .foot a {
      text-decoration-color: transparent;
      text-underline-offset: 3px;
    }

    .foot a:hover {
      color: var(--accent);
      text-decoration-color: currentColor;
    }

    .community {
      flex-wrap: wrap;
      justify-content: flex-end;
      gap: 7px 14px;
    }

    .star {
      border-radius: 999px;
      background: var(--accent-soft);
      color: #174333;
      padding: 7px 10px;
      font-weight: 750;
      text-decoration: none;
    }

    a:focus-visible {
      outline: 3px solid var(--accent);
      outline-offset: 4px;
    }

    @media (max-width: 780px) {
      body { padding: 0; }
      .page {
        min-height: 100vh;
        min-height: 100svh;
        border: 0;
        padding: 20px;
      }
      .content {
        grid-template-columns: 1fr;
        align-content: center;
        gap: 26px;
        padding: 32px 0;
      }
      h1 { max-width: 520px; font-size: clamp(40px, 12vw, 58px); }
      .intro { margin-top: 15px; font-size: 14px; line-height: 1.55; }
      .path { padding: 13px 1px; }
      .remove { align-items: flex-start; padding: 15px 0; }
      .foot { align-items: flex-start; }
      .community { justify-content: flex-end; }
    }

    @media (max-width: 520px) {
      .topbar { gap: 14px; }
      .remove, .foot { align-items: flex-start; flex-direction: column; gap: 11px; }
      .community { justify-content: flex-start; }
    }

    @media (max-height: 700px) and (min-width: 781px) {
      .page { padding-block: 20px; }
      .content { padding-block: 24px; }
      h1 { font-size: clamp(42px, 5.5vw, 64px); }
      .intro { margin-top: 15px; font-size: 15px; }
      .path { padding-block: 13px; }
      .remove { padding-block: 13px; }
      .foot { padding-top: 13px; }
    }

    @media (max-width: 520px) and (max-height: 720px) {
      .page { padding: 14px 16px; }
      .content { gap: 16px; padding: 17px 0; }
      h1 { font-size: 36px; }
      .intro { margin-top: 9px; font-size: 12.5px; line-height: 1.4; }
      .path { gap: 10px; padding-block: 8px; }
      .path strong { font-size: 13.5px; }
      .path span { margin-top: 3px; font-size: 11px; line-height: 1.3; }
      .remove { gap: 8px; padding-block: 10px; }
      .remove p { font-size: 11px; line-height: 1.35; }
      code { padding: 8px 10px; font-size: 10.5px; }
      .foot { flex-direction: row; align-items: center; gap: 9px; padding-top: 10px; font-size: 10px; }
      .community { justify-content: flex-end; gap: 5px 9px; }
      .star { padding: 6px 8px; }
    }

    @media (max-width: 340px) and (max-height: 600px) {
      .page { padding: 10px 14px; }
      .content { padding-block: 12px; }
      h1 { font-size: 34px; }
      .foot { padding-top: 8px; }
    }

    @media (prefers-reduced-motion: reduce) {
      .status-dot { animation: none; }
      .path, .path svg { transition: none; }
    }
  </style>
</head>
<body>
  <?php $platformInstalled = function_exists('base_path') && is_dir(base_path('.dalt')); ?>
  <div class="page">
    <header class="topbar">
      <a class="brand" href="/">DALT.PHP</a>
      <div class="status" aria-label="DALT is running">
        <span class="status-dot" aria-hidden="true"></span>
        DALT is up
      </div>
    </header>

    <main class="content">
      <section aria-labelledby="welcome-title">
        <?php if ($platformInstalled): ?>
          <h1 id="welcome-title">Your framework is ready.</h1>
          <p class="intro">Learn DALT itself, follow the dedicated fullstack course, or build a complete workspace and issue-management application from the ground up.</p>
        <?php else: ?>
          <h1 id="welcome-title">Your clean framework is ready.</h1>
          <p class="intro">The learning platform has been removed. Replace this welcome view with the first screen of your application.</p>
        <?php endif; ?>
      </section>

      <?php if ($platformInstalled): ?>
        <nav class="paths" aria-label="Learning paths">
          <a class="path" href="/learn">
            <span><strong>Learn the framework</strong><span>Understand routing, requests, sessions, validation, databases, and the DALT core.</span></span>
            <svg aria-hidden="true" viewBox="0 0 20 20" fill="none"><path d="M4 10h11m-4-4 4 4-4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>
          <a class="path" href="/learn/fullstack">
            <span><strong>Take the fullstack course</strong><span>React, TypeScript, Tailwind CSS, DALT/PHP, PostgreSQL, and Docker—taught in focused lessons.</span></span>
            <svg aria-hidden="true" viewBox="0 0 20 20" fill="none"><path d="M4 10h11m-4-4 4 4-4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>
          <a class="path" href="/learn/build">
            <span><strong>Learn by building</strong><span>Build a serious workspace and issue-management system, one production step at a time.</span></span>
            <svg aria-hidden="true" viewBox="0 0 20 20" fill="none"><path d="M4 10h11m-4-4 4 4-4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>
        </nav>
      <?php else: ?>
        <nav class="paths" aria-label="Framework links">
          <a class="path" href="https://github.com/Ibnu-Afdel/DALT.PHP" target="_blank" rel="noopener noreferrer">
            <span><strong>Read the framework source</strong><span>Explore DALT's public API, examples, and release notes on GitHub.</span></span>
            <svg aria-hidden="true" viewBox="0 0 20 20" fill="none"><path d="M4 10h11m-4-4 4 4-4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>
        </nav>
      <?php endif; ?>
    </main>

    <?php if ($platformInstalled): ?>
      <aside class="remove" aria-label="Remove guided learning">
        <p>Ready to build without the learning layer? This removes <strong>.dalt</strong> and leaves the framework core.</p>
        <code>php artisan platform:remove</code>
      </aside>
    <?php endif; ?>

    <footer class="foot">
      <span>Framework by <a href="https://github.com/Ibnu-Afdel" target="_blank" rel="noopener noreferrer">ibnu-afdel</a></span>
      <nav class="community" aria-label="Project and community links">
        <a class="star" href="https://github.com/Ibnu-Afdel/DALT.PHP" target="_blank" rel="noopener noreferrer">Star the repository</a>
        <a href="https://t.me/daltphp" target="_blank" rel="noopener noreferrer">Telegram</a>
        <a href="https://discord.gg/x7ajNAeY6" target="_blank" rel="noopener noreferrer">Discord</a>
      </nav>
    </footer>
  </div>
</body>
</html>
