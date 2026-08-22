# Part 09 lab — Advanced React and tooling

This shared React + TypeScript lab grows across Part 09:

- `src/features/issues/` holds three custom hooks and the feature's public `index.ts`;
- later lessons add a dependency-direction check, public configuration, and error
  boundaries.

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
npm run test:hooks   # 4 passed
npm run typecheck    # clean
npm run build        # production bundle created
```

## Prove the hook tests can fail honestly

Change one thing at a time, rerun `npm run test:hooks`, and restore it:

```text
drop issueId from the useIssueEvents dependency array  → the cleanup test fails
return the raw ?status= value without validating it    → the URL test fails
give both SelectionProbes one lifted useState          → the independence test fails
```

## Reset

Delete the workspace copy and repeat setup. Keep a working copy for the next lesson.
