# FS01.3 — Modules and browser tooling

Lesson ID: FS01.3
Lesson format: Concise theory
Part: 01 — Modern JavaScript
Status: Published
Estimated effort: 25–35 minutes
Difficulty: Foundation
Prerequisites: FS01.2
Last reviewed: 2026-08-22

## What we will learn

JavaScript modules let one file expose a deliberate public surface and another file
import it. Browser and Node tooling then help us see which module loaded, which value
crossed the boundary, and where an error originated.

By the end, we can:

- create named exports and import them with a relative module specifier;
- distinguish a module's private implementation from its public API;
- use Console, Sources, and stack traces to locate a failure.

### Export only what another file needs

Suppose `issue-summary.mjs` owns issue-label formatting:

```js
const statusPrefix = (status) => status.toUpperCase()

export const summarizeIssue = ({ id, title, status }) => {
  return `#${id} ${statusPrefix(status)}: ${title}`
}
```

`summarizeIssue` is a named export. `statusPrefix` stays private to this module because
it is not exported. Another file can use the public function:

```js
import { summarizeIssue } from './issue-summary.mjs'

const issue = { id: 17, title: 'Broken search', status: 'open' }
console.log(summarizeIssue(issue))
```

The braces mean “import the named export with this exact name.” The relative specifier
starts with `./` and includes the file extension, which works directly in browsers and
Node. Bare names such as `'react'` need an environment or tool that knows how to
resolve packages; browsers do not search `node_modules` by themselves.

### Modules create explicit boundaries

Without modules, unrelated code can accumulate in one file and depend on any variable
nearby. A module makes the dependency visible:

```text
run-preview.mjs
    │ imports summarizeIssue
    ▼
issue-summary.mjs
    │ keeps statusPrefix private
    ▼
one formatted string
```

This is not mainly about smaller files. It is about responsibilities. A useful module
groups code that changes for the same reason and exports the smallest surface its
consumers require.

Named exports make refactoring and editor navigation straightforward:

```js
export const findIssue = (issues, id) =>
  issues.find((issue) => issue.id === id)

export const summarizeIssue = (issue) =>
  `#${issue.id} ${issue.title}`
```

The importer can choose either or both:

```js
import { findIssue, summarizeIssue } from './issues.mjs'
```

A module can also have one default export, but named exports are a clear default for
our application utilities because every imported name matches its definition.

### The environment must load modules as modules

In HTML, use `type="module"`:

```html
<script type="module" src="./run-preview.js"></script>
```

Module scripts are deferred automatically, have their own scope, and follow module
loading rules. Opening an HTML file directly with a `file:` URL can trigger browser
security restrictions; serve it over HTTP when working in a browser.

For a small non-DOM experiment, Node can run `.mjs` directly:

```bash
node run-preview.mjs
```

Later Vite will resolve package imports, transform TypeScript and JSX, and provide a
development server. The import/export relationship remains ordinary JavaScript.

### Read errors from the first useful frame

If an imported function throws, the Console shows a stack trace:

```text
Error: Issue title is required
    at summarizeIssue (issue-summary.mjs:3:11)
    at run-preview.mjs:4:13
```

Read from the message into the first frame belonging to our code. The Sources panel
can open that file and line. Set a breakpoint before the throw, run again, and inspect
the local values and call stack instead of adding many permanent logs.

Useful tools answer different questions:

- Console: what was logged or thrown?
- Sources/Debugger: which code ran, and with what values?
- Network: was the module requested, and did it return successfully?

## Try it

**Workspace:** create `.dalt/workspace/fs01-modules/`. It is disposable and ignored by
Git.

Create `.dalt/workspace/fs01-modules/issue-summary.mjs`:

```js
const statusPrefix = (status) => status.toUpperCase()

export const summarizeIssue = ({ id, title, status }) => {
  if (title === '') throw new Error('Issue title is required')
  return `#${id} ${statusPrefix(status)}: ${title}`
}
```

Create `.dalt/workspace/fs01-modules/run-preview.mjs`:

```js
import { summarizeIssue } from './issue-summary.mjs'

const issue = { id: 17, title: 'Broken search', status: 'open' }
console.log(summarizeIssue(issue))
```

Run:

```bash
node .dalt/workspace/fs01-modules/run-preview.mjs
```

Then change `title` to an empty string, run again, and follow the stack trace to the
throwing module. Finally, misspell the imported name and compare that loading error
with the runtime error.

**Expected result:** the first run prints `#17 OPEN: Broken search`. The empty title
exits with `Error: Issue title is required` and frames for both files. A misspelled
named import fails while the modules are being linked, before the issue code runs.

**Reset:** delete `.dalt/workspace/fs01-modules/`, or restore the two short files and
repeat the successful run.

<details>
<summary>If Node says it cannot find the module</summary>

Run the command from the repository root and confirm the filename, `.mjs` extension,
relative `./issue-summary.mjs` specifier, and letter casing all match.
</details>

## What to notice

Import errors, runtime errors, and failed HTTP loads are different boundaries. A
module that was never found cannot execute. A module with a missing named export can
be fetched but cannot link successfully. A function that throws proves loading and
linking already succeeded.

Do not make every function its own module. Draw a boundary where a responsibility has
a useful public API, then let tooling reveal how values cross it.

## Check your understanding

1. What makes `summarizeIssue` public while `statusPrefix` stays private?
2. Why does a browser import usually include `./` and a file extension?
3. What does `type="module"` change for a browser script?
4. Which tool shows whether a module request returned 404?
5. How does a missing export differ from an error thrown inside the function?

## Next

Modules organize synchronous code. Next we will follow values that arrive later with
Promises, `async`/`await`, `fetch`, and explicit failure boundaries.

<details>
<summary>Maintainer source record</summary>

- Source dossier: Full Stack Open Part 1 research notes with the Part 02 async reconciliation
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: MDN JavaScript modules; MDN browser developer tools; Node.js ECMAScript modules documentation
- Versions: ECMAScript modules, current evergreen browsers, and Node.js documentation current on 2026-08-22
- Consulted: 2026-08-22
- Curriculum authority: DALT Fullstack theory curriculum Batch 2, FS01.3
- DALT files inspected: former FS01.2 lesson, root `.gitignore`, and current Fullstack navigation tests
- Reused material: named module boundaries and diagnostic-tooling guidance split from the former combined FS01.2
</details>
