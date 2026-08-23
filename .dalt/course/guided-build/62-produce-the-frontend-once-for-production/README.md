# Produce the frontend once for production

Our development loop rebuilds the frontend constantly and nobody minds. A deployment is
the opposite: the frontend is built **once**, and whatever that build produced is what
every visitor downloads until the next release. We will make that single build
trustworthy — gated by everything that guards it, and checked afterwards for the
mistakes that a green `vite build` does not notice.

> **Helpful background:** Vite's [build guide](https://vite.dev/guide/build.html)
> explains the manifest and content hashing this lesson relies on.

## What our build already does

Look at the `build` block in `vite.config.mjs`. Two settings there are doing the work:

```js
build: {
  manifest: true,
  outDir: resolve(__dirname, 'public/build'),
  emptyOutDir: true,
  rollupOptions: {
    input: resolve(__dirname, 'resources/app/main.tsx')
  }
}
```

`manifest: true` writes `public/build/.vite/manifest.json`, which maps our source entry
to the hashed files Rollup actually emitted. And DALT's `vite()` helper already prefers
that manifest over the development server:

```php
// If pre-compiled manifest exists, ALWAYS use it.
// This avoids port conflicts and 200ms timeouts when the dev server is offline.
if ($manifestPath) {
    $manifest = json_decode(file_get_contents($manifestPath), true);
```

So the serving half is solved. What is not solved is everything around it: whether the
artifact should have been produced at all, and whether the one on disk is the one we
think it is.

## Build only what has already passed

`npm run build` will happily emit a bundle from code that does not typecheck against
our browser tests, fails lint, and breaks two component tests. Vite is a bundler, not a
gate.

Add one command to `package.json` that puts the gate in front of the artifact:

```json
"build:production": "npm run typecheck && npm run typecheck:browser && npm run lint && npm run test && npm run build && php scripts/verify-build.php"
```

`&&` rather than a script runner: each step must stop the chain. A build that runs
after a failed test is a build nobody should deploy, and producing it anyway makes the
failure easy to ignore.

```bash
npm run build:production
```

```text
Tests  42 passed (42)
✓ built in 518ms
Build artifact is complete and current (2 emitted files).
```

## Check the artifact, not the exit code

That last line comes from a script we still need. `vite build` exiting 0 means the
bundler was happy; it says nothing about whether the output is complete, current, or
free of development leftovers.

Create `scripts/verify-build.php`. First, the manifest has to describe a real entry:

```php
if (!isset($manifest[$entry])) {
    $problems[] = "The manifest has no entry for {$entry}; the built page would load nothing.";
}
```

Then every file it promises must exist:

```php
foreach ($manifest as $name => $chunk) {
    foreach ([...(array) ($chunk['css'] ?? []), $chunk['file'] ?? null] as $file) {
        if (!is_string($file) || $file === '') {
            continue;
        }
        $emitted[] = $file;
        if (!is_file($buildDir . '/' . $file)) {
            $problems[] = "The manifest names {$file} for {$name}, but that file is missing.";
        }
    }
}
```

This is not paranoia. A deployment that copies `public/build` file by file, or a
`.gitignore` that excludes one extension, produces exactly this: a manifest pointing at
a stylesheet nobody shipped, and a page that renders unstyled.

Every emitted name must carry a content hash:

```php
// Hashed names are what make a one-year cache header safe: a changed file is a
// different URL. An unhashed name would be served stale after every deployment.
foreach ($emitted as $file) {
    if (preg_match('/-[A-Za-z0-9_-]{8,}\.(js|css)$/', basename($file)) !== 1) {
        $problems[] = "{$file} has no content hash in its name, so it cannot be cached safely.";
    }
}
```

This is worth understanding rather than copying. `main-B0_nQ7gI.js` can be cached
forever, because changing the file changes its name and therefore its URL. `main.js`
cannot: a browser that cached it yesterday will keep yesterday's application. Content
hashing is what turns aggressive caching from a risk into the correct default — which
is why Lesson 63 can hand these files a one-year cache header without worrying.

