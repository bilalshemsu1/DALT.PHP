import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router';
import { createFakeServer } from './issueApi';
import type { FakeServer } from './issueApi';
import { IssueBoard, IssueStatusReader } from './StateAudit';

function seeded(): FakeServer {
  return createFakeServer([
    { id: 'ISS-1', title: 'Trace a request', status: 'open' },
    { id: 'ISS-2', title: 'Name the states', status: 'open' },
    { id: 'ISS-3', title: 'Ship the audit', status: 'closed' },
  ]);
}

function CurrentUrl() {
  const location = useLocation();

  return <output>{location.pathname + location.search}</output>;
}

function renderAt(path: string, api: FakeServer) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <CurrentUrl />
      <Routes>
        <Route path="/projects/:projectId" element={<IssueBoard api={api} projectId="PRJ-1" />} />
      </Routes>
    </MemoryRouter>,
  );
}

it('keeps the filter in the URL, where it can be shared and restored', async () => {
  const user = userEvent.setup();
  renderAt('/projects/PRJ-1?status=closed', seeded());

  expect(await screen.findByText('Ship the audit — closed')).toBeInTheDocument();

  await user.click(screen.getByRole('button', { name: 'Show open' }));

  expect(screen.getByRole('status')).toHaveTextContent('/projects/PRJ-1?status=open');
  expect(await screen.findByText('Trace a request — open')).toBeInTheDocument();
});

it('keeps a draft local: it is private to one board and does not survive unmounting', async () => {
  const user = userEvent.setup();
  const api = seeded();
  const first = renderAt('/projects/PRJ-1', api);
  await screen.findByText('Trace a request — open');

  await user.type(screen.getByLabelText('New issue'), 'Half-written');
  expect(screen.getByLabelText('New issue')).toHaveValue('Half-written');

  first.unmount();
  renderAt('/projects/PRJ-1', api);
  await screen.findByText('Trace a request — open');

  expect(screen.getByLabelText('New issue')).toHaveValue('');
});

it('shows a stored count drifting from the list it was computed from', async () => {
  const user = userEvent.setup();
  renderAt('/projects/PRJ-1', seeded());

  expect(await screen.findByText('Derived open: 2')).toBeInTheDocument();
  expect(screen.getByText('Stored open: 2')).toBeInTheDocument();

  await user.click(screen.getByRole('button', { name: 'Close Trace a request' }));

  expect(await screen.findByText('Derived open: 1')).toBeInTheDocument();
  expect(screen.getByText('Stored open: 2')).toBeInTheDocument();
});

it('shows two private copies of one server fact disagreeing after a write', async () => {
  const user = userEvent.setup();
  const api = seeded();

  render(
    <>
      <IssueStatusReader api={api} issueId="ISS-1" label="Sidebar" />
      <IssueStatusReader api={api} issueId="ISS-1" label="Detail" />
    </>,
  );

  expect(await screen.findByText(/Sidebar: open/)).toBeInTheDocument();
  expect(screen.getByText(/Detail: open/)).toBeInTheDocument();

  await user.click(screen.getByRole('button', { name: 'Close from Detail' }));

  expect(await screen.findByText(/Detail: closed/)).toBeInTheDocument();
  expect(screen.getByText(/Sidebar: open/)).toBeInTheDocument();
  expect(api.calls.filter((call) => call === 'getIssue:ISS-1')).toHaveLength(2);
});
