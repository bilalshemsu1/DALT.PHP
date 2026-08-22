# FS02.3 — Modeling application data

Lesson ID: FS02.3
Lesson format: Concise theory
Part: 02 — TypeScript foundations
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Foundation
Prerequisites: FS02.2
Last reviewed: 2026-08-22

We will turn product decisions into object shapes that admit valid application states and reject accidental ones.

> **Helpful background:** [Everyday types and useful inference](/learn/lessons/58-fs02-2-everyday-types-and-inference)

## What we will learn

By the end, we can:

- model required, optional, nullable, and readonly properties accurately;
- use literal unions for finite application states;
- explain structural compatibility between object shapes.

## A model is a set of allowed values

This type does more than document an issue:

```ts
type IssueStatus = 'backlog' | 'todo' | 'in_progress' | 'done';

type Issue = {
  readonly id: number;
  title: string;
  description?: string;
  status: IssueStatus;
};
```

It records four decisions:

- every issue has an `id`, `title`, and `status`;
- `description` may be absent;
- only four status strings are valid;
- our typed code must not replace an issue's identity.

If we used `status: string`, values such as `'don'` and `''` would be accepted even though the product has no meaning for them. A **literal union** keeps the finite domain visible and gives later code useful information.

## Optional and nullable say different things

These shapes are not interchangeable:

```ts
type Draft = {
  description?: string;
};

type AssignedIssue = {
  assignee: UserSummary | null;
};
```

`description?: string` means the property may be absent. `assignee: UserSummary | null` means the property must exist, but `null` deliberately represents an unassigned issue.

That distinction matters in forms, JSON responses, PATCH requests, and database columns. Ask a product question before choosing syntax: is the field omitted, or is “no value” an explicit state?

The lab enables `exactOptionalPropertyTypes`. Under that rule, an optional string is absent or a string; `{ description: undefined }` is not treated as the same thing as omitting the property.

## Related data deserves a related shape

An assignee has fields that belong together:

```ts
type UserSummary = {
  readonly id: number;
  name: string;
};

type Issue = {
  readonly id: number;
  title: string;
  status: IssueStatus;
  assignee: UserSummary | null;
};
```

This is clearer than unrelated `assigneeId` and `assigneeName` properties that can disagree. TypeScript checks the nested shape too: a string user ID or a missing name is rejected in source the checker can see.

Both `type` and `interface` can name ordinary object contracts:

```ts
interface UserSummary {
  readonly id: number;
  name: string;
}
```

Type aliases can also name unions and other compositions. Interfaces have extension and declaration-merging behavior. We do not need a style war; we need an honest shape. Neither declaration creates a runtime class or validator.

## Readonly is shallow and compile-time only

`readonly id` prevents assignment through that typed property:

```ts
const issue: Issue = {
  id: 17,
  title: 'Broken search',
  status: 'todo',
  assignee: null,
};

issue.id = 99; // checker error
```

It does not freeze the JavaScript object. It is erased at runtime, and it does not automatically protect nested fields. If immutability must be enforced at runtime, use a runtime mechanism and an application design that supports it.

## Compatibility follows shape

TypeScript is primarily structurally typed. A richer object can satisfy a smaller contract when it has every required member:

```ts
type IssueSummary = { id: number; title: string };

const issue = {
  id: 17,
  title: 'Broken search',
  status: 'todo',
  priority: 'high',
};

const summary: IssueSummary = issue;
```

`issue` was not constructed from a class named `IssueSummary`; its structure is enough. Fresh object literals assigned directly to a typed target receive an additional excess-property check, which helps catch misspelled or unintended fields at the point they are written.

## Try it

**Workspace:** `.dalt/workspace/fs02-3-modeling`

**Starting state:** copy the modeling lab and install its pinned TypeScript version.

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/typescript-modeling-lab/starter .dalt/workspace/fs02-3-modeling
cd .dalt/workspace/fs02-3-modeling
npm ci
```

Predict each outcome, then run:

```bash
npm run stage:optional
npm run stage:readonly
npm run stage:status
npm run stage:structural
```

The first three commands intentionally fail. They expose invalid `null`/`undefined` usage, reassignment of a readonly property, and a status outside the literal union. The structural stage succeeds because its richer issue contains the smaller summary shape.

Open `src/exercise.ts`. Change its old optional `assigneeId` model into this explicit relationship:

```ts
type UserSummary = { readonly id: number; name: string };

type InitialIssue = {
  readonly id: number;
  title: string;
  description?: string;
  status: 'backlog' | 'todo' | 'in_progress' | 'done';
  assignee: UserSummary | null;
};
```

Run `npm run typecheck` before repairing the example values. Read the errors as a map of old assumptions. Give the assigned issue a complete `UserSummary`, give the unassigned issue `assignee: null`, and run `npm run typecheck` again.

**Expected result:** the deliberate stage mistakes fail for their modeled reasons; structural compatibility passes; the changed exercise fails until every old caller expresses the new assignee decision.

**Reset:** delete `.dalt/workspace/fs02-3-modeling`.

## What to notice

A model change causing several errors is useful evidence. Each diagnostic identifies code that still assumes the old contract. Weakening the model—making every property optional or widening a finite status to `string`—can make the checker green while making the application less truthful.

## Check your understanding

1. How does `description?: string` differ from `description: string | null`?
2. Why is a literal union stronger than `string` for issue status?
3. What does `readonly` prevent, and what does it not do?
4. Why can a richer issue satisfy `IssueSummary`?
5. When an assignee field must always exist, but may represent nobody, which shape communicates that?

<details>
<summary>Check your answers</summary>

1. Optional allows the property to be absent; nullable requires it and permits the deliberate value `null`.
2. It lists the finite valid values and rejects all other strings.
3. It prevents typed reassignment through that property. It does not freeze runtime JavaScript or deeply protect nested objects.
4. Structural typing checks for the required members; extra members on an existing value do not remove compatibility.
5. `assignee: UserSummary | null`.
</details>

## Next

Next we will use runtime checks to narrow unions and `unknown` into the specific possibility our code can safely use.

<details>
<summary>Maintainer source record</summary>

- Source dossier: `TYPESCRIPT_HANDBOOK.md`; `FSO_TYPESCRIPT.md`.
- Official sources: TypeScript Handbook, *Everyday Types*, *Object Types*, and `exactOptionalPropertyTypes` TSConfig reference.
- Versions: TypeScript 5.9.3; Node 25.
- Consulted: 2026-08-22.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 3, FS02.3.
- DALT files inspected: `.dalt/course/fullstack/typescript-modeling-lab/starter/**` and its executable lab expectations.
- Reused material: former FS02.2 domain-modeling explanations and experiment, condensed around valid-state decisions.
</details>
