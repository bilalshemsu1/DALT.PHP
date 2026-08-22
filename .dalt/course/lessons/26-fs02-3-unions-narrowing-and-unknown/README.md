# FS02.4 — Unions, narrowing, and unknown

Lesson ID: FS02.4
Lesson format: Concise theory
Part: 02 — TypeScript foundations
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Foundation
Prerequisites: FS02.3
Last reviewed: 2026-08-22

We will use runtime evidence to reduce several possible types to the one possibility a code path can safely handle.

> **Helpful background:** [Modeling application data](/learn/lessons/25-fs02-2-modeling-application-data)

## What we will learn

By the end, we can:

- narrow unions with ordinary JavaScript checks;
- choose `unknown` instead of casually disabling checking with `any`;
- model mutually exclusive states as a discriminated union.

## A union preserves every possibility

An issue identifier may arrive as a number or a numeric string:

```ts
function normalizeIssueId(value: string | number): number {
  return Number(value);
}
```

Before narrowing, TypeScript allows only operations safe for both `string` and `number`. We cannot call `toUpperCase`, because a number does not have it.

Ordinary JavaScript evidence changes what the checker knows:

```ts
function normalizeIssueId(value: string | number): number {
  if (typeof value === 'number') {
    return value;
  }

  return Number(value);
}
```

Inside the first branch, the runtime `typeof` check proves `number`. On the remaining path, TypeScript knows the number possibility returned, so `value` is `string`. This is **control-flow narrowing**.

Useful evidence includes `typeof`, equality comparisons, `instanceof`, the `in` operator for object properties, and explicit null checks. The checks are real JavaScript; narrowing is TypeScript's conclusion from them.

## Truthiness can discard valid values

This code treats zero as absent:

```ts
function countLabel(count: number | undefined): string {
  if (count) return `Count: ${count}`;
  return 'No count';
}
```

`0`, `''`, `false`, `null`, and `undefined` are all falsy. When zero is meaningful, test the condition we intend:

```ts
if (count !== undefined) {
  return `Count: ${count}`;
}
```

Narrowing should encode evidence, not merely shorten a condition.

## Unknown requires proof

Both `any` and `unknown` can represent a value whose type we do not yet know, but they create opposite behavior:

```ts
declare const unchecked: any;
declare const uncertain: unknown;

unchecked.toUpperCase(); // allowed; checking stops here
uncertain.toUpperCase(); // rejected; evidence is required
```

`any` opts this path out of useful checking. `unknown` preserves uncertainty honestly. We can use the value after narrowing it:

```ts
function describe(value: unknown): string {
  if (typeof value === 'string') return value.toUpperCase();
  if (typeof value === 'number') return String(value);
  if (value === null) return 'no value';
  return 'unrecognized';
}
```

Use `unknown` where any JavaScript value is possible. Do not assert it into the type we hope for; prove the properties the next operation needs.

## A guard names reusable evidence

For an object, first prove that the value is a non-null object, then prove each required property:

```ts
type UserSummary = { id: number; name: string };

function isUserSummary(value: unknown): value is UserSummary {
  return typeof value === 'object'
    && value !== null
    && 'id' in value
    && 'name' in value
    && typeof value.id === 'number'
    && typeof value.name === 'string';
}
```

The return type `value is UserSummary` is a **type predicate**. It promises that `true` means the full shape was checked. TypeScript trusts that promise, so the implementation must prove everything it claims.

## Model states that cannot contradict themselves

Separate booleans such as `loading`, `hasError`, and `hasData` allow impossible combinations. A discriminated union ties each state to the fields it owns:

```ts
type LoadState =
  | { status: 'idle' }
  | { status: 'loading' }
  | { status: 'success'; issues: IssueSummary[] }
  | { status: 'error'; message: string };
```

Checking the shared literal field narrows the whole object:

```ts
function describeState(state: LoadState): string {
  switch (state.status) {
    case 'idle': return 'Waiting.';
    case 'loading': return 'Loading.';
    case 'success': return `${state.issues.length} issues`;
    case 'error': return state.message;
    default: {
      const exhaustive: never = state;
      return exhaustive;
    }
  }
}
```

`never` represents a possibility that should not exist. If we add `'refreshing'` but forget this switch, the default assignment fails and exposes the missing branch.

## Try it

**Workspace:** `.dalt/workspace/fs02-4-narrowing`

**Starting state:** copy the course-owned narrowing lab.

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/typescript-narrowing-lab/starter .dalt/workspace/fs02-4-narrowing
cd .dalt/workspace/fs02-4-narrowing
npm ci
```

Predict, run, and read each result:

```bash
npm run stage:union
npm run stage:unknown
npm run stage:truthiness
npm run stage:guard
npm run stage:exhaustive
```

The union and unknown stages intentionally fail because they use a value without enough evidence. The truthiness stage succeeds and prints `No count`, exposing the logic bug for zero. The guard succeeds and prints `true` then `false`. The exhaustive stage intentionally fails because a new `refreshing` variant is not handled.

Finally run the finished examples:

```bash
npm run typecheck
npm run run
```

Both succeed.

**Expected result:** diagnostics identify unsafe operations and a missing state branch; runtime output demonstrates why a type-correct truthiness condition can still be logically wrong.

**Reset:** delete `.dalt/workspace/fs02-4-narrowing`.

## What to notice

TypeScript narrows because control flow contains runtime evidence. `unknown` keeps that proof obligation visible. A discriminated union uses the same mechanism to connect a state name with exactly the data available in that state.

## Check your understanding

1. What operations are safe before narrowing `string | number`?
2. Why can `if (count)` mishandle `0`?
3. How does `unknown` differ from `any`?
4. What must a type guard implementation prove?
5. How does assigning an unhandled value to `never` help future changes?

<details>
<summary>Check your answers</summary>

1. Only operations valid for every remaining member, unless runtime evidence narrows it first.
2. Zero is falsy even when it is a present, valid count.
3. `any` disables checks; `unknown` requires narrowing before use.
4. Every property and condition claimed by its type predicate.
5. Adding a union member makes the supposedly impossible default value non-`never`, producing a diagnostic at forgotten branches.
</details>

## Next

Next we will type function inputs, outputs, callbacks, and generic relationships that preserve information across reusable code.

<details>
<summary>Maintainer source record</summary>

- Official sources: TypeScript Handbook, *Narrowing* and *Everyday Types*.
- Consulted: 2026-08-22.
- Toolchain: TypeScript 5.9.3 from the pinned narrowing lab.
- DALT files inspected: `.dalt/course/fullstack/typescript-narrowing-lab/starter/**` and its executable expectations.
- Reused material: former FS02.3 union, unknown, guard, discriminated-union, and exhaustiveness examples.
- Research dossiers: `TYPESCRIPT_HANDBOOK.md` and `FSO_TYPESCRIPT.md`.
</details>
