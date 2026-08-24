# FS02.1 — What TypeScript checks—and what it cannot

Lesson ID: FS02.1
Lesson format: Concise theory
Part: 02 — TypeScript foundations
Status: Published
Estimated effort: 30–40 minutes
Difficulty: Foundation
Prerequisites: FS01.4
Last reviewed: 2026-08-22

TypeScript checks relationships in our source before JavaScript runs; it does not inspect the values that later arrive at runtime.

> **Helpful background:** [Promises, fetch, and failure boundaries](/learn/lessons/57-fs01-4-promises-fetch-and-failure)

## What we will learn

By the end, we can:

- distinguish a TypeScript diagnostic from a JavaScript runtime failure;
- explain why types disappear before the program runs;
- describe what a successful typecheck actually proves.

## Two moments, two kinds of evidence

Consider a title-formatting function written in JavaScript:

```js
function displayTitle(title) {
  return title.trim().toUpperCase();
}

displayTitle(17);
```

JavaScript accepts the call, starts executing it, and then fails because a number has no `trim` method. TypeScript lets us state the intended relationship:

```ts
function displayTitle(title: string): string {
  return title.trim().toUpperCase();
}

displayTitle(17);
```

Now the checker reports that a number was supplied where a string is required. That happens before this call executes.

```text
source code → TypeScript checks declared relationships → diagnostics
values at runtime → JavaScript executes operations → behavior or failure
```

TypeScript does not change how `trim` behaves. It gives us earlier evidence about code the checker can see.

## Types describe code; values run

A type alias and an interface help the checker understand shapes:

```ts
type IssueKey = string;

interface IssueSummary {
  id: IssueKey;
  title: string;
}

function label(issue: IssueSummary): string {
  return `${issue.id}: ${issue.title}`;
}
```

When TypeScript emits JavaScript, `IssueKey`, `IssueSummary`, parameter annotations, and return annotations disappear. The function remains:

```js
function label(issue) {
  return `${issue.id}: ${issue.title}`;
}
```

This is **type erasure**. A type alias is not a runtime object, so `value instanceof IssueSummary` cannot work. There is no JavaScript value named `IssueSummary` to inspect.

The same distinction appears in modules:

```ts
import type { IssueSummary } from './contracts.js';
```

`import type` says that the import is needed only while checking. It is erased rather than becoming a runtime dependency.

## What a green typecheck means

Suppose our source says a value is an `IssueSummary`:

```ts
declare const response: IssueSummary;

console.log(response.title.toUpperCase());
```

The checker can use that declaration, but it cannot know whether a server will really send `{ "title": 42 }`. A successful check means:

> Under this project's TypeScript rules, the relationships visible in the checked source are consistent.

It does not mean every server response matches our types, every user input is valid, I/O succeeds, JavaScript cannot throw, or our business rules are correct. We will establish runtime evidence at external boundaries in FS02.6.

## Project rules matter

The lab's `tsconfig.json` enables strict checking:

```json
{
  "compilerOptions": {
    "strict": true,
    "noEmitOnError": true
  }
}
```

`tsconfig.json` defines the TypeScript project and the rules used to interpret it. Strict checking prevents uncertain relationships from quietly becoming `any`. We will not memorize every option. The useful habit is to run the project's own typecheck command, because it uses the project's actual configuration.

Typechecking and building are separate jobs. `tsc --noEmit` checks without creating JavaScript. A later tool such as Vite may transform and bundle source for the browser. A build pipeline must choose deliberately whether and where typechecking runs.

## Try it

**Workspace:** `.dalt/workspace/fs02-1-typescript-lab`

**Starting state:** copy the course-owned starter and install its pinned dependency.

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/typescript-lab/starter .dalt/workspace/fs02-1-typescript-lab
cd .dalt/workspace/fs02-1-typescript-lab
npm ci
```

Read `src/runtime-failure.mjs` and `src/checker-catches-it.ts`. Predict which phase reports each mistake, then run:

```bash
npm run runtime
npm run stage-a
```

Both commands intentionally fail. The first reaches a JavaScript `TypeError`; the second reports a TypeScript diagnostic before execution.

Now inspect erasure:

```bash
npm run emit:erasure
sed -n '1,220p' .tmp/erasure/erasure.js
npm run run:erasure
```

The final three commands succeed. Types and type-only imports are absent from the emitted JavaScript, while values and executable statements remain.

**Expected result:** one runtime failure, one checker failure, and runnable emitted JavaScript with its type syntax erased.

**Reset:** leave the repository root and delete `.dalt/workspace/fs02-1-typescript-lab`. The course-owned starter remains unchanged.

## What to notice

The two failures are not interchangeable. The runtime failure proves JavaScript received a bad value and attempted an invalid operation. The checker failure proves the source contained a relationship TypeScript could reject early. The emitted file proves that the checker is not present when JavaScript runs.

When a diagnostic appears, ask what type the checker has, what type is required, and whether the source, the model, or missing runtime evidence is responsible. `any` and assertions can silence feedback without repairing the relationship.

## Check your understanding

Without looking back, answer:

1. When does a TypeScript diagnostic occur, compared with a runtime error?
2. Which parts of `type Issue = { title: string }` exist at runtime?
3. What does `import type` communicate?
4. Can a green typecheck prove that JSON from a server matches `Issue`? Why?
5. What is the difference between typechecking and building?

<details>
<summary>Check your answers</summary>

1. A diagnostic is produced while checking source; a runtime error occurs while JavaScript executes real values.
2. None of the type declaration remains. It is erased.
3. The imported names are checker-only information and should not create a runtime import.
4. No. The server value was not established by the checked source; it needs runtime validation or parsing.
5. Typechecking evaluates static relationships. Building transforms and packages runnable assets.
</details>

## Next

Next we will use everyday types and inference to describe values without annotating every line.

<details>
<summary>Maintainer source record</summary>

- Source dossier: TypeScript Handbook research notes; Full Stack Open TypeScript research notes.
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: TypeScript Handbook, *The Basics*, *Everyday Types*, modules theory, and TSConfig references for `strict`, `noEmit`, and `noEmitOnError`.
- Versions: TypeScript 5.9.3; Node 25.
- Consulted: 2026-08-22.
- Curriculum authority: DALT Fullstack theory curriculum, Batch 3, FS02.1.
- DALT files inspected: `.dalt/course/fullstack/typescript-lab/starter/**` and `FullstackLabExecutionTest.php`.
- Reused material: former FS02.1 explanations and erasure experiment, narrowed to the checker/runtime boundary.
</details>
