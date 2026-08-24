# FS01.2 — Functions, arrays, and data transformations

Lesson ID: FS01.2
Lesson format: Concise theory
Part: 01 — Modern JavaScript
Status: Published
Estimated effort: 30–40 minutes
Difficulty: Foundation
Prerequisites: FS01.1
Last reviewed: 2026-08-22

## What we will learn

JavaScript functions are values: we can store them, pass them to array methods, and
return them from other functions. That gives us a compact way to turn raw issue data
into exactly the result an interface needs.

By the end, we can:

- read function declarations, arrow functions, callbacks, and closures;
- choose `map`, `filter`, `find`, `some`, `every`, or `reduce` by intent;
- build transformations that return new values instead of mutating their input.

### Functions receive values and return values

A function declaration names a reusable operation:

```js
function isOpen(issue) {
  return issue.status === 'open'
}
```

An arrow function is another way to create a function value:

```js
const isOpen = (issue) => issue.status === 'open'
```

With one expression, the arrow returns that expression automatically. Braces create
a block, so an explicit `return` is then required:

```js
const issueLabel = (issue) => {
  const prefix = issue.status === 'open' ? 'OPEN' : 'CLOSED'
  return `${prefix}: ${issue.title}`
}
```

Parameters are the names receiving input; the returned value is the output. A
function with no `return` produces `undefined`.

### A callback is a function passed for later use

Because a function is a value, we can pass it to another function:

```js
const issues = [
  { id: 17, title: 'Broken search', status: 'open' },
  { id: 18, title: 'Refresh docs', status: 'closed' },
]

const openIssues = issues.filter(isOpen)
```

`filter` receives `isOpen` as a **callback** and calls it once for each item. We pass
the function itself as `isOpen`, not the result of calling `isOpen()` immediately.

Inline callbacks are common when the rule is short:

```js
const titles = issues.map((issue) => issue.title)
```

The outer method decides when and with which value the callback runs.

### Closures remember surrounding values

A function can use values from the scope where it was created:

```js
const hasStatus = (wantedStatus) => {
  return (issue) => issue.status === wantedStatus
}

const isClosed = hasStatus('closed')
const closedIssues = issues.filter(isClosed)
```

The returned function remembers `wantedStatus` even after `hasStatus` has finished.
That remembered surrounding scope is a **closure**. We will later use the same idea in
event handlers and reusable application functions.

### Choose the method that names the result

Each common array method answers a different question:

```js
const open = issues.filter((issue) => issue.status === 'open')
const titles = issues.map((issue) => issue.title)
const issue = issues.find((issue) => issue.id === 18)
const hasUrgent = issues.some((issue) => issue.priority === 'high')
const allAssigned = issues.every((issue) => issue.assigneeId !== null)
```

- `filter` returns every matching element in a new array.
- `map` returns one transformed element for every input element.
- `find` returns the first match, or `undefined`.
- `some` asks whether at least one element matches.
- `every` asks whether all elements match.

Use `reduce` when we genuinely need one accumulated result:

```js
const counts = issues.reduce(
  (result, issue) => ({
    ...result,
    [issue.status]: (result[issue.status] ?? 0) + 1,
  }),
  {},
)
```

The second argument `{}` is the initial accumulator. Each callback returns the value
the next iteration will receive. A loop is often clearer than a clever `reduce`; use
the method because it expresses the result, not because it is advanced.

### Destructuring makes inputs explicit

Destructuring extracts named values:

```js
const summarizeIssue = ({ id, title, status }) => ({
  id,
  label: `${status.toUpperCase()}: ${title}`,
})
```

The return value uses object-property shorthand: `{ id }` means `{ id: id }`. Rest
collects the remaining values, while spread expands values into a new container:

```js
const { assignee, ...issueWithoutAssignee } = issue
const withLabel = { ...issueWithoutAssignee, label: 'frontend' }
```

## Try it

**Workspace:** No workspace copy is needed. Open the browser Console on a DALT page.

Paste this dataset:

```js
const issues = [
  { id: 17, title: 'Broken search', status: 'open', priority: 'high' },
  { id: 18, title: 'Refresh docs', status: 'closed', priority: 'low' },
  { id: 19, title: 'Slow export', status: 'open', priority: 'medium' },
]
```

Then write and run expressions that:

1. filter open issues;
2. map them to titles;
3. find issue 18;
4. ask whether any issue has high priority;
5. count issues by status;
6. return a new array where issue 19 is closed, without changing `issues`.

**Expected result:** the derived values contain two open issues, their two titles,
issue 18, `true`, counts of `{ open: 2, closed: 1 }`, and an updated array. The
original issue 19 still has status `open`.

**Reset:** reload the page or clear the Console. The dataset is disposable.

<details>
<summary>One possible immutable status update</summary>

```js
const nextIssues = issues.map((issue) =>
  issue.id === 19 ? { ...issue, status: 'closed' } : issue,
)
```
</details>

## What to notice

These methods do not change the source array, but a callback can still mutate objects
inside it. “Used `map`” is not proof of immutability; the callback must return the
appropriate old or copied value.

Also watch return values. Braced arrow functions need `return`, `find` may produce
`undefined`, and a reducer must return its next accumulator on every iteration.

## Check your understanding

1. What is the difference between passing `isOpen` and calling `isOpen()`?
2. What value does a function without `return` produce?
3. Which method fits one match? Every match? A boolean question?
4. What does a closure remember?
5. Why can a `map` callback still cause mutation?

## Next

Next we will split code into browser modules and use Developer Tools to see exactly
which file loaded, which export crossed the boundary, and where an error began.

<details>
<summary>Maintainer source record</summary>

- Source dossier: Full Stack Open Part 1 research notes
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: MDN Functions; MDN Closures; MDN Array iterative methods; MDN Destructuring assignment
- Versions: ECMAScript and MDN documentation current on 2026-08-22
- Consulted: 2026-08-22
- Curriculum authority: DALT Fullstack theory curriculum Batch 2, FS01.2
- DALT files inspected: former FS01.1 lesson and current Part 01 learning-flow tests
- Reused material: functions-as-values, callbacks, closures, destructuring, array-method selection, and transformation examples split from the former FS01.1
</details>
