# Part 08 lab — Server and application state

This shared React + TypeScript lab grows across Part 08:

- `StateAudit` holds every value by hand, so ownership mistakes become visible;
- later lessons add a query cache, mutations, and a bounded client store.

`src/issueApi.ts` provides an in-memory stand-in for DALT. It records every call in
`api.calls`, which is the evidence most experiments in this part depend on.

Work in a copy, never in the course starter.

## Set up

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/state-architecture-lab/starter \
  .dalt/workspace/fs08-state-architecture
cd .dalt/workspace/fs08-state-architecture
npm ci
```

## Check each completed slice

```bash
npm run test:audit   # 4 passed
npm run typecheck    # clean
npm run build        # production bundle created
```

## Prove the audit tests can fail honestly

Change one thing at a time, rerun `npm run test:audit`, and restore it:

```text
render derivedOpenCount in place of storedOpenCount  → the drift test fails
read the status filter from useState instead         → the URL test fails
give both IssueStatusReaders one shared useState     → the disagreement test fails
```

## Reset

Delete the workspace copy and repeat setup. Keep a working copy for the next lesson.
