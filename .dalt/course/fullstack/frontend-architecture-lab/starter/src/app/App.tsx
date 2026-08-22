import { MemoryRouter } from 'react-router';
import { useIssueFilters } from '../features/issues';

function IssueSummary() {
  const filters = useIssueFilters();

  return <p>Showing {filters.status} issues, page {filters.page}</p>;
}

export function App() {
  return (
    <MemoryRouter initialEntries={['/issues?status=open']}>
      <IssueSummary />
    </MemoryRouter>
  );
}
