import {
  createContext,
  useContext,
  useEffect,
  useState,
  type ReactNode,
} from 'react';
import { Navigate, Outlet, Route, Routes } from 'react-router';

export type PublicUser = {
  id: number;
  email: string;
};

export type AuthState =
  | { status: 'loading' }
  | { status: 'anonymous' }
  | { status: 'authenticated'; user: PublicUser }
  | { status: 'failed'; message: string };

export type SessionApi = {
  getCurrentUser(signal: AbortSignal): Promise<PublicUser | null>;
};

const AuthContext = createContext<AuthState | null>(null);

export function AuthProvider({
  api,
  children,
}: {
  api: SessionApi;
  children: ReactNode;
}) {
  const [auth, setAuth] = useState<AuthState>({ status: 'loading' });

  useEffect(() => {
    const controller = new AbortController();

    api.getCurrentUser(controller.signal)
      .then((user) => {
        if (controller.signal.aborted) return;
        setAuth(user
          ? { status: 'authenticated', user }
          : { status: 'anonymous' });
      })
      .catch(() => {
        if (controller.signal.aborted) return;
        setAuth({ status: 'failed', message: 'Could not check your session.' });
      });

    return () => controller.abort();
  }, [api]);

  return <AuthContext.Provider value={auth}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthState {
  const auth = useContext(AuthContext);

  if (auth === null) {
    throw new Error('useAuth must be used inside AuthProvider');
  }

  return auth;
}

export function RequireAuth() {
  const auth = useAuth();

  if (auth.status === 'loading') return <p>Checking your session…</p>;
  if (auth.status === 'failed') return <p role="alert">{auth.message}</p>;
  if (auth.status === 'anonymous') return <Navigate to="/login" replace />;

  return <Outlet />;
}

function AccountPage() {
  const auth = useAuth();

  if (auth.status !== 'authenticated') return null;

  return (
    <main>
      <h1>Account</h1>
      <p>Signed in as {auth.user.email}</p>
    </main>
  );
}

export function SessionRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<h1>Sign in</h1>} />
      <Route element={<RequireAuth />}>
        <Route path="/account" element={<AccountPage />} />
      </Route>
      <Route path="*" element={<h1>Page not found</h1>} />
    </Routes>
  );
}
