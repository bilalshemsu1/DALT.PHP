# FS07.3 — Test components through user behavior

Lesson ID: FS07.3
Lesson format: Concise theory
Part: 07 — Routed and tested frontend
Status: Published
Estimated effort: 40–50 minutes
Difficulty: Integration
Prerequisites: FS07.2
Last reviewed: 2026-08-23

We will test a real React screen through the controls, messages, and results a person can observe—without starting DALT or intercepting the network.

> **Helpful background:** [Current-user state and protected navigation](/learn/lessons/43-fs07-2-authentication-in-the-frontend)

## What we will learn

- query rendered HTML by role, label, and visible text;
- drive forms with `user-event` and await observable outcomes;
- replace a real API client with a typed fake at an intentional seam.

## Test the promise, not the component's machinery

A useful component test describes what a user can do and see:

```text
fake API result → real component → user action → visible outcome
```

It does not assert that `useState` holds a particular array or that an internal function ran. Those details can change while behavior stays correct. Conversely, a test that only says “the component rendered” can stay green while the empty state, validation, or submit flow is broken.

The shared lab uses Vitest as the runner, jsdom as its browser-like document, React Testing Library for rendering and queries, `user-event` for interaction, and jest-dom for readable DOM assertions.

## Query the interface by meaning

Testing Library's preferred queries follow the way people and assistive technology perceive a page:

```tsx
screen.getByRole('button', { name: /create issue/i });
screen.getByLabelText(/title/i);
screen.getByText(/no issues yet/i);
```

A role query fails if a real `<button>` becomes a clickable `<div>`. A label query fails if the label and input lose their relationship. Those are product regressions that `getByTestId('submit')` would miss.

Choose the query family by timing and certainty:

```tsx
getByRole(...)    // must exist now; throws if absent
queryByRole(...)  // may be absent; returns null
findByRole(...)   // should appear asynchronously; returns a Promise
```

Use `queryBy...` for absence. Use `findBy...` after an API promise or interaction instead of sleeping for an arbitrary duration.

## Drive the page like a person

Create one user controller per test, then await every interaction:

```tsx
const user = userEvent.setup();

await user.type(screen.getByLabelText(/title/i), 'Login form loses focus');
await user.selectOptions(screen.getByLabelText(/priority/i), 'low');
await user.click(screen.getByRole('button', { name: /create issue/i }));

expect(await screen.findAllByRole('listitem')).toHaveLength(2);
```

`user-event` performs a realistic sequence and respects whether a control is disabled. Its methods are asynchronous; forgetting `await` lets the assertion race React's update.

## Put the network behind a seam

`ProjectPage` needs an `IssueApi`, not fetch itself. Context makes one client available to a subtree:

```tsx
const ApiContext = createContext<IssueApi>(issueApi);

export const useIssueApi = (): IssueApi => useContext(ApiContext);

export function ApiProvider({ api, children }: Props) {
  return <ApiContext.Provider value={api}>{children}</ApiContext.Provider>;
}
```

Production uses the real default. A test supplies a plain fake object:

```tsx
function fakeApi(overrides: Partial<IssueApi> = {}): IssueApi {
  return {
    listIssues: async () => [seedIssue],
    createIssue: async () => {
      throw new Error('createIssue was not expected in this test');
    },
    ...overrides,
  };
}

render(
  <ApiProvider api={fakeApi({ listIssues: async () => [] })}>
    <ProjectPage projectId="PRJ-1" />
  </ApiProvider>,
);

expect(await screen.findByText(/no issues yet/i)).toBeInTheDocument();
```

Typing the fake as `IssueApi` keeps it aligned with the real boundary. Throwing from unused methods exposes surprising calls immediately. No server or mocking library is required.

## Assert calls only when the request is the contract

Usually the visible result is stronger than an internal call assertion. Sometimes the sent data matters too:

