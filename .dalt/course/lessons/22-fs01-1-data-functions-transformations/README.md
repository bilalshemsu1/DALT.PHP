# FS01.1 — Values, references, and immutable updates

Lesson ID: FS01.1
Lesson format: Concise theory
Part: 01 — Modern JavaScript
Status: Published
Estimated effort: 25–35 minutes
Difficulty: Foundation
Prerequisites: FS00.4
Last reviewed: 2026-08-22

## What we will learn

JavaScript variables can hold primitive values or references to objects and arrays.
That distinction explains why a “copy” sometimes changes the original—and how to
build predictable next values without accidental mutation.

By the end, we can:

- choose `const` and `let` based on whether a binding must be reassigned;
- predict when two bindings reach the same object;
- update each changed level of nested data without mutating the original.

### A binding is a name for a value

We normally begin with `const`:

```js
const title = 'Broken search'
const issue = { id: 17, title: 'Broken search', status: 'open' }
```

`const` means the binding cannot be assigned a different value later:

```js
title = 'Slow export' // TypeError: Assignment to constant variable
```

It does not freeze an object held by that binding:

```js
issue.status = 'closed' // allowed, although it mutates the object
```

Use `let` when reassignment is genuinely part of the algorithm:

```js
let openCount = 0
openCount = openCount + 1
```

This makes `const` versus `let` a question about the binding, not whether the value is
morally “constant.” Avoid `var` in our modern code; its function-scoped behavior adds
rules we do not need.

### Primitives copy as values; objects share references

Strings, numbers, booleans, `null`, and `undefined` are primitive values. Assigning a
primitive copies that value:

```js
let firstStatus = 'open'
let secondStatus = firstStatus
secondStatus = 'closed'

console.log(firstStatus) // open
```

Objects and arrays behave differently. A variable holds a reference that reaches the
object. Assigning it copies the reference, not the object:

```js
const original = { id: 17, status: 'open' }
const alias = original

alias.status = 'closed'

console.log(original.status) // closed
console.log(original === alias) // true
```

There is one object with two ways to reach it. The mutation is visible through both.
Arrays are objects too, so the same rule applies to them.

### Spread creates a shallow copy

Object spread creates a new outer object and copies its enumerable properties:

```js
const original = { id: 17, status: 'open' }
const closed = { ...original, status: 'closed' }

console.log(original.status) // open
console.log(original === closed) // false
```

Property order matters: the later `status` overrides the copied one. Array spread can
create a new outer array:

```js
const issues = [{ id: 17 }, { id: 18 }]
const nextIssues = [...issues]

console.log(issues === nextIssues) // false
console.log(issues[0] === nextIssues[0]) // true
```

This is a **shallow copy**. The container is new, but nested objects keep their old
references. That is useful when unchanged branches should retain identity, but it
means we must copy every branch we intend to change.

### Copy the changed path

Consider an issue with a nested assignee:

```js
const issue = {
  id: 17,
  status: 'open',
  assignee: { id: 4, name: 'Mina' },
}
```

This only copies the issue; `assignee` remains shared:

```js
const unsafe = { ...issue }
unsafe.assignee.name = 'Noah'

console.log(issue.assignee.name) // Noah — original changed
```

Instead, copy the changed object and each parent on the path to it:

```js
const updated = {
  ...issue,
  assignee: {
    ...issue.assignee,
    name: 'Noah',
  },
}
```

Now the original issue and assignee remain untouched. We are not making JavaScript
immutable; we are choosing an update that returns a separate next value.

## Try it

**Workspace:** No workspace copy is needed. Open the browser Console on any DALT page.

Paste this experiment, but predict the four booleans first:

```js
const issue = {
  id: 17,
  status: 'open',
  assignee: { name: 'Mina' },
}

const next = {
  ...issue,
  status: 'closed',
  assignee: { ...issue.assignee, name: 'Noah' },
}

console.log(issue === next)
console.log(issue.assignee === next.assignee)
console.log(issue.status === 'open')
console.log(issue.assignee.name === 'Mina')
```

Then remove the nested spread by changing the `assignee` line to
`assignee: issue.assignee`, mutate `next.assignee.name`, and inspect the original.

**Expected result:** the safe version prints four `false, false, true, true` identity
and preservation results in that order. Sharing `issue.assignee` makes a later nested
mutation visible through the original issue.

**Reset:** reload the page or clear the Console. These values exist only in the current
page's JavaScript memory.

<details>
<summary>Why not deep-clone everything?</summary>

A full deep clone loses useful identity for unchanged branches, may not support every
JavaScript value, and can hide which part actually changed. Copy the changed path;
reach for `structuredClone()` only when a genuine full-copy requirement exists.
</details>

## What to notice

Equality with `===` compares object identity, not matching contents. Two separate
objects can contain the same properties and still compare false; two bindings can
compare true because both reach the same object.

The common bug is copying only the outer array or object and then mutating a nested
reference. Spread is useful precisely because it is shallow—provided we know which
levels our update must replace.

## Check your understanding

1. What does `const` prevent, and what does it still allow?
2. Why does assigning an object to another variable not copy the object?
3. What does “shallow” mean for `{ ...issue }`?
4. Which objects must be copied to change `issue.assignee.name` safely?
5. Why might preserving unchanged object identities be useful?

## Next

Next we will pass functions as values and use array methods to transform issue data
into useful new values without changing the source array.

<details>
<summary>Maintainer source record</summary>

- Source dossier: `docs/dalt-fullstack/sources/FSO_PART_01.md`
- Official sources: MDN Grammar and types; MDN Working with objects; MDN Spread syntax; MDN Shallow copy
- Versions: ECMAScript and MDN documentation current on 2026-08-22
- Consulted: 2026-08-22
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md` Batch 2, FS01.1
- DALT files inspected: former FS01.1 lesson and current Part 01 manifest/tests
- Reused material: bindings, reference identity, shallow spread, and nested immutable-update explanations from the former combined FS01.1
</details>
