The issue workflow now crosses React state, client routes, form encoding, JSON responses,
and DALT controllers. Clicking through it is useful while building, but it is too easy to
miss a regression later. We will protect what a person can see and do, while keeping the
backend outside this test boundary.

## Test behavior at the browser boundary

React Testing Library works best when a test finds controls the way a person or assistive
technology would: by role, label, and visible name. Mock Service Worker (MSW) intercepts
the same `fetch` requests the application already makes. The component does not receive a
special test-only API.

Install MSW beside the existing Vitest and Testing Library tools:

```bash
npm install --save-dev msw@2.15.0
```

Open `vite.config.mjs` and give jsdom an absolute page URL:

```js
test: {
  environment: 'jsdom',
  environmentOptions: {
    jsdom: { url: 'http://localhost:8000' }
  },
  globals: true,
  passWithNoTests: true,
  setupFiles: [resolve(__dirname, 'resources/setup-tests.ts')],
  include: ['resources/**/*.{test,spec}.{ts,tsx,js,jsx}']
},
```

Our application calls paths such as `/api/workspaces/1/projects/2/issues`. The absolute
jsdom URL lets the browser-like environment resolve those paths consistently.

## Start one mock server for the test run

Create `resources/app/test/server.ts`:

```ts
import { setupServer } from 'msw/node'

export const server = setupServer()
```

There are no global response handlers. Each test declares the network conversation it
needs, so a reader can see the expected request beside the expected screen behavior.

Open `resources/setup-tests.ts`. Keep the existing `jest-dom` import, then add:

```ts
import { afterAll, afterEach, beforeAll } from 'vitest'
import { server } from './app/test/server'

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => server.resetHandlers())
afterAll(() => server.close())
```

An unhandled request fails loudly. Resetting after every test prevents one scenario's
responses from leaking into another.

## Render the real route tree in memory

Create `resources/app/issue-workflow.test.tsx`. Start with the tools, application screens,
and representative server data:

```tsx
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { AppLayout } from './AppLayout'
import { DeleteIssuePage } from './DeleteIssuePage'
import { EditIssuePage } from './EditIssuePage'
import { IssuePage } from './IssuePage'
import { ProjectPage } from './ProjectPage'
import { server } from './test/server'
import type { Issue, ProjectPageData } from './project-page-data'

const data: ProjectPageData = {
  workspace: { id: 1, name: 'Studio' },
  project: { id: 2, name: 'Launch' },
  form: { csrfToken: 'test-token' },
}

const issue: Issue = {
  id: 3,
  title: 'Write release notes',
  description: 'Explain the visible changes.',
  status: 'open',
}
```

Add a helper that mirrors `main.tsx`, but starts at a chosen URL without opening a real
browser:

```tsx
function renderAt(path: string) {
  const router = createMemoryRouter([
    {
      path: '/workspaces/:workspaceId/projects/:projectId',
      element: <AppLayout data={data} />,
      children: [
        { index: true, element: <ProjectPage data={data} /> },
        { path: 'issues/:issueId', element: <IssuePage data={data} /> },
        { path: 'issues/:issueId/edit', element: <EditIssuePage data={data} /> },
        { path: 'issues/:issueId/delete', element: <DeleteIssuePage data={data} /> },
      ],
    },
  ], { initialEntries: [path] })

  return {
    router,
    user: userEvent.setup(),
    ...render(<RouterProvider router={router} />),
  }
}
```

Import `RouterProvider` from the same `react-router` runtime as `createMemoryRouter` in
this memory test. The production entry can use its DOM package; this isolated router and
its provider must share one context.

## Prove loading and creation

First prove that loading ends with a server issue on screen:

```tsx
test('loads the project collection and renders server issues', async () => {
  server.use(
    http.get('/api/workspaces/1/projects/2/issues', () =>
      HttpResponse.json({ issues: [issue] })),
  )

  renderAt('/workspaces/1/projects/2')

  expect(screen.getByRole('status')).toHaveTextContent('Loading issues')
  expect(await screen.findByRole('link', { name: /Write release notes/i }))
    .toBeInTheDocument()
  expect(screen.getByText('Explain the visible changes.')).toBeInTheDocument()
})
```

Then make the create handler reject the first request and accept the second:

