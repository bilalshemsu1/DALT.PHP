import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router';
import { RouteDemo } from './RouteDemo';

function renderAt(path: string) {
  return render(<MemoryRouter initialEntries={[path]}><RouteDemo /></MemoryRouter>);
}

describe('RouteDemo', () => {
  it('renders nested workspace and project routes with a URL filter', () => {
    renderAt('/workspaces/10/projects/20?status=todo');

    expect(screen.getByRole('heading', { name: 'Workspace 10' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Project 20' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Trace a request' })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Protect a mutation' })).not.toBeInTheDocument();
  });

  it('navigates through a real link to issue detail', async () => {
    const user = userEvent.setup();
    renderAt('/workspaces/10/projects/20');

    await user.click(screen.getByRole('link', { name: 'Trace a request' }));

    expect(screen.getByRole('heading', { name: 'Trace a request' })).toBeInTheDocument();
  });

  it('falls back safely for an unknown search value', () => {
    renderAt('/workspaces/10/projects/20?status=not-real');

    expect(screen.getByText('Filter: all')).toBeInTheDocument();
    expect(screen.getAllByRole('listitem')).toHaveLength(2);
  });

  it('renders an intentional not-found page for an invalid issue id', () => {
    renderAt('/issues/not-an-issue');

    expect(screen.getByRole('heading', { name: 'Page not found' })).toBeInTheDocument();
  });
});
