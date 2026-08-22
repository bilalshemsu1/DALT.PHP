import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError, createFakeServer } from './issueApi';
import type { FakeServer } from './issueApi';
import { IssueListPanel } from './IssueQueries';
import { CreateIssueForm, IssueStatusCard } from './IssueMutations';
import { createTestQueryClient, withQueryClient } from './testQueryClient';

function seeded(): FakeServer {
  return createFakeServer([{ id: 'ISS-1', title: 'Trace a request', status: 'open' }]);
}

function renderBoard(api: FakeServer) {
  return render(
    <>
      <CreateIssueForm api={api} projectId="PRJ-1" />
      <IssueListPanel api={api} projectId="PRJ-1" status="open" staleTime={60_000} />
    </>,
    { wrapper: withQueryClient(createTestQueryClient()) },
  );
}

it('refetches the list the write changed, instead of patching a local array', async () => {
  const user = userEvent.setup();
  const api = seeded();
  renderBoard(api);

  expect(await screen.findByText('Trace a request')).toBeInTheDocument();

  await user.type(screen.getByLabelText('New issue'), 'Name the states');
  await user.click(screen.getByRole('button', { name: 'Create issue' }));

  expect(await screen.findByText('Name the states')).toBeInTheDocument();
  expect(screen.getByLabelText('New issue')).toHaveValue('');
  expect(api.calls).toEqual([
    'listIssues:PRJ-1:open',
    'createIssue:PRJ-1',
    'listIssues:PRJ-1:open',
  ]);
});

it('keeps the draft and the old list when the write fails', async () => {
  const user = userEvent.setup();
  const api = seeded();
  api.failNextWrite(new ApiError(422, 'Title is required'));
  renderBoard(api);

  expect(await screen.findByText('Trace a request')).toBeInTheDocument();

  await user.type(screen.getByLabelText('New issue'), 'Name the states');
  await user.click(screen.getByRole('button', { name: 'Create issue' }));

  expect(await screen.findByRole('alert')).toHaveTextContent('Could not create the issue.');
  expect(screen.getByLabelText('New issue')).toHaveValue('Name the states');
  expect(screen.queryByText('Name the states')).not.toBeInTheDocument();
  expect(api.calls).toEqual(['listIssues:PRJ-1:open', 'createIssue:PRJ-1']);
});

it('disables the submit button while the write is in flight', async () => {
  const user = userEvent.setup();
  const api = seeded();
  const release = api.pauseWrites();
  renderBoard(api);

  await screen.findByText('Trace a request');
  await user.type(screen.getByLabelText('New issue'), 'Name the states');
  await user.click(screen.getByRole('button', { name: 'Create issue' }));

  expect(await screen.findByRole('button', { name: 'Creating…' })).toBeDisabled();

  release();
  expect(await screen.findByText('Name the states')).toBeInTheDocument();
  expect(api.calls.filter((call) => call === 'createIssue:PRJ-1')).toHaveLength(1);
});

it('shows an optimistic status before the server has answered', async () => {
  const user = userEvent.setup();
  const api = seeded();
  const release = api.pauseWrites();

  render(<IssueStatusCard api={api} issueId="ISS-1" />, {
    wrapper: withQueryClient(createTestQueryClient()),
  });

  expect(await screen.findByText('Trace a request — open')).toBeInTheDocument();
  await user.click(screen.getByRole('button', { name: 'Mark closed' }));

  expect(await screen.findByText('Trace a request — closed')).toBeInTheDocument();

  release();
  expect(await screen.findByRole('button', { name: 'Mark open' })).toBeInTheDocument();
});

it('rolls back to the previous value when an optimistic write fails', async () => {
  const user = userEvent.setup();
  const api = seeded();
  api.failNextWrite(new ApiError(403, 'Forbidden'));

  render(<IssueStatusCard api={api} issueId="ISS-1" />, {
    wrapper: withQueryClient(createTestQueryClient()),
  });

  expect(await screen.findByText('Trace a request — open')).toBeInTheDocument();

  // Hold both sides open, so each step is observed rather than inferred.
  const releaseReads = api.pauseReads();
  const releaseWrites = api.pauseWrites();

  await user.click(screen.getByRole('button', { name: 'Mark closed' }));
  expect(await screen.findByText('Trace a request — closed')).toBeInTheDocument();

  // The write fails. With the refetch still blocked, only the rollback can restore 'open'.
  releaseWrites();
  expect(await screen.findByText('Trace a request — open')).toBeInTheDocument();

  // onSettled invalidates either way, so the screen ends on server truth, not a guess.
  releaseReads();
  expect(await screen.findByRole('alert')).toHaveTextContent('Could not change the status.');
  expect(screen.getByText('Trace a request — open')).toBeInTheDocument();
  expect(api.calls.filter((call) => call === 'getIssue:ISS-1')).toHaveLength(2);
});
