# FS02.6 — Runtime boundaries and parsing external data

Lesson ID: FS02.6
Lesson format: Concise theory
Part: 02 — TypeScript foundations
Status: Published
Estimated effort: 40–45 minutes
Difficulty: Foundation
Prerequisites: FS02.5
Last reviewed: 2026-08-22

We will convert untrusted runtime input into trusted application data by checking and reconstructing every field we depend on.

> **Helpful background:** [Typed functions, generics, and reusable relationships](/learn/lessons/27-fs02-4-functions-generics-and-reusable-types)

## What we will learn

By the end, we can:

- identify where TypeScript's knowledge stops;
- keep external values `unknown` until runtime evidence exists;
- write and test a parser that returns a trusted domain value or throws a useful error.

## The checker cannot see across I/O

Types describe relationships in checked source. Values from outside that source arrive at runtime:

```text
DALT JSON response ─┐
localStorage ───────┼→ unknown runtime value → parser → trusted Issue
user input ─────────┘
```

A successful network request proves that bytes arrived, not that those bytes match our `Issue` type.

This assertion skips the proof:

```ts
const parsed: unknown = JSON.parse(text);
const issue = parsed as Issue;
```

`as Issue` does not inspect `parsed`, convert fields, or create an `Issue`. It tells the checker to treat the value as one. The emitted JavaScript contains no assertion, so malformed data continues unchanged.

## JSON syntax is not domain validity

`JSON.parse` establishes only that text is valid JSON syntax. `null`, `[]`, and this object are all valid JSON:

```json
{"id":"42","title":null,"status":"banana"}
```

None is a valid issue for this application:

```ts
type IssueStatus = 'backlog' | 'todo' | 'in_progress' | 'done';

type Issue = {
  id: number;
  title: string;
  status: IssueStatus;
  description: string | null;
};
```

The type is the destination. A runtime parser is the evidence-producing path to it.

## Prove an object before reading fields

JavaScript's `typeof null` is `'object'`, and arrays are objects too. Start with a narrow record check:

```ts
function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object'
    && value !== null
    && !Array.isArray(value);
}
```

After this succeeds, property reads are permitted but each property remains `unknown`. The parser must establish every field independently.

A finite union needs runtime evidence too:

```ts
const issueStatuses = new Set<string>([
  'backlog', 'todo', 'in_progress', 'done',
]);

function isIssueStatus(value: unknown): value is IssueStatus {
  return typeof value === 'string' && issueStatuses.has(value);
}
```

The TypeScript union disappears at runtime, so the set supplies actual strings to compare.

## A parser earns its return type

A parser checks the input and constructs a fresh trusted object:

```ts
export function parseIssue(value: unknown): Issue {
  if (!isRecord(value)) {
    throw new Error('Issue must be an object.');
  }
  if (typeof value.id !== 'number') {
    throw new Error('Issue id must be a number.');
  }
  if (typeof value.title !== 'string') {
    throw new Error('Issue title must be a string.');
  }
  if (!isIssueStatus(value.status)) {
    throw new Error('Issue status is invalid.');
  }
  if (value.description !== null
    && typeof value.description !== 'string') {
    throw new Error('Issue description must be text or null.');
  }

  return {
    id: value.id,
    title: value.title,
    status: value.status,
    description: value.description,
  };
}
```

The return type is justified by control flow. Reconstructing the object prevents unknown extra properties from silently entering the trusted domain value.

A **guard** returns a boolean and lets its caller decide what failure means. A **parser** returns trusted data or a structured failure and is useful when the boundary owns that decision.

## Test the boundary, not one favorite fixture

A useful parser test proves both directions:

- different valid issues are accepted;
- malformed IDs, titles, statuses, descriptions, arrays, and `null` are rejected;
- the parser constructs a trusted result instead of returning the same unknown object.

One fixture is not enough. A fake parser that recognizes exact text or object identity must fail on a second valid value and varied invalid values.

## Try it

**Workspace:** `.dalt/workspace/fs02-6-runtime-boundaries`

**Starting state:** copy the course-owned boundary lab.

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/typescript-runtime-boundaries-lab/starter .dalt/workspace/fs02-6-runtime-boundaries
cd .dalt/workspace/fs02-6-runtime-boundaries
npm ci
```

First observe the unsafe shortcut and the unimplemented honest path:

```bash
npm run typecheck
npm run unsafe
npm run test
```

The typecheck succeeds. `unsafe` then fails with a JavaScript `TypeError`, proving that `as Issue` validated nothing. The test fails at the parser's explicit unfinished-boundary exception.

Open `src/parser.ts` and replace the seeded exception with the parser shown above. The file already contains `isIssueStatus` and `isRecord`, so add only the field checks and reconstructed return value. Then run:

```bash
npm run typecheck
npm run test
npm run run
```

All three now succeed. The test prints `boundary tests passed: valid issues accepted; malformed fixtures rejected`, and the program prints a label for issue 42.

**Expected result:** the unsafe assertion remains compiler-green and runtime-red; the parser remains red until it establishes every required field, then varied tests and the program pass.

**Reset:** delete `.dalt/workspace/fs02-6-runtime-boundaries`.

## What to notice

TypeScript becomes useful at a runtime boundary when we begin with honest uncertainty. Starting with `unknown` forces the parser to produce evidence. Starting with `as Issue` hides the missing evidence.

Validation does not replace TypeScript, and TypeScript does not replace validation. The parser connects them: runtime checks establish a value the rest of our typed source can safely use.

## Check your understanding

1. What does `JSON.parse` prove about a value?
2. What runtime work does `as Issue` perform?
3. Why must an object check exclude both `null` and arrays?
4. Why does `IssueStatus` need runtime values for validation?
5. What evidence should make a plausible fake parser fail?

<details>
<summary>Check your answers</summary>

1. Only that the input text is valid JSON syntax.
2. None; the assertion is erased.
3. JavaScript classifies both as objects, but neither has the required record shape.
4. The union type is erased, so runtime code needs actual strings to compare.
5. A second valid value and varied malformed values prevent fixture-specific or identity-based shortcuts.
</details>

## Next

Part 02 has given us the TypeScript foundation for React: honest models, narrowed state, reusable contracts, and a trustworthy server boundary.

<details>
<summary>Maintainer source record</summary>

- Source dossier: `TYPESCRIPT_HANDBOOK.md`; `FSO_TYPESCRIPT.md`.
- Official sources: TypeScript Handbook, *The Basics*, *Narrowing*, `unknown`, and type assertions; JavaScript `JSON.parse` behavior.
- Versions: TypeScript 5.9.3; Node 25.
- Consulted: 2026-08-22.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 3, FS02.6.
- DALT files inspected: `.dalt/course/fullstack/typescript-runtime-boundaries-lab/starter/**`, its tests, and executable expectations.
- Reused material: former FS02.5 trust-boundary, unsafe assertion, record check, status guard, parser, and fake-resistant tests.
</details>
