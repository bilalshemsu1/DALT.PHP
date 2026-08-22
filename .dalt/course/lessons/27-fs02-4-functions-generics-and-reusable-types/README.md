# FS02.5 — Typed functions, generics, and reusable relationships

Lesson ID: FS02.5
Lesson format: Concise theory
Part: 02 — TypeScript foundations
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Foundation
Prerequisites: FS02.4
Last reviewed: 2026-08-22

We will type function contracts and use generics only when one real relationship must survive across different input types.

> **Helpful background:** [Unions, narrowing, and unknown](/learn/lessons/26-fs02-3-unions-narrowing-and-unknown)

## What we will learn

By the end, we can:

- state function parameter, return, and callback contracts;
- recognize when a generic preserves useful information;
- derive focused application types without duplicating the source model.

## A function contract has two directions

Parameters constrain what callers may supply. The return type constrains what every branch must produce:

```ts
type Issue = { id: number; title: string };

function findIssue(issues: Issue[], id: number): Issue | undefined {
  return issues.find((issue) => issue.id === id);
}
```

The `undefined` is part of the contract. Callers must handle “not found” honestly. Returning `'not found'` would violate the promise and produce a diagnostic.

Local return types can often be inferred, but an explicit return type is useful on exported functions because it prevents an implementation change from silently changing the public contract.

## Functions are typed values

A callback has its own input and output relationship:

```ts
type IssuePredicate = (issue: Issue) => boolean;

function filterIssues(
  issues: Issue[],
  predicate: IssuePredicate,
): Issue[] {
  return issues.filter(predicate);
}
```

The callback receives an `Issue` and must return a boolean:

```ts
const urgent = filterIssues(
  issues,
  (issue) => issue.priority === 'high',
);
```

Contextual typing supplies `issue`. A callback that expects a number or returns a title does not satisfy this contract.

## A generic preserves a relationship

This concrete function works only for issues:

```ts
function firstIssue(items: Issue[]): Issue | undefined {
  return items[0];
}
```

Its real behavior does not depend on issue fields. It preserves the array's element type:

```ts
function first<T>(items: T[]): T | undefined {
  return items[0];
}

const issue = first(issues);       // Issue | undefined
const project = first(projects);   // Project | undefined
```

`T` is a type parameter. The useful fact is not merely “this accepts anything”; it is “the result keeps the input element type.” Prefer concrete functions until repeated behavior reveals a relationship worth preserving.

## Constraints require a capability

A generic type parameter initially promises no properties. State the smallest capability the implementation needs:

```ts
function findById<T extends { id: number }>(
  items: T[],
  id: number,
): T | undefined {
  return items.find((item) => item.id === id);
}
```

The constraint permits access to `id` while preserving the caller's complete type. `Issue[]` produces `Issue | undefined`; `Project[]` produces `Project | undefined`.

## Derive types from one source model

An application needs related views of one domain type:

```ts
type Issue = {
  readonly id: number;
  title: string;
  description: string | null;
  status: IssueStatus;
  priority: IssuePriority;
  createdAt: string;
};

type IssueSummary = Pick<Issue, 'id' | 'title' | 'status'>;
type NewIssue = Omit<Issue, 'id' | 'createdAt'>;
type IssuePatch = Partial<
  Pick<Issue, 'title' | 'description' | 'status' | 'priority'>
>;
```

`Pick` selects fields, `Omit` removes fields, and `Partial` makes selected fields optional. These are type transformations; no JavaScript object changes.

A patch should be a partial set of editable fields, not `Partial<Issue>`, which would accidentally permit server-owned fields such as `id`.

Indexed access keeps one field type tied to its source:

```ts
type Status = Issue['status'];
```

`Record` can prove that every finite key has a value:

```ts
const statusLabels: Record<IssueStatus, string> = {
  backlog: 'Backlog',
  todo: 'To do',
  in_progress: 'In progress',
  done: 'Done',
};
```

If `IssueStatus` gains `'blocked'`, this map becomes incomplete and the checker exposes the dependent decision.

## Try it

**Workspace:** `.dalt/workspace/fs02-5-functions`

**Starting state:** copy the course-owned function lab.

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/typescript-functions-lab/starter .dalt/workspace/fs02-5-functions
cd .dalt/workspace/fs02-5-functions
npm ci
```

Predict which relationship each stage breaks, then run:

```bash
npm run stage:return-contract
npm run stage:callbacks
npm run stage:constraint
npm run stage:utility-model-change
```

All four intentionally fail. They expose an invalid return, incompatible callbacks, unconstrained property access, and a `Record` missing a newly added status.

Now run the coherent implementation:

```bash
npm run typecheck
npm run run
```

Both succeed. In `src/functions.ts`, find one concrete function, one callback contract, one generic, one constrained generic, and one derived application type.

**Expected result:** each focused stage fails at its broken relationship; the completed functions preserve their caller types and execute successfully.

**Reset:** delete `.dalt/workspace/fs02-5-functions`.

## What to notice

Generics do not make a function automatically better. `first<T>` and `findById<T>` earn them because the caller's specific type must reach the result. `findIssueById` remains concrete because its domain-specific contract is useful.

Derived types create helpful change pressure too. Adding a status or changing a source property exposes dependent utilities that no longer tell the full truth.

## Check your understanding

1. What does `Issue | undefined` require from callers?
2. How does a callback type constrain a function value?
3. What relationship does `first<T>(items: T[]): T | undefined` preserve?
4. Why does `T extends { id: number }` help `findById`?
5. Why derive a patch from only editable fields?

<details>
<summary>Check your answers</summary>

1. They must handle the not-found case.
2. It states both the parameter type received and the result returned.
3. The array's element type becomes the result's value type.
4. It proves every `T` has the capability the implementation reads while retaining the full caller type.
5. It prevents immutable or server-owned fields from becoming editable by accident.
</details>

## Next

Next we will meet values TypeScript did not create and build a runtime parser that earns the application type it returns.

<details>
<summary>Maintainer source record</summary>

- Official sources: TypeScript Handbook, *More on Functions*, *Generics*, indexed access types, `keyof`, and utility types.
- Consulted: 2026-08-22.
- Toolchain: TypeScript 5.9.3 from the pinned functions lab.
- DALT files inspected: `.dalt/course/fullstack/typescript-functions-lab/starter/**` and executable expectations.
- Reused material: former FS02.4 function, callback, generic-constraint, and utility-type examples.
- Research dossiers: `TYPESCRIPT_HANDBOOK.md` and `FSO_TYPESCRIPT.md`.
</details>
