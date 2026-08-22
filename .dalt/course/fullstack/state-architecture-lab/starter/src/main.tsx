import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { MemoryRouter } from 'react-router';
import { createFakeServer } from './issueApi';
import { IssueBoard } from './StateAudit';

const api = createFakeServer([{ id: 'ISS-1', title: 'Trace a request', status: 'open' }]);

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <MemoryRouter initialEntries={['/projects/PRJ-1']}>
      <IssueBoard api={api} projectId="PRJ-1" />
    </MemoryRouter>
  </StrictMode>,
);
