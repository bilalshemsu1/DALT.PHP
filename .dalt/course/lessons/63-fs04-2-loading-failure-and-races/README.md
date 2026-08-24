# FS04.2 — Loading, failure, cleanup, and request races

Lesson ID: FS04.2
Lesson format: Concise theory
Part: 04 — React and the server
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Foundation
Prerequisites: FS04.1
Last reviewed: 2026-08-22

We will represent every request outcome explicitly and stop obsolete work from changing the current screen.

> **Helpful background:** [Fetching data and synchronizing with Effects](/learn/lessons/33-fs04-1-fetching-data-and-effects)

## What we will learn

- distinguish loading, success, empty, and failure states;
- clean up an Effect with `AbortController`;
- explain and prevent a stale-response race.

## One nullable array collapses different facts

With `Issue[] | null`, `null` might mean “not started,” “loading,” or “failed.” An empty array might mean “the server returned no issues” or “we have not loaded yet.” Use a discriminated union so impossible combinations are unrepresentable:

```ts
type IssueRequest =
  | { status: 'loading' }
  | { status: 'success'; issues: readonly Issue[] }
  | { status: 'failure'; message: string };

const [request, setRequest] = useState<IssueRequest>({ status: 'loading' });
```

Rendering can now narrow each case:

```tsx
if (request.status === 'loading') return <p>Loading issues…</p>;
if (request.status === 'failure') return <p role="alert">{request.message}</p>;
if (request.issues.length === 0) return <p>No issues yet.</p>;
return <IssueList issues={request.issues} />;
```

An empty result is a successful answer with zero items, not an error.

## Cleanup ends ownership of a request

An Effect may become obsolete because the component unmounts or a dependency changes. Create an abort signal for the work owned by that Effect:

```tsx
useEffect(() => {
  const controller = new AbortController();

  async function loadIssues() {
    setRequest({ status: 'loading' });
    try {
      const response = await fetch(ISSUES_URL, { signal: controller.signal });
      if (!response.ok) throw new Error(`Request failed with ${response.status}.`);
      const body: unknown = await response.json();
      setRequest({ status: 'success', issues: parseIssues(body) });
    } catch (error: unknown) {
      if (error instanceof DOMException && error.name === 'AbortError') return;
      const message = error instanceof Error ? error.message : 'Could not load issues.';
      setRequest({ status: 'failure', message });
    }
  }

  void loadIssues();
  return () => controller.abort();
}, []);
```

Cleanup runs before a replacement Effect and when the component unmounts. In development Strict Mode, React's setup → cleanup → setup probe aborts the first request. That is evidence the cleanup works, not a reason to remove Strict Mode.

## Races are about relevance, not speed

Imagine `projectId` changes from A to B. Request A starts first but finishes last:

```text
request A starts ───────────────→ A response
      request B starts ─→ B response
```

Without cleanup, A overwrites B even though the screen now represents project B. The problem is not simply that A was slow; its result was no longer relevant. Aborting the old request prevents its result from winning and can also save network work.

Not every asynchronous API supports aborting. A cleanup-local `ignore` boolean is another valid way to refuse stale results. The invariant is the same: work no longer owned by the current Effect must not update current state.

## HTTP failure and network failure differ

A 422 or 500 is an HTTP response: inspect its status and possibly its body. A network failure means no usable response arrived, perhaps because the server stopped or the connection failed. `fetch` rejects for the latter but normally resolves for the former, which is why both the `response.ok` branch and `catch` are needed.

User-facing text may simplify these cases, but diagnostics should preserve useful status or error information. Never label every non-success as a “network error.”

## Try it

**Workspace:** continue in `.dalt/workspace/fs04-react-server` with the fixture running.

**Starting state:** FS04.1 has a nullable issue list and a fetch Effect.

Replace that state and Effect with `IssueRequest` and the abortable version above. Set:

```ts
const ISSUES_URL = 'http://127.0.0.1:8034/api/issues';
```

Render the four branches shown earlier, then run:

```bash
npm run typecheck
npm run dev
```

Observe these cases in DevTools:

1. Reload with the fixture running: loading becomes three issues.
2. Stop the fixture and reload: the alert shows a failure instead of loading forever.
3. Restart the fixture: success returns because the fixture resets.
4. Temporarily change the URL to `/api/nope`: Network shows a 404 response and the UI includes status 404.

Restore the URL. In development Network tools, the first request may appear cancelled because Strict Mode tested cleanup; the final request succeeds.

**Expected result:** each observable situation has one honest UI branch, and an aborted obsolete request never becomes a visible failure.

**Reset:** keep the workspace for FS04.3, or delete `.dalt/workspace/fs04-react-server`.

## What to notice

Cleanup is not merely about suppressing a warning after unmount. It defines which synchronization currently owns permission to update the screen.

## Check your understanding

1. Why is an empty array not a loading state?
2. When does React run Effect cleanup?
3. Why does `fetch` need both an `ok` check and a `catch`?
4. What makes a response stale?

<details><summary>Check your answers</summary>

1. It can be a successful response containing zero records.
2. Before a replacement Effect and when the component unmounts.
3. HTTP error responses normally resolve; transport failures reject.
4. The screen's current inputs no longer match the request that produced it.
</details>

## Next

Reads now stay honest under failure and races; next user events will create, update, and delete server data without pretending success early.

<details><summary>Maintainer source record</summary>

- Source dossier: React documentation research notes; Full Stack Open Part 2 research notes.
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: React Learn, *Synchronizing with Effects* and *You Might Not Need an Effect*; MDN `AbortController`, `fetch`, and `Response.ok`.
- Versions: React 19.2.3; TypeScript 5.9.3.
- Consulted: 2026-08-22.
- Curriculum authority: DALT Fullstack theory curriculum, Batch 5, FS04.2.
- DALT files inspected: Part 04 fixture statuses, CORS behavior, and executable lifecycle test.
- Reused material: loading/empty/failure union, cleanup, AbortError, deliberate failure, and request-race material extracted from former FS04.1.
</details>
