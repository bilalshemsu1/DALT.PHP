import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { MemoryRouter } from 'react-router';
import { createFakeServer } from './issueApi';
import { IssueListPanel } from './IssueQueries';
import { IssueBoard } from './StateAudit';

// One client for the whole application, created once outside render.
const queryClient = new QueryClient();

const api = createFakeServer([{ id: 'ISS-1', title: 'Trace a request', status: 'open' }]);

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={['/projects/PRJ-1']}>
        <IssueBoard api={api} projectId="PRJ-1" />
      </MemoryRouter>
      <IssueListPanel api={api} projectId="PRJ-1" status="open" staleTime={30_000} />
    </QueryClientProvider>
  </StrictMode>,
);
