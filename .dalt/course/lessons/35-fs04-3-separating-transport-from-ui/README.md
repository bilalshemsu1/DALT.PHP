# FS04.4 — Typed API functions and UI boundaries

Lesson ID: FS04.4
Lesson format: Concise theory
Part: 04 — React and the server
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Foundation
Prerequisites: FS04.3
Last reviewed: 2026-08-22

We will move HTTP mechanics and runtime parsing behind small typed functions so React components express application intent.

> **Helpful background:** [Mutating server data honestly](/learn/lessons/34-fs04-2-mutating-server-data)

## What we will learn

- design API functions around application operations;
- keep external JSON `unknown` until a parser validates it;
- separate transport errors from React rendering and state decisions.

## Extract after the repetition appears

Our component now repeats URLs, headers, status checks, JSON parsing, and domain parsing. That pressure has earned a boundary. Create `src/api/issues.ts` with a small public surface:

```ts
export async function listIssues(signal?: AbortSignal): Promise<Issue[]>;
export async function createIssue(input: IssueDraft): Promise<Issue>;
export async function updateIssueStatus(id: string, status: IssueStatus): Promise<Issue>;
export async function deleteIssue(id: string): Promise<void>;
```

These names state what the application wants. Callers should not need to remember whether update uses PUT or PATCH, which endpoint returns 204, or where runtime parsing occurs.

## One transport helper owns shared mechanics

The internal helper can normalize requests without becoming a generic framework:

```ts
const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL
  ?? 'http://127.0.0.1:8034').replace(/\/$/, '');

class ApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly code?: string,
  ) {
    super(message);
  }
}

async function request(
  path: string,
  init: RequestInit = {},
): Promise<unknown | undefined> {
  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...init,
    headers: init.body === undefined
      ? init.headers
      : { 'Content-Type': 'application/json', ...init.headers },
  });

  if (!response.ok) {
    const failure: unknown = await response.json().catch(() => undefined);
    throw new ApiError(errorMessage(failure, response.status), response.status);
  }

  if (response.status === 204) return undefined;
  return response.json() as Promise<unknown>;
}
```

`VITE_API_BASE_URL` is build-time client configuration and is public in the browser bundle; never put a secret there. The fallback keeps the disposable lab simple. Removing one trailing slash prevents accidental `//api/issues` paths.

The helper preserves HTTP status in `ApiError`, tolerates non-JSON error bodies, and branches before parsing a 204. `errorMessage` can cautiously inspect the fixture's `{ error: { message } }` shape and fall back to `Request failed with 422.` It must not throw while trying to describe the original failure.

## Public functions establish domain types

Transport produces `unknown`; each operation proves its own result:

```ts
export async function listIssues(signal?: AbortSignal): Promise<Issue[]> {
  return parseIssues(await request('/api/issues', { signal }));
}

export async function createIssue(input: IssueDraft): Promise<Issue> {
  return parseIssue(await request('/api/issues', {
    method: 'POST',
    body: JSON.stringify(input),
  }));
}

export async function updateIssueStatus(
  id: string,
  status: IssueStatus,
): Promise<Issue> {
  return parseIssue(await request(`/api/issues/${encodeURIComponent(id)}`, {
    method: 'PATCH',
    body: JSON.stringify({ status }),
  }));
}

export async function deleteIssue(id: string): Promise<void> {
  await request(`/api/issues/${encodeURIComponent(id)}`, { method: 'DELETE' });
}
```

The return types are promises because network work completes later. They are trustworthy because the implementations parse, not because a type assertion labels untrusted JSON.

## React owns presentation policy

The API module should contain no JSX, state setters, toast calls, or navigation. It reports typed success or throws a useful error. A component decides what that means for the current screen:

```tsx
try {
  const created = await createIssue(draft);
  setIssues((current) => [...current, created]);
} catch (error: unknown) {
  setMessage(error instanceof Error ? error.message : 'Could not create issue.');
}
```

This division keeps dependencies pointing in a useful direction:

```text
React screen → typed API operations → fetch + runtime parser
```

The transport layer knows the server contract but not how an alert looks. The screen knows the user context but not how to build headers.

## Keep the boundary small

Four operations do not require a repository base class, dependency-injection container, or universal request DSL. A small module is easy to read and change. Extract more only when repeated behavior creates evidence for another abstraction.

Keep abort support on reads. Do not catch errors inside the API module merely to replace every useful fact with “Something went wrong.” Normalization should make failure easier to act on, not erase it.

## Try it

**Workspace:** continue in `.dalt/workspace/fs04-react-server` with the fixture running.

**Starting state:** `App.tsx` contains the working list fetch and create mutation from FS04.3.

First create `src/vite-env.d.ts` so this starter's checker knows Vite's client environment shape:

```ts
/// <reference types="vite/client" />
```

Then create `src/api/issues.ts`. Move `parseIssue` and `parseIssues` into it, add `request`, then implement `listIssues` and `createIssue` exactly as above. A compact error reader is enough:

```ts
function errorMessage(value: unknown, status: number): string {
  if (typeof value === 'object' && value !== null && 'error' in value) {
    const error = (value as { error?: unknown }).error;
    if (typeof error === 'object' && error !== null && 'message' in error) {
      const message = (error as { message?: unknown }).message;
      if (typeof message === 'string') return message;
    }
  }
  return `Request failed with ${status}.`;
}
```

Replace the component's raw GET with `listIssues(controller.signal)` and its raw POST with `createIssue(draft)`. Run:

```bash
npm run typecheck
npm test
npm run build
npm run dev
```

Reload and create a sample issue. Both flows behave as before. Temporarily change `VITE_API_BASE_URL` in `.env.local` to `http://127.0.0.1:9999`, restart Vite, and confirm both operations fail through the same boundary. Remove `.env.local`, restart, and confirm they recover.

**Expected result:** all static checks and the build pass; React contains no URL, method, header, or JSON parsing for the extracted flows; success and failure remain observable.

**Reset:** stop both servers and delete `.dalt/workspace/fs04-react-server`.

## What to notice

The refactor changes responsibility, not behavior. We first experienced the repetition, then moved it. The API functions now make components easier to read while keeping runtime proof at the exact place untrusted data enters.

## Check your understanding

1. Why does `request` return `unknown` rather than `Issue`?
2. Where should a 204 response be handled?
3. Why should the API module not call a React state setter?
4. Is `VITE_API_BASE_URL` a safe place for a secret?

<details><summary>Check your answers</summary>

1. Transport has not yet proven the domain shape.
2. In the shared transport boundary, before JSON parsing.
3. Presentation policy belongs to the component; coupling it prevents reuse and testing.
4. No. Vite exposes it in client code.
</details>

## Next

The browser/server boundary is explicit; Batch 6 crosses to the server and begins with modern PHP values, types, arrays, functions, and exceptions.

<details><summary>Maintainer source record</summary>

- Source dossier: `FSO_PART_02.md`; `REACT_DOCS.md`; TypeScript runtime-boundary material.
- Official sources: MDN `fetch`, `RequestInit`, `Response`, and `AbortSignal`; Vite environment-variable documentation; TypeScript `unknown` and narrowing.
- Versions: React 19.2.3; TypeScript 5.9.3; Vite 8.0.12.
- Consulted: 2026-08-22.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 5, FS04.4.
- DALT files inspected: Part 04 fixture contracts, runtime-boundary lab parser, React starter TypeScript configuration, and executable lifecycle test.
- Reused material: application-operation naming, shared request helper, runtime parsing, error normalization, environment configuration, and dependency direction from former FS04.3.
</details>
