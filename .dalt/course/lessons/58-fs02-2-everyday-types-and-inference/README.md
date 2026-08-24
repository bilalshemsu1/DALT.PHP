# FS02.2 — Everyday types and useful inference

Lesson ID: FS02.2
Lesson format: Concise theory
Part: 02 — TypeScript foundations
Status: Published
Estimated effort: 25–35 minutes
Difficulty: Foundation
Prerequisites: FS02.1
Last reviewed: 2026-08-22

We will describe ordinary JavaScript values with TypeScript while letting the checker infer facts that do not need repeating.

> **Helpful background:** [What TypeScript checks—and what it cannot](/learn/lessons/24-fs02-1-typescript-mental-model)

## What we will learn

By the end, we can:

- use TypeScript's everyday primitive, array, and object types;
- recognize local and contextual inference;
- add annotations where they clarify a contract instead of decorating every value.

## Types begin with JavaScript values

TypeScript uses lowercase names for JavaScript's common primitive values:

```ts
const title: string = 'Broken search';
const issueCount: number = 3;
const archived: boolean = false;
```

JavaScript has one `number` type for ordinary integers and floating-point values. Use `string`, `number`, and `boolean`, not the rarely useful wrapper types `String`, `Number`, and `Boolean`.

Arrays describe the type of their elements:

```ts
const issueIds: number[] = [17, 18, 21];
const titles: Array<string> = ['Broken search', 'Refresh docs'];
```

The two array spellings mean the same thing. We will normally use `Issue[]` once we have an application type named `Issue`.

An object type describes required properties:

```ts
type IssueSummary = {
  id: number;
  title: string;
};

const issue: IssueSummary = {
  id: 17,
  title: 'Broken search',
};
```

The next lesson will make those shapes model real domain decisions. Here we are learning how much TypeScript already understands.

## Let inference carry local facts

These annotations add no new information:

```ts
const title: string = 'Broken search';
const issueCount: number = 3;
```

TypeScript can infer the same types from the initial values:

```ts
const title = 'Broken search';       // string
const issueCount = 3;               // number
const issueIds = [17, 18, 21];      // number[]
```

Inference is not weaker typing. The checker still rejects `issueIds.push('ISS-22')`. It inferred a useful constraint instead of asking us to repeat it.

Our practical rule is:

> Let inference describe local implementation details. Annotate boundaries where readers and callers need a deliberate contract.

A function parameter is such a boundary:

```ts
type IssueSummary = { id: number; title: string };

function issueLabel(issue: IssueSummary): string {
  const cleanTitle = issue.title.trim();
  return `#${issue.id} ${cleanTitle}`;
}
```

The parameter tells callers what they must provide. The return annotation states the function's promise. `cleanTitle` remains inferred because its type is obvious from `trim()`.

## Context can infer callback parameters

Inference also flows from where a function is used:

```ts
const issues: IssueSummary[] = [
  { id: 17, title: 'Broken search' },
  { id: 18, title: 'Refresh docs' },
];

const labels = issues.map((issue) => issue.title.toUpperCase());
```

We did not write `(issue: IssueSummary)`. The `map` call and the `IssueSummary[]` array provide that context. Hover `issue` in an editor and TypeScript will show the inferred type.

If TypeScript cannot infer a useful type under strict checking, it asks for information. An unconnected parameter such as `function label(value) { ... }` becomes an implicit-`any` diagnostic. Supply an honest contract rather than disabling the rule.

## Literal values may widen

A `const` binding cannot be reassigned, but an object's properties can still change:

```ts
const request = { method: 'GET' };
request.method = 'POST';
```

TypeScript therefore usually infers `request.method` as `string`, not only the literal type `'GET'`. When the entire value is intended to stay literal and readonly, `as const` can preserve that information:

```ts
const methods = ['GET', 'POST', 'PATCH'] as const;
// inferred as readonly ['GET', 'POST', 'PATCH']
```

Do not add `as const` everywhere. Use it when literal identity is part of the contract. A normal mutable object should remain normal.

## Try it

**Workspace:** `.dalt/workspace/fs02-2-inference`

**Starting state:** copy the same pinned TypeScript starter used in FS02.1, then create `src/everyday.ts` with this code:

```ts
type IssueSummary = { id: number; title: string };

const issues: IssueSummary[] = [
  { id: 17, title: 'Broken search' },
  { id: 18, title: 'Refresh docs' },
];

const labels = issues.map((issue) => issue.title.toUpperCase());
console.log(labels);
```

Set up and check only that focused file:

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/typescript-lab/starter .dalt/workspace/fs02-2-inference
cd .dalt/workspace/fs02-2-inference
npm ci
npx tsc --noEmit --strict src/everyday.ts
```

The command succeeds without output. In your editor, hover `issues`, the callback parameter `issue`, and `labels`. Then temporarily change `issue.title.toUpperCase()` to `issue.id.toUpperCase()` and rerun the command. It should report that `toUpperCase` does not exist on `number`. Restore the original line and confirm the check is quiet again.

**Expected result:** TypeScript infers the callback parameter from the array and the result as `string[]`; the deliberate numeric string-method mistake fails.

**Reset:** delete `.dalt/workspace/fs02-2-inference`. No cumulative application was changed.

## What to notice

The useful information entered once, at `IssueSummary[]`. From there it flowed into the callback and result. Repeating all three annotations would add noise without adding safety.

Inference is local reasoning, not runtime validation. The array literal is visible to the checker. A future server response is not, which is why FS02.6 still matters.

## Check your understanding

1. Which lowercase types represent text, numbers, and true/false values?
2. What does TypeScript infer for `[1, 2, 3]`?
3. Why can the parameter of an array `map` callback be typed without an annotation?
4. Where is an explicit annotation usually more valuable: a simple local constant or an exported function boundary?
5. Why might `{ method: 'GET' }` infer `method` as `string`?

<details>
<summary>Check your answers</summary>

1. `string`, `number`, and `boolean`.
2. Normally `number[]`.
3. Contextual typing flows from the array element type through `map`'s callback contract.
4. The exported boundary, because it communicates and constrains what callers exchange.
5. The property remains mutable, so another string could be assigned later. `as const` preserves literal information when that is truly intended.
</details>

## Next

Next we will turn these building blocks into an honest model of issues, users, optional fields, and finite states.

<details>
<summary>Maintainer source record</summary>

- Source dossier: TypeScript Handbook research notes; Full Stack Open TypeScript research notes.
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: TypeScript Handbook, *Everyday Types* and *Type Inference*.
- Versions: TypeScript 5.9.3; Node 25.
- Consulted: 2026-08-22.
- Curriculum authority: DALT Fullstack theory curriculum, Batch 3, FS02.2.
- DALT files inspected: `.dalt/course/fullstack/typescript-lab/starter/**`.
- Extracted from: former FS02.1 inference and everyday-syntax material.
</details>
