import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {
  ContextDensity,
  ContextSidebar,
  renderCounts,
  StoreDensity,
  StoreSidebar,
  StoreSummary,
  WorkspaceProvider,
} from './ClientStore';
import { filterReducer, initialFilterState } from './filterReducer';
import { resetWorkspaceStore, useWorkspaceStore } from './workspaceStore';

beforeEach(() => {
  renderCounts.clear();
  resetWorkspaceStore();
});

it('tests a reducer as the pure function it is, with no React involved', () => {
  const searched = filterReducer(
    { ...initialFilterState, page: 4 },
    { type: 'query-changed', query: 'timeout' },
  );

  expect(searched).toEqual({ status: 'open', query: 'timeout', page: 1 });

  const paged = filterReducer(searched, { type: 'page-changed', page: 3 });
  expect(paged).toEqual({ status: 'open', query: 'timeout', page: 3 });

  // The transition is a value, so the previous state is untouched.
  expect(searched.page).toBe(1);
});

it('re-renders every Context consumer when any part of the value changes', async () => {
  const user = userEvent.setup();

  render(
    <WorkspaceProvider>
      <ContextDensity />
      <ContextSidebar />
    </WorkspaceProvider>,
  );

  expect(renderCounts.get('context-density')).toBe(1);
  expect(renderCounts.get('context-sidebar')).toBe(1);

  await user.click(screen.getByRole('button', { name: 'Toggle context sidebar' }));

  expect(screen.getByText('context sidebar: closed')).toBeInTheDocument();
  expect(renderCounts.get('context-sidebar')).toBe(2);
  // The density consumer never reads sidebarOpen and still re-rendered.
  expect(renderCounts.get('context-density')).toBe(2);
});

it('re-renders only the store subscriber whose slice changed', async () => {
  const user = userEvent.setup();

  render(
    <>
      <StoreDensity />
      <StoreSidebar />
      <StoreSummary />
    </>,
  );

  expect(renderCounts.get('store-density')).toBe(1);
  expect(renderCounts.get('store-sidebar')).toBe(1);

  await user.click(screen.getByRole('button', { name: 'Toggle store sidebar' }));

  expect(screen.getByText('store sidebar: closed')).toBeInTheDocument();
  expect(renderCounts.get('store-sidebar')).toBe(2);
  expect(renderCounts.get('store-density')).toBe(1);
  // useShallow keeps a freshly built object from counting as a change.
  expect(renderCounts.get('store-summary')).toBe(1);
});

it('keeps module-level store state until it is explicitly reset', () => {
  useWorkspaceStore.getState().setDensity('compact');
  expect(useWorkspaceStore.getState().density).toBe('compact');

  const { unmount } = render(<StoreDensity />);
  expect(screen.getByText('store density: compact')).toBeInTheDocument();
  unmount();

  // Unmounting a component does not empty a store that lives in a module.
  expect(useWorkspaceStore.getState().density).toBe('compact');

  resetWorkspaceStore();
  expect(useWorkspaceStore.getState().density).toBe('comfortable');
});
