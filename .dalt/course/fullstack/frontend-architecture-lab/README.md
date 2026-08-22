# Part 09 lab — Advanced React and tooling

This shared React + TypeScript lab grows across Part 09:

- `src/features/issues/` holds three custom hooks and the feature's public `index.ts`;
- `scripts/check-boundaries.mjs` enforces the direction of dependencies between
  `shared`, `features`, and `app`;
- a later lesson adds public configuration and error boundaries.

`src/shared/formatIssueLabel.ts` starts with a deliberate wrong-direction import. It
typechecks and builds; only the boundary check reports it.

Work in a copy, never in the course starter.

## Set up

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/frontend-architecture-lab/starter \
  .dalt/workspace/fs09-frontend-architecture
cd .dalt/workspace/fs09-frontend-architecture
npm ci
```

## Check each completed slice

```bash
npm run test:hooks        # 4 passed
npm run typecheck         # clean
npm run build             # production bundle created
npm run check:boundaries  # fails until STAGE 1 is fixed
```

## Prove the hook tests can fail honestly

Change one thing at a time, rerun `npm run test:hooks`, and restore it:

```text
drop issueId from the useIssueEvents dependency array  → the cleanup test fails
return the raw ?status= value without validating it    → the URL test fails
give both SelectionProbes one lifted useState          → the independence test fails
```

## The FS09.2 defect

```bash
npm run check:boundaries
```

```text
boundary violation: src/shared/formatIssueLabel.ts -> src/features/issues/issueStatusLabels.ts
  shared code must not depend on feature 'issues'
```

Move `issueStatusLabels.ts` into `src/shared/`, change its import of `../../shared/types`
to `./types`, and import it from `./issueStatusLabels` in `formatIssueLabel.ts`. The check
passes.

The course test also applies a plausible fake automatically: it re-exports the labels from
`features/issues/index.ts` and imports that from shared. The check must still fail, because
the direction is unchanged.

## Reset

Delete the workspace copy and repeat setup. Keep a working copy for the next lesson.
