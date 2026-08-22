import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import {
  AuthProvider,
  SessionRoutes,
  type PublicUser,
  type SessionApi,
} from './AuthSession';

function renderSession(api: SessionApi) {
  return render(
    <MemoryRouter initialEntries={['/account']}>
      <AuthProvider api={api}>
        <SessionRoutes />
      </AuthProvider>
    </MemoryRouter>,
  );
}

describe('session-aware navigation', () => {
  it('shows a loading state while the current user is unknown', () => {
    const api: SessionApi = {
      getCurrentUser: () => new Promise<PublicUser | null>(() => undefined),
    };

    renderSession(api);

    expect(screen.getByText('Checking your session…')).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Sign in' })).not.toBeInTheDocument();
  });

  it('redirects an anonymous visitor to sign in', async () => {
    const api: SessionApi = { getCurrentUser: async () => null };

    renderSession(api);

    expect(await screen.findByRole('heading', { name: 'Sign in' })).toBeInTheDocument();
  });

  it('renders the protected page for the current user', async () => {
    const api: SessionApi = {
      getCurrentUser: async () => ({ id: 7, email: 'learner@example.test' }),
    };

    renderSession(api);

    expect(await screen.findByRole('heading', { name: 'Account' })).toBeInTheDocument();
    expect(screen.getByText('Signed in as learner@example.test')).toBeInTheDocument();
  });

  it('shows a visible failure instead of pretending the visitor is signed out', async () => {
    const api: SessionApi = {
      getCurrentUser: async () => { throw new Error('network unavailable'); },
    };

    renderSession(api);

    expect(await screen.findByRole('alert')).toHaveTextContent('Could not check your session.');
    expect(screen.queryByRole('heading', { name: 'Sign in' })).not.toBeInTheDocument();
  });
});