```tsx
test('keeps creation values on validation and adds only the confirmed issue', async () => {
  let attempts = 0
  server.use(
    http.get('/api/workspaces/1/projects/2/issues', () =>
      HttpResponse.json({ issues: [] })),
    http.post('/api/workspaces/1/projects/2/issues', async ({ request }) => {
      attempts += 1
      const body = await request.formData()
      expect(body.get('_token')).toBe('test-token')

      if (attempts === 1) {
        return HttpResponse.json(
          { errors: { title: 'Use between 2 and 100 characters.' } },
          { status: 422 },
        )
      }

      return HttpResponse.json(
        { issue: { ...issue, title: String(body.get('title')) }, message: 'Created.' },
        { status: 201 },
      )
    }),
  )

  const { user } = renderAt('/workspaces/1/projects/2')
  const title = screen.getByRole('textbox', { name: 'Title' })
  await user.type(title, 'x')
  await user.click(screen.getByRole('button', { name: 'Create issue' }))

  expect(await screen.findByRole('alert'))
    .toHaveTextContent('Use between 2 and 100 characters.')
  expect(title).toHaveValue('x')

  await user.clear(title)
  await user.type(title, 'Confirmed issue')
  await user.click(screen.getByRole('button', { name: 'Create issue' }))

  expect(await screen.findByRole('link', { name: /Confirmed issue/i }))
    .toBeInTheDocument()
  expect(title).toHaveValue('')
})
```

The important assertion is not that a state setter ran. It is that rejected input stays
editable and only the issue confirmed by DALT appears.

## Prove the routed mutations

Use the same pattern for status. Load the issue, click its visible action, and return the
new server representation:

```tsx
test('changes status from the detail using the confirmed response', async () => {
  server.use(
    http.get('/api/workspaces/1/projects/2/issues/3', () =>
      HttpResponse.json({ issue })),
    http.post('/api/workspaces/1/projects/2/issues/3/status', () =>
      HttpResponse.json({
        issue: { ...issue, status: 'closed' },
        message: 'Issue was closed.',
      })),
  )

  const { user } = renderAt('/workspaces/1/projects/2/issues/3')
  await screen.findByRole('heading', { name: 'Write release notes' })
  await user.click(screen.getByRole('button', { name: 'Close issue' }))

  expect(await screen.findByText('Closed')).toBeInTheDocument()
  expect(screen.getByRole('button', { name: 'Reopen issue' })).toBeEnabled()
  expect(screen.getByRole('status')).toHaveTextContent('Issue was closed.')
})
```

For editing, let the first response return 422, prove the attempted title remains, then
prove success changes the current route:

```tsx
const { user, router } = renderAt('/workspaces/1/projects/2/issues/3/edit')
const title = await screen.findByRole('textbox', { name: 'Title' })
await user.clear(title)
await user.type(title, 'x')
await user.click(screen.getByRole('button', { name: 'Save changes' }))
expect(await screen.findByRole('alert')).toHaveTextContent('Use between 2 and 100 characters.')
expect(title).toHaveValue('x')

await user.clear(title)
await user.type(title, 'Updated title')
await user.click(screen.getByRole('button', { name: 'Save changes' }))
await waitFor(() =>
  expect(router.state.location.pathname)
    .toBe('/workspaces/1/projects/2/issues/3'))
```

Give that test two MSW POST responses just as the creation test does. The successful JSON
contains the edited issue and `message: 'Updated.'`.

Finally, prove deletion begins on its review screen, sends the method override, and ends
at the project:

```tsx
test('deletes only after review and returns to the project', async () => {
  server.use(
    http.get('/api/workspaces/1/projects/2/issues/3', () =>
      HttpResponse.json({ issue })),
    http.post('/api/workspaces/1/projects/2/issues/3', async ({ request }) => {
      const body = await request.formData()
      expect(body.get('_method')).toBe('DELETE')
      return HttpResponse.json({ message: 'Write release notes was deleted.' })
    }),
    http.get('/api/workspaces/1/projects/2/issues', () =>
      HttpResponse.json({ issues: [] })),
  )

  const { user, router } = renderAt('/workspaces/1/projects/2/issues/3/delete')
  expect(await screen.findByRole('heading', { name: 'Delete this issue?' }))
    .toBeInTheDocument()
  expect(screen.getByText('This cannot be undone')).toBeInTheDocument()

  await user.click(screen.getByRole('button', { name: 'Delete issue' }))
  await waitFor(() =>
    expect(router.state.location.pathname).toBe('/workspaces/1/projects/2'))
  expect(await screen.findByRole('status'))
    .toHaveTextContent('Write release notes was deleted.')
})
```

These are fast browser-boundary tests. MSW proves request shape and response handling, but
it does not prove that a PHP route writes the database. That becomes our next boundary.

## Run the protected workflow

Run every application check:

```bash
npm test
npm run typecheck
npm run lint
npm run build
```

Vitest should report one file and five passing tests. To see the test protect you, briefly
change the delete method override to `PATCH` and run `npm test`: the deletion test must
fail. Restore `DELETE` and confirm green again.

If Git is available, save this browser-boundary checkpoint:

```bash
git add package.json package-lock.json vite.config.mjs \
  resources/setup-tests.ts resources/app/test/server.ts \
  resources/app/issue-workflow.test.tsx
git commit -m "Test the React issue workflow"
```

The React workflow is now protected from the loading screen through deletion. Next we
will cross the boundary and exercise the real DALT controllers against a real test
database.
