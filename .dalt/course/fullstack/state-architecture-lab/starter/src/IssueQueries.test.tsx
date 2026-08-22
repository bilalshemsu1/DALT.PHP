import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError, createFakeServer } from './issueApi';
import type { FakeServer } from './issueApi';
import { IssueListPanel, IssueTitle, OptionalIssueTitle } from './IssueQueries';
import { createTestQueryClient, withQueryClient } from './testQueryClient';

function seeded(): FakeServer {
  return createFakeServer([
    { id: 'ISS-1', title: 'Trace a request', status: 'open' },
    { id: 'ISS-2', title: 'Name the states', status: 'open' },
    { id: 'ISS-3', title: 'Ship the audit', status: 'closed' },
  ]);
}

it('gives one remote fact one address, so two readers share one request', async () => {
  const api = seeded();

  render(
    <>
      <IssueTitle api={api} issueId="ISS-1" label="Sidebar" />
      <IssueTitle api={api} issueId="ISS-1" label="Detail" />
    </>,
    { wrapper: withQueryClient(createTestQueryClient()) },
  );

  expect(await screen.findByText('Sidebar: Trace a request')).toBeInTheDocument();
  expect(screen.getByText('Detail: Trace a request')).toBeInTheDocument();
  expect(api.calls.filter((call) => call === 'getIssue:ISS-1')).toHaveLength(1);
});

it('treats a different key as a different fact, and reuses a fresh snapshot', async () => {
  const api = seeded();
  const client = createTestQueryClient();
  const wrapper = withQueryClient(client);

  const open = render(<IssueListPanel api={api} projectId="PRJ-1" status="open" staleTime={60_000} />, {
    wrapper,
  });
  expect(await screen.findByText('Trace a request')).toBeInTheDocument();

  open.unmount();
  render(<IssueListPanel api={api} projectId="PRJ-1" status="closed" staleTime={60_000} />, { wrapper });
  expect(await screen.findByText('Ship the audit')).toBeInTheDocument();

  expect(api.calls).toEqual(['listIssues:PRJ-1:open', 'listIssues:PRJ-1:closed']);

  // Back to the first address, still inside its staleTime: the snapshot is reused.
  render(<IssueListPanel api={api} projectId="PRJ-1" status="open" staleTime={60_000} />, { wrapper });
  expect(await screen.findByText('Trace a request')).toBeInTheDocument();
  expect(api.calls).toEqual(['listIssues:PRJ-1:open', 'listIssues:PRJ-1:closed']);
});

it('never requests an address it does not have', async () => {
  const api = seeded();

  render(<OptionalIssueTitle api={api} issueId="not-an-issue" />, {
    wrapper: withQueryClient(createTestQueryClient()),
  });

  expect(await screen.findByText(/title=-/)).toBeInTheDocument();
  expect(api.calls).toEqual([]);

  // A disabled query is pending — it has no data — but it is not loading.
  expect(screen.getByText(/pending=true loading=false fetching=false/)).toBeInTheDocument();
});

it('offers a retry when the first load fails with nothing to show', async () => {
  const user = userEvent.setup();
  const api = seeded();
  api.failReads(new ApiError(503, 'Service unavailable'));

  render(<IssueListPanel api={api} projectId="PRJ-1" status="open" />, {
    wrapper: withQueryClient(createTestQueryClient()),
  });

  expect(await screen.findByRole('alert')).toHaveTextContent('Could not load issues.');

  api.failReads(null);
  await user.click(screen.getByRole('button', { name: 'Try again' }));

  expect(await screen.findByText('Trace a request')).toBeInTheDocument();
  expect(screen.queryByRole('alert')).not.toBeInTheDocument();
});

it('keeps the last known list on screen when a refresh fails', async () => {
  const user = userEvent.setup();
  const api = seeded();

  render(<IssueListPanel api={api} projectId="PRJ-1" status="open" />, {
    wrapper: withQueryClient(createTestQueryClient()),
  });
  expect(await screen.findByText('Trace a request')).toBeInTheDocument();

  api.failReads(new ApiError(503, 'Service unavailable'));
  await user.click(screen.getByRole('button', { name: 'Refresh' }));

  expect(await screen.findByRole('alert')).toHaveTextContent('could not refresh');
  expect(screen.getByText('Trace a request')).toBeInTheDocument();
});
