import { useEffect, useState } from 'react';
import { Navigate, Route, Routes, useParams } from 'react-router';
import {
  AuthProvider,
  RequireAuth,
  type PublicUser,
  type SessionApi,
} from './AuthSession';

export type BoundaryIssue = {
  id: string;
  title: string;
};

export type BoundaryApi = SessionApi & {
  getIssue(id: string, signal: AbortSignal): Promise<BoundaryIssue>;
};

export class BoundaryApiError extends Error {
  constructor(readonly status: number) {
    super(`The API answered ${status}`);
    this.name = 'BoundaryApiError';
  }
}

type IssueState =
  | { status: 'loading' }
  | { status: 'ready'; issue: BoundaryIssue }
  | { status: 'sign-in-required' }
  | { status: 'denied' }
  | { status: 'not-found' }
  | { status: 'failed' };

function IssueBoundaryPage({ api }: { api: BoundaryApi }) {
  const { issueId } = useParams();
  const validId = issueId !== undefined && /^ISS-[0-9]+$/.test(issueId);
  const [state, setState] = useState<IssueState>({ status: 'loading' });

  useEffect(() => {
    if (!validId || issueId === undefined) return;

    const controller = new AbortController();

    api.getIssue(issueId, controller.signal)
      .then((issue) => {
        if (!controller.signal.aborted) setState({ status: 'ready', issue });
      })
      .catch((error: unknown) => {
        if (controller.signal.aborted) return;
        if (error instanceof BoundaryApiError && error.status === 401) {
          setState({ status: 'sign-in-required' });
        } else if (error instanceof BoundaryApiError && error.status === 403) {
          setState({ status: 'denied' });
        } else if (error instanceof BoundaryApiError && error.status === 404) {
          setState({ status: 'not-found' });
        } else {
          setState({ status: 'failed' });
        }
      });

    return () => controller.abort();
  }, [api, issueId, validId]);

  if (!validId || state.status === 'not-found') return <h1>Page not found</h1>;
  if (state.status === 'sign-in-required') return <Navigate to="/login" replace />;
  if (state.status === 'denied') return <h1>Access denied</h1>;
  if (state.status === 'failed') return <p role="alert">Could not load this issue.</p>;
  if (state.status === 'loading') return <p>Loading issue…</p>;

  return <h1>{state.issue.title}</h1>;
}

export function BoundaryApp({ api }: { api: BoundaryApi }) {
  return (
    <AuthProvider api={api}>
      <Routes>
        <Route path="/login" element={<h1>Sign in</h1>} />
        <Route element={<RequireAuth />}>
          <Route path="/issues/:issueId" element={<IssueBoundaryPage api={api} />} />
        </Route>
        <Route path="*" element={<h1>Page not found</h1>} />
      </Routes>
    </AuthProvider>
  );
}

export const signedInUser: PublicUser = {
  id: 7,
  email: 'learner@example.test',
};
