import { MemoryRouter } from 'react-router';
import { useIssueFilters } from '../features/issues';
import { formatIssueLabel } from '../shared/formatIssueLabel';

function IssueSummary() {
  const filters = useIssueFilters();

  return <p>{formatIssueLabel({ id: 'ISS-1', title: 'Trace a request', status: filters.status })}</p>;
}

export function App() {
  return (
    <MemoryRouter initialEntries={['/issues?status=open']}>
      <IssueSummary />
    </MemoryRouter>
  );
}
