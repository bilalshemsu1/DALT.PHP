import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, useLocation } from 'react-router';
import { useIssueEvents, useIssueFilters, useIssueSelection } from './features/issues';
import type { Connect } from './features/issues';

function CurrentUrl() {
  const location = useLocation();

  return <output>{location.pathname + location.search}</output>;
}

function FiltersProbe() {
  const filters = useIssueFilters();

  return (
    <div>
      <p>
        status={filters.status} q={filters.query || '-'} page={filters.page}
      </p>
      <button onClick={() => filters.setPage(4)}>Page 4</button>
      <button onClick={() => filters.setQuery('timeout')}>Search timeout</button>
      <button onClick={() => filters.setStatus('closed')}>Show closed</button>
    </div>
  );
}

function SelectionProbe({ label }: { label: string }) {
  const selection = useIssueSelection();

  return (
    <div>
      <p>
        {label}: {selection.selected.join(',') || 'none'}
      </p>
      <button onClick={() => selection.toggle('ISS-1')}>Toggle ISS-1 in {label}</button>
    </div>
  );
}

function EventsProbe({ connect, issueId }: { connect: Connect; issueId: string }) {
  const latest = useIssueEvents(connect, issueId);

  return <p>latest: {latest ?? 'none'}</p>;
}

it('reads filters from the URL and writes every change back to it', async () => {
  const user = userEvent.setup();

  render(
    <MemoryRouter initialEntries={['/issues?status=sideways&page=0']}>
      <CurrentUrl />
      <FiltersProbe />
    </MemoryRouter>,
  );

  // An unknown status and a zero page are untrusted input, not application state.
  expect(screen.getByText('status=open q=- page=1')).toBeInTheDocument();

  await user.click(screen.getByRole('button', { name: 'Page 4' }));
  expect(screen.getByRole('status')).toHaveTextContent('/issues?status=open&page=4');
  expect(screen.getByText('status=open q=- page=4')).toBeInTheDocument();
});

it('keeps the "narrowing returns to page one" rule inside the hook', async () => {
  const user = userEvent.setup();

  render(
    <MemoryRouter initialEntries={['/issues?status=open&page=4']}>
      <FiltersProbe />
    </MemoryRouter>,
  );

  await user.click(screen.getByRole('button', { name: 'Search timeout' }));
  expect(screen.getByText('status=open q=timeout page=1')).toBeInTheDocument();

  await user.click(screen.getByRole('button', { name: 'Page 4' }));
  await user.click(screen.getByRole('button', { name: 'Show closed' }));
  expect(screen.getByText('status=closed q=timeout page=1')).toBeInTheDocument();
});

it('gives each caller its own state, because a hook shares behavior and not values', async () => {
  const user = userEvent.setup();

  render(
    <>
      <SelectionProbe label="Board" />
      <SelectionProbe label="Sidebar" />
    </>,
  );

  await user.click(screen.getByRole('button', { name: 'Toggle ISS-1 in Board' }));

  expect(screen.getByText('Board: ISS-1')).toBeInTheDocument();
  expect(screen.getByText('Sidebar: none')).toBeInTheDocument();
});

it('stops whatever it started, on unmount and on a changed input', () => {
  const log: string[] = [];
  const connect: Connect = (issueId, onEvent) => {
    log.push(`connect:${issueId}`);
    onEvent(`${issueId} updated`);

    return () => log.push(`disconnect:${issueId}`);
  };

  const view = render(<EventsProbe connect={connect} issueId="ISS-1" />);
  expect(screen.getByText('latest: ISS-1 updated')).toBeInTheDocument();

  view.rerender(<EventsProbe connect={connect} issueId="ISS-2" />);
  expect(screen.getByText('latest: ISS-2 updated')).toBeInTheDocument();

  view.unmount();

  expect(log).toEqual([
    'connect:ISS-1',
    'disconnect:ISS-1',
    'connect:ISS-2',
    'disconnect:ISS-2',
  ]);
});