## Refuse the leftovers

Two things must not reach a production bundle:

```php
// Source maps expose our original source. Ship them only on purpose.
if (str_ends_with($relative, '.map')) {
    $problems[] = "{$relative} is a source map; production builds should not ship one unless we decided to.";
}

// A bundle that still points at the Vite dev server only works on a laptop.
if (str_contains($contents, 'localhost:5173') || str_contains($contents, '/@vite/client')) {
    $problems[] = "{$relative} still refers to the Vite development server.";
}
```

Neither is currently true of our build, and that is the point: the check exists so that
a future configuration change cannot make it true silently.

## Catch the stale artifact

The failure that actually happens in practice is not a broken build. It is a build that
was correct last Tuesday:

```php
if ($newestSource > filemtime($manifestPath)) {
    $problems[] = 'A source file is newer than the build manifest; this artifact is stale.';
}
```

Try it:

```bash
touch resources/app/main.tsx
php scripts/verify-build.php
```

```text
This build artifact should not be deployed:
- A source file is newer than the build manifest; this artifact is stale.
```

Rebuild and it is quiet again. This is a modification-time heuristic, not a proof — it
would miss a change restored from an old backup — but it catches the deployment that
shipped without running the build, which is the case that costs an afternoon.

## Prove every rule fires

Break each one deliberately and put it back:

```bash
mv public/build/assets/main-*.css /tmp/  &&  php scripts/verify-build.php
```

```text
- The manifest names assets/main-Dxok4Xq5.css for resources/app/main.tsx, but that file is missing.
```

```bash
touch public/build/assets/main.js.map  &&  php scripts/verify-build.php
```

```text
- assets/main.js.map is a source map; production builds should not ship one unless we decided to.
```

```bash
echo '// import "http://localhost:5173/@vite/client";' >> public/build/assets/main-*.js
php scripts/verify-build.php
```

```text
- assets/main-B0_nQ7gI.js still refers to the Vite development server.
```

Four rules, four failures, each naming the file and the reason.

## Prove the built application needs nothing else

The claim we most want to be true is that a visitor needs only DALT and the files in
`public/build` — no Vite process anywhere.

We can prove that without writing a new test, because Lesson 60 already set it up. Look
at the `webServer` command in `playwright.config.ts`:

```ts
command: `DB_NAME=dalt_issue_tracker_test php artisan serve 127.0.0.1 ${port}`,
```

One PHP server, and nothing else. Confirm the development server really is absent, then
run the journeys:

```bash
(exec 3<>/dev/tcp/127.0.0.1/5173) 2>/dev/null && echo "5173 open" || echo "5173 closed"
npm run test:browser
```

```text
5173 closed — no Vite dev server
7 passed (16.0s)
```

And the page served in that state loads the hashed files by name:

```html
<link rel="stylesheet" href="/build/assets/main-Dxok4Xq5.css">
<script type="module" src="/build/assets/main-B0_nQ7gI.js"></script>
```

Seven journeys — including a deep-link refresh — driving a real browser against a built
bundle with no bundler running. That is the artifact a container will carry.

## What this sets up for the container

Keep one distinction in mind for the next lesson. Building the frontend needs Node,
npm, our `devDependencies`, and our TypeScript source. **Serving** it needs none of
them — only the files in `public/build`.

```text
build time    node, npm, node_modules, resources/app/**   → produces public/build
run time      PHP, our application code, public/build     → serves requests
```

That gap is exactly what a multi-stage image is for, and it is why Lesson 63 can copy
`public/build` out of a Node stage and leave 300MB of tooling behind.

## Run the gate

```bash
npm run build:production
composer check:config
composer check:secrets
npm run test:browser
```

The production build runs both type checks, lint, and all 42 component tests before
emitting anything, the artifact verifies as complete and current, the configuration and
secret checks pass, and the seven browser journeys pass against the built bundle.

Our frontend is now produced by one command that cannot skip its own gate, and the
result is checked rather than assumed. Next we put the whole application — PHP,
this artifact, and nothing it does not need — into a container.