```tsx
const createIssue = vi.fn(async (draft: IssueDraft): Promise<Issue> => ({
  ...draft,
  id: 'ISS-2',
  status: 'todo',
}));

expect(createIssue).toHaveBeenCalledWith({
  title: 'Login form loses focus',
  priority: 'low',
  projectId: 'PRJ-1',
});
```

This proves the draft crosses the client boundary with the selected priority and without a client-invented ID. The accompanying list assertion proves the user sees the returned issue.

## Failure and empty are different behavior

An empty successful response should show guidance; a failed request should show an alert. The tests must preserve that distinction:

```tsx
renderPage(fakeApi({
  listIssues: async () => {
    throw new IssueApiError('Could not reach the server', 'network');
  },
}));

expect(await screen.findByRole('alert'))
  .toHaveTextContent('Could not reach the server');
expect(screen.queryByText(/no issues yet/i)).not.toBeInTheDocument();
```

For an absence after asynchronous work, wait for a positive outcome first. An immediate “Delete is absent” assertion may pass only because the issue has not loaded yet.

## Try it

**Workspace:** continue in the shared Batch 9 lab, or copy a clean starter:

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/frontend-testing-lab/starter \
  .dalt/workspace/fs07-frontend-testing
cd .dalt/workspace/fs07-frontend-testing
npm ci
```

**Starting state:** six tests wrap `ProjectPage` in `ApiProvider`, but `ProjectPage.tsx` ignores that seam and imports `issueApi` directly. TypeScript cannot reject the wrong choice between two values with the same type.

```bash
npm run typecheck
npm run test:components
```

**Expected result:** type checking passes, while all six component tests fail with `Failed to parse URL` because jsdom receives a real relative fetch. Replace the two lines marked `STAGE 1` with:

```tsx
import { useIssueApi } from './ApiContext';

const api = useIssueApi();
```

Now run:

```bash
npm run test:components
npm test
```

The focused six tests pass, then all eighteen Part 07 tests pass. Temporarily remove `.trim()` from title validation and confirm the whitespace-title test becomes red; wiring the seam while weakening behavior is not a genuine fix.

**Reset:** restore the deliberate `STAGE 1` lines to repeat the exercise, or keep the working workspace for FS07.4.

## What to notice

The initial failure is not a type error or server failure. It reveals an architectural dependency the component hid. Once the screen asks for its client through the seam, typed fakes make loading, empty, failure, validation, and successful mutation behavior deterministic.

## Check your understanding

1. Why is `getByRole` usually stronger than `getByTestId`?
2. When should a test use `findBy...`?
3. Why should unused fake methods throw?
4. What does a hidden Delete button fail to prove?

<details><summary>Check your answers</summary>

1. It depends on semantic role and accessible name, so it detects broken interactive markup that a private test marker ignores.
2. When the expected element appears after asynchronous work such as an API promise or user interaction.
3. An unexpected dependency call fails at its source instead of returning `undefined` and producing a confusing later error.
4. It does not prove the server refuses a forged deletion request; that requires a backend behavior test.
</details>

## Next

Next we will combine routes, session outcomes, and API statuses in a small set of boundary tests.

<details><summary>Maintainer source record</summary>

- Source dossier: Full Stack Open Part 5 research notes.
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: React Testing Library introduction and query priority; Testing Library `user-event` introduction; Vitest mock functions.
- Versions: Vitest 4.0.18; React Testing Library 16.3.2; `user-event` 14.6.1; jest-dom 6.9.1; jsdom 27.0.1; React 19.2.3.
- Consulted: 2026-08-23.
- Curriculum authority: DALT Fullstack theory curriculum, Batch 9, FS07.3.
- DALT files inspected: the complete frontend-testing lab starter, its Vite/Vitest configuration, and `FullstackLabExecutionTest.php`.
- Reused material: semantic query priority, awaited user interaction, typed API fakes, observable outcomes, and the deliberate API-seam defect from the former FS07.3.
- Extracted material: route, session, and API-boundary integration testing now belongs to FS07.4.
</details>
