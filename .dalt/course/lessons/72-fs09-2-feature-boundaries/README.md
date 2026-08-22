# FS09.2 — Feature boundaries and dependency direction

Lesson ID: FS09.2
Lesson format: Concise theory
Part: 09 — Advanced React and tooling
Status: Published
Estimated effort: 30–40 minutes
Difficulty: Applied
Prerequisites: FS09.1
Last reviewed: 2026-08-23

We will group a growing frontend by what changes together, and make the direction of its dependencies a rule a machine can check.

> **Helpful background:** [Custom hooks as reusable behavior](/learn/lessons/48-fs09-1-custom-hooks-and-feature-boundaries)

## What we will learn

- organise by feature rather than by file type;
- give each feature a public surface and keep the rest internal;
- express dependency direction as a check, because the compiler will not.

## Group by what changes together

Folders named `components/`, `hooks/`, and `types/` sort files by what they *are*. Almost no change is shaped like that. Adding a filter to the issue list touches a component, a hook, and a type — three folders, and none of them tells you they belong together.

Grouping by feature puts a change in one place:

```text
src/
  shared/            cross-feature primitives: types, formatting, transport
  features/
    issues/          pages, hooks, and types that change together
      index.ts       the feature's public surface
  app/               routing, providers, configuration, the composition root
```

This is a map for future change, not a hierarchy to admire. Start from the code you already have; do not create nineteen folders in advance.

## One direction, three layers

The layout only helps if the arrows point one way:

```text
app        →  may use features and shared
features   →  may use shared, and other features only through their index.ts
shared     →  may use nothing but shared
```

`shared` is the load-bearing rule. The moment a shared module imports from a feature, that feature stops being removable, the "shared" name stops being true, and two features can pull each other in through a helper neither of them owns.

A feature's `index.ts` is the promise about what other code may rely on:

```ts
export { useIssueFilters } from './useIssueFilters';
export { useIssueSelection } from './useIssueSelection';
export type { IssueFilters } from './useIssueFilters';
```

Anything not exported there is internal, and another feature reaching past it into `features/issues/useIssueFilters` has coupled itself to a file that was free to change.

## TypeScript will not help you here

A wrong-direction import is not a type error. It compiles, it bundles, it ships. So the rule has to be written down somewhere executable:

```js
function violationFor(fromFile, toFile) {
  const from = layerOf(fromFile);
  const to = layerOf(toFile);

  if (from.kind === 'shared' && to.kind !== 'shared') {
    return `shared code must not depend on ${to.kind === 'feature' ? `feature '${to.name}'` : 'the app layer'}`;
  }
  if (from.kind === 'feature' && to.kind === 'app') {
    return 'a feature must not depend on the app layer';
  }
  if (from.kind === 'feature' && to.kind === 'feature' && from.name !== to.name && !to.isPublicSurface) {
    return `feature '${from.name}' reaches into the internals of feature '${to.name}'`;
  }

  return null;
}
```

Two details matter more than they look. The pattern that finds imports must also match `import type`, because a type-only import is still a direction between modules. And the layer of a file is decided by *where it sits*, not by what it is called — a rule that depends on naming discipline is a rule that decays.

## Moving a dependency, not hiding it

When shared code needs something a feature owns, there are only two honest answers: the thing was never feature knowledge and belongs in `shared`, or the shared module was never shared and belongs in the feature.

The dishonest answer is to keep the direction and route it through the feature's `index.ts`. It reads like respecting the boundary. It is the same arrow.

## Try it

**Workspace:** continue in the Part 09 lab, or copy a clean starter:

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/frontend-architecture-lab/starter \
  .dalt/workspace/fs09-frontend-architecture
cd .dalt/workspace/fs09-frontend-architecture
npm ci
```

**Starting state:** `src/shared/formatIssueLabel.ts` imports `issueStatusLabels` from inside the issues feature. The line is marked `STAGE 1`.

```bash
npm run typecheck        # clean
npm run build            # succeeds
npm run check:boundaries # fails
```

**Expected result:** the type check and the production build both succeed, and the boundary check exits non-zero with:

```text
boundary violation: src/shared/formatIssueLabel.ts -> src/features/issues/issueStatusLabels.ts
  shared code must not depend on feature 'issues'
```

Fix it by moving the labels: `git mv`-style, `src/features/issues/issueStatusLabels.ts` becomes `src/shared/issueStatusLabels.ts`, its import of `../../shared/types` becomes `./types`, and `formatIssueLabel` imports it from `./issueStatusLabels`. Rerun the three commands; all three now pass.

Then try the tempting non-fix: put the labels back in the feature, export them from `features/issues/index.ts`, and import that from `formatIssueLabel`. The check fails again, and it names `index.ts` — going through the front door does not reverse the arrow.

**Reset:** delete the workspace copy, or keep it for FS09.3.

## What to notice

`npm run typecheck` and `npm run build` are green in every one of those states, including the broken one. That is the whole reason the check exists: the compiler enforces types, and nothing enforces architecture unless we write it down.

The public-surface fake is worth doing yourself rather than reading about. It feels like a fix.

## Common mistakes

- A `common/` or `utils/` folder that accumulates whatever had no obvious home, and eventually imports from three features.
- Importing `features/issues/useIssueFilters` directly from another feature instead of `features/issues`.
- Writing the rule in a document rather than in a script. Undocumented rules decay; unexecuted rules decay faster.
- Reorganising every file at once. Move one boundary, run the checks, keep going.

## Check your understanding

1. Why is grouping by feature usually better than grouping by file type?
2. What does a feature's `index.ts` promise?
3. Why can TypeScript not catch a wrong-direction import?
4. Why must the import scan also match `import type`?

<details><summary>Check your answers</summary>

1. Because changes arrive shaped like features, so a feature-shaped tree keeps one change in one place.
2. That everything it exports is safe for other code to depend on, and everything else is internal and free to change.
3. Because the import is perfectly well typed; direction is an architectural rule, not a type rule.
4. A type-only import still couples one module to another, so ignoring it leaves an easy way to keep a wrong dependency.
</details>

## Next

Next we will look at what the build actually produces, which configuration values are public, and how to contain a render failure.

<details><summary>Maintainer source record</summary>

- Source dossier: `FSO_PART_07.md`, sections 36–48.
- Official sources: TypeScript module resolution and `import type` semantics; React "Thinking in React" component organisation guidance; Node.js `fs` and `path` APIs used by the check script.
- Versions: React 19.2.3; TypeScript 5.9.3; Node 25.0.0; Vite 8.0.12.
- Consulted: 2026-08-23.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 10, FS09.2.
- DALT files inspected: `frontend-architecture-lab`, the Part 09 track manifest, and the former FS09.1 page.
- Extracted material: the "group by change" section and the feature-tree sketch from the former FS09.1, rewritten around an executable rule.
- Verified in the lab: the starter fails the check while typecheck and build succeed; moving the labels to `shared` makes all three pass; re-exporting them from the feature index fails again and names `index.ts`.
</details>
