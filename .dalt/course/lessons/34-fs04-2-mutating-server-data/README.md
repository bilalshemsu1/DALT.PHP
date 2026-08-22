# FS04.3 — Mutating server data honestly

Lesson ID: FS04.3
Lesson format: Concise theory
Part: 04 — React and the server
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Foundation
Prerequisites: FS04.2
Last reviewed: 2026-08-22

We will create, update, and delete through user events while keeping the interface aligned with confirmed server truth.

> **Helpful background:** [Loading, failure, cleanup, and request races](/learn/lessons/63-fs04-2-loading-failure-and-races)

## What we will learn

- send JSON mutations with deliberate HTTP methods and bodies;
- update local state from confirmed responses rather than guesses;
- scope pending and failure state to the operation that owns it.

## Mutations belong to the event that caused them

Loading a screen's current data is synchronization, so FS04.1 used an Effect. Creating an issue is caused by a particular form submission, so it belongs in that event path:

```tsx
async function createIssue(draft: IssueDraft) {
  const response = await fetch(ISSUES_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(draft),
  });

  if (!response.ok) {
    throw new Error(`Create failed with ${response.status}.`);
  }

  const body: unknown = await response.json();
  return parseIssue(body);
}
```

The method states the operation, `Content-Type` describes the representation, and the body is serialized exactly once. DevTools Network should show a POST with the title and priority that the form supplied.

Do not move POST into an Effect. Development remounts and ordinary navigation could repeat it because displaying a screen is not the same event as submitting a form.

## The response, not the draft, is server truth

The server assigns fields such as `id`, `projectId`, and initial status. Append the parsed 201 response only after it arrives:

```tsx
const created = await createIssue(draft);
setIssues((current) => [...current, created]);
```

Appending `{ ...draft, id: guessedId }` before confirmation can display data that never existed. Clearing the form before success can also discard the user's work after validation or server failure. A later lesson can introduce deliberate optimistic UI; the honest default is confirmed updates.

## PATCH changes part of a resource

The fixture accepts a partial status change and returns the complete updated issue:

```tsx
const response = await fetch(`${ISSUES_URL}/${encodeURIComponent(id)}`, {
  method: 'PATCH',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ status: 'done' }),
});
if (!response.ok) throw new Error(`Update failed with ${response.status}.`);
const updated = parseIssue(await response.json());

setIssues((current) => current.map((issue) =>
  issue.id === updated.id ? updated : issue,
));
```

Replacing the matching item preserves the server's complete answer and creates a new array snapshot.

## A 204 response has no JSON body

Successful DELETE returns status 204 and no content:

```tsx
const response = await fetch(`${ISSUES_URL}/${encodeURIComponent(id)}`, {
  method: 'DELETE',
});
if (!response.ok) throw new Error(`Delete failed with ${response.status}.`);

setIssues((current) => current.filter((issue) => issue.id !== id));
```

Calling `response.json()` here fails because there are no bytes to parse. The response contract determines whether a body is expected; not every success returns JSON.

## Pending state has a scope

A single page-wide `isLoading` confuses initial loading with saving one row. Give the operation enough identity:

```tsx
const [creating, setCreating] = useState(false);
const [savingIssueId, setSavingIssueId] = useState<string | null>(null);
const [mutationError, setMutationError] = useState<string | null>(null);
```

Wrap the operation so pending state always ends:

```tsx
setCreating(true);
setMutationError(null);
try {
  const created = await createIssue(draft);
  setIssues((current) => [...current, created]);
} catch (error: unknown) {
  setMutationError(error instanceof Error ? error.message : 'Create failed.');
} finally {
  setCreating(false);
}
```

Disable only controls whose repeated action would conflict. Change their visible label to “Creating…” or “Saving…” so the user understands the state.

## Try it

**Workspace:** continue in `.dalt/workspace/fs04-react-server` with both servers running.

**Starting state:** FS04.2 loads issues into explicit request state.

Add the `creating` and `mutationError` state above. Add `createIssue` to `App.tsx`, then add this focused handler:

```tsx
async function handleCreate() {
  setCreating(true);
  setMutationError(null);
  try {
    const created = await createIssue({
      title: 'Inspect the 201 response',
      priority: 'high',
    });
    setRequest((current) => current.status === 'success'
      ? { status: 'success', issues: [...current.issues, created] }
      : current);
  } catch (error: unknown) {
    setMutationError(error instanceof Error ? error.message : 'Create failed.');
  } finally {
    setCreating(false);
  }
}
```

Render the operation near the issue list:

```tsx
<button type="button" disabled={creating} onClick={() => void handleCreate()}>
  {creating ? 'Creating…' : 'Create sample issue'}
</button>
{mutationError ? <p role="alert">{mutationError}</p> : null}
```

Run:

```bash
npm run typecheck
npm run dev
```

Click **Create sample issue**. Network shows POST, status 201, and the server-assigned issue; only then does the list grow. Temporarily change the fixed title to whitespace to observe 422 without losing existing issues. Restore it, stop the fixture, and click again to observe a transport failure and a button that returns from pending.

**Expected result:** successful creation uses the parsed response; HTTP and transport failures remain visible; `finally` restores the form after every outcome.

**Reset:** keep the workspace for FS04.4, or delete `.dalt/workspace/fs04-react-server`.

## What to notice

The UI never claims that a mutation succeeded merely because a user clicked. It names pending work, waits for the actual contract, and changes local state from confirmed server data.

## Check your understanding

1. Why does POST belong in the submit event rather than an Effect?
2. Why append the response instead of the original draft?
3. Why must a 204 skip `response.json()`?
4. What job does `finally` perform?

<details><summary>Check your answers</summary>

1. The operation is caused by that particular user action.
2. The server owns fields and may normalize the accepted value.
3. A 204 response has no body to parse.
4. It ends pending state after either success or failure.
</details>

## Next

The mutations work, but components now know too much HTTP detail; next we will move transport and parsing behind small typed application operations.

<details><summary>Maintainer source record</summary>

- Source dossier: `REACT_DOCS.md`; `FSO_PART_02.md`.
- Official sources: MDN `fetch`, HTTP POST/PATCH/DELETE semantics, `Response.ok`, and 204 No Content; React event guidance.
- Versions: React 19.2.3; TypeScript 5.9.3; PHP 8.4 fixture.
- Consulted: 2026-08-22.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 5, FS04.3.
- DALT files inspected: fixture POST/PATCH/DELETE contracts and lifecycle tests, including 201, 422, 200, and bodiless 204 behavior.
- Reused material: confirmed updates, pending scope, method/body construction, 204 handling, and failure distinctions from former FS04.2.
</details>
