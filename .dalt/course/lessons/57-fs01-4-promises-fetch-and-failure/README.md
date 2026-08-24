# FS01.4 — Promises, fetch, and failure boundaries

Lesson ID: FS01.4
Lesson format: Concise theory
Part: 01 — Modern JavaScript
Status: Published
Estimated effort: 30–40 minutes
Difficulty: Foundation
Prerequisites: FS01.3
Last reviewed: 2026-08-22

## What we will learn

Some JavaScript operations finish later. A Promise represents that future result;
`async`/`await` lets us follow it in order, while careful checks keep network, HTTP,
body-parsing, and application failures distinct.

By the end, we can:

- describe pending, fulfilled, and rejected Promises;
- read an `async` function that awaits `fetch()` and JSON parsing;
- identify which boundary failed instead of treating every failure as “fetch broke.”

### A Promise is a value for a later result

`fetch()` returns immediately with a Promise, not with response data:

```js
const responsePromise = fetch('/learn/fullstack/observe/async/issue-preview')
console.log(responsePromise)
```

A Promise begins **pending**. It later becomes **fulfilled** with a value or
**rejected** with a reason. We can register callbacks with `.then()` and `.catch()`:

```js
fetch('/learn/fullstack/observe/async/issue-preview')
  .then((response) => response.json())
  .then((data) => console.log(data.issue.title))
  .catch((error) => console.error(error))
```

Each `.then()` returns another Promise. Returning `response.json()` is important: the
next step must wait for that parsing Promise rather than run with no data.

### `async` and `await` expose the same sequence

An `async` function always returns a Promise. Inside it, `await` pauses that function's
dependent steps until a Promise settles:

```js
const loadIssuePreview = async (path) => {
  const response = await fetch(path)
  const data = await response.json()
  return data.issue
}
```

Calling it still produces a Promise:

```js
const previewPromise = loadIssuePreview(
  '/learn/fullstack/observe/async/issue-preview',
)
```

To receive the issue, another async context must await it or attach `.then()`. `await`
does not block the whole browser; other events and work can continue while this
function waits.

### A Response is not yet the body data

`await fetch(path)` gives us a `Response` object with status, headers, and methods for
reading its body. JSON parsing is a separate asynchronous step:

```js
const response = await fetch(path)
console.log(response.status, response.ok)

const data = await response.json()
```

A body can usually be consumed once. Choose the representation the endpoint promises:
`json()` for JSON, `text()` for text, and other methods for other body types.

### HTTP errors do not normally reject `fetch`

This is the essential boundary: a server response such as 404 means the network
exchange succeeded. `fetch()` normally fulfills with that Response, so our code must
check the HTTP status:

```js
const loadIssuePreview = async (path) => {
  const response = await fetch(path)

  if (!response.ok) {
    throw new Error(`Issue request failed with ${response.status}`)
  }

  const data = await response.json()
  return data.issue
}
```

`response.ok` is true for statuses from 200 through 299. Throwing turns our rejected
application rule into a rejected Promise that the caller can handle.

### Handle failure where we can act on it

The caller can show or log a useful outcome:

```js
const showIssuePreview = async (path) => {
  try {
    const issue = await loadIssuePreview(path)
    console.log(issue.title)
  } catch (error) {
    console.error('Could not load issue preview:', error)
  }
}
```

One `catch` can receive failures from different stages, so inspect the evidence:

```text
network failure   → fetch Promise rejects; no usable Response
HTTP failure      → Response arrives with ok false
body failure      → Response arrives, but json() rejects
application error → parsed data violates a rule we define
```

Do not catch merely to hide an error and return `undefined`. Catch where we can add
context, present a state, retry, or otherwise make a decision; otherwise let the
rejection continue to a caller that can.

## Try it

**Workspace:** No workspace copy is needed. Use the Console and Network panel on a
DALT page.

Paste this loader:

```js
const inspectIssuePreview = async (path) => {
  const response = await fetch(path)
  console.log('response', response.status, response.ok)

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`)
  }

  const data = await response.json()
  console.log('data', data)
  return data
}
```

Run these separately, using `await` in the Console:

```js
await inspectIssuePreview('/learn/fullstack/observe/async/issue-preview')
await inspectIssuePreview('/learn/fullstack/observe/async/missing-issue')
await inspectIssuePreview('/learn/fullstack/observe/async/invalid-json')
```

**Expected result:** success logs status 200 and parsed issue data. `missing-issue`
receives a Response with status 404 and throws our `HTTP 404` error. `invalid-json`
receives an OK 200 Response, then rejects during `response.json()` with a parsing
error. Network shows that all three servers responded.

**Reset:** clear the Console and Network log. The fixture is read-only and stores
nothing.

<details>
<summary>If `await` is rejected at the Console prompt</summary>

Wrap the call in an async function:

```js
inspectIssuePreview('/learn/fullstack/observe/async/issue-preview')
  .catch(console.error)
```
</details>

## What to notice

Both the 404 and invalid JSON reach an error handler, but for different reasons. A
single red Console line cannot identify the boundary; status, `ok`, response headers,
Network evidence, and the error message together can.

Also remember that `async` changes a function's return contract. Even `return 17`
inside an async function fulfills a Promise with 17; it does not return 17 directly.

## Check your understanding

1. What three states can a Promise have?
2. What does an async function always return?
3. Why are `await fetch()` and `await response.json()` separate steps?
4. Why does a 404 normally not reject `fetch()`?
5. What evidence distinguishes an HTTP failure from a JSON parsing failure?

## Next

We now have the JavaScript model needed for application data and asynchronous work.
Build B01 checks that readiness; Part 02 will add TypeScript's static evidence without
changing JavaScript's runtime behavior.

<details>
<summary>Maintainer source record</summary>

- Source dossier: Full Stack Open Part 1 research notes; async reconciliation in Full Stack Open Part 2 research notes
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: MDN Using promises; MDN async function; MDN Using Fetch; MDN Response
- Versions: ECMAScript and Fetch APIs documented by MDN current on 2026-08-22
- Consulted: 2026-08-22
- Curriculum authority: DALT Fullstack theory curriculum Batch 2, FS01.4
- DALT files inspected: `.dalt/Http/controllers/learn/fullstack-observation.php`; `.dalt/routes/routes.php`; former FS01.2 lesson
- Reused material: Promise states, async/await sequence, response parsing, and four failure-boundary model split from the former combined FS01.2
</details>
