# Part 07 lab — Routed and tested frontend

This shared React + TypeScript lab grows across Part 07:

- `RouteDemo` gives screens durable URLs;
- `AuthSession` resolves a server session before protected navigation;
- `ProjectPage` teaches behavior testing through an injectable API seam.

The component exercise starts red on purpose. Work in a copy, never in the course starter.

## Set up

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/frontend-testing-lab/starter \
  .dalt/workspace/fs07-frontend-testing
cd .dalt/workspace/fs07-frontend-testing
npm ci
```

## Check each completed slice

```bash
npm run test:routing     # 4 passed
npm run test:session     # 4 passed
npm run test:parsers     # 4 passed
npm run typecheck        # clean
npm run build            # production bundle created
```

## The FS07.3 defect

Run the component tests:

```bash
npm run test:components
```

All six fail with an error containing:

```text
Failed to parse URL
```

The tests wrap `ProjectPage` in an `ApiProvider` whose typed fake never touches the network. The component ignores it because `src/ProjectPage.tsx` imports the real `issueApi` directly. TypeScript remains green because both clients satisfy the same interface.

Replace the two lines marked `STAGE 1`:

```tsx
import { useIssueApi } from './ApiContext';

// inside ProjectPage
const api = useIssueApi();
```

Run the focused command again:

```bash
npm run test:components   # 6 passed
npm test                  # 18 passed
```

The context's default remains the real API client, so the production entry point needs no special test wiring.

## Prove the tests can fail honestly

Break one behavior at a time, run `npm run test:components`, and restore it:

```text
IssueList returns null for an empty list       → empty-state test fails
ProjectPage checks title without trim()        → whitespace-title test fails
CreateIssueForm clears a rejected draft        → preserved-draft test fails
IssueList changes <li> to <div>                 → listitem query fails
```

Each failure should name a missing visible result. The course test also applies a plausible fake automatically: it wires the seam correctly but breaks whitespace validation, and requires the behavior suite to reject it.

## Reset

Delete the workspace copy and repeat setup whenever you want the original red exercise again. Keep a working copy for FS07.4.
