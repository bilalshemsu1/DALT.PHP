import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import {
  BoundaryApiError,
  BoundaryApp,
  signedInUser,
  type BoundaryApi,
} from './BoundaryApp';

function fakeApi(overrides: Partial<BoundaryApi> = {}): BoundaryApi {
  return {
    getCurrentUser: async () => signedInUser,
    getIssue: async () => ({ id: 'ISS-41', title: 'Trace a request' }),
    ...overrides,
  };
}

function renderAt(path: string, api: BoundaryApi) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <BoundaryApp api={api} />
    </MemoryRouter>,
  );
}

describe('route, session, and API boundaries', () => {
  it('redirects an anonymous direct visit before loading private data', async () => {
    const getIssue = vi.fn();
    renderAt('/issues/ISS-41', fakeApi({
      getCurrentUser: async () => null,
      getIssue,
    }));

    expect(await screen.findByRole('heading', { name: 'Sign in' })).toBeInTheDocument();
    expect(getIssue).not.toHaveBeenCalled();
  });

  it('renders the requested issue for an authenticated user', async () => {
    renderAt('/issues/ISS-41', fakeApi());

    expect(await screen.findByRole('heading', { name: 'Trace a request' })).toBeInTheDocument();
  });

  it('keeps a forbidden response distinct from login', async () => {
    renderAt('/issues/ISS-41', fakeApi({
      getIssue: async () => { throw new BoundaryApiError(403); },
    }));

    expect(await screen.findByRole('heading', { name: 'Access denied' })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Sign in' })).not.toBeInTheDocument();
  });

  it('recovers to login when a previously valid session has expired', async () => {
    renderAt('/issues/ISS-41', fakeApi({
      getIssue: async () => { throw new BoundaryApiError(401); },
    }));

    expect(await screen.findByRole('heading', { name: 'Sign in' })).toBeInTheDocument();
  });

  it('rejects an invalid route parameter without calling the API', async () => {
    const getIssue = vi.fn();
    renderAt('/issues/not-an-issue', fakeApi({ getIssue }));

    expect(await screen.findByRole('heading', { name: 'Page not found' })).toBeInTheDocument();
    expect(getIssue).not.toHaveBeenCalled();
  });
});
