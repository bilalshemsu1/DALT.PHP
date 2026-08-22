import { Link, Outlet, Route, Routes, useParams, useSearchParams } from 'react-router';

const issues = [
  { id: 'ISS-41', title: 'Trace a request', status: 'todo' },
  { id: 'ISS-42', title: 'Protect a mutation', status: 'done' },
] as const;

const allowedStatuses = ['todo', 'done'] as const;
type StatusFilter = (typeof allowedStatuses)[number] | 'all';

function readStatus(value: string | null): StatusFilter {
  return (allowedStatuses as readonly string[]).includes(value ?? '')
    ? (value as StatusFilter)
    : 'all';
}

function WorkspaceLayout() {
  const { workspaceId } = useParams();

  return (
    <main>
      <h1>Workspace {workspaceId}</h1>
      <nav aria-label="Workspace"><Link to="projects/20">Issue tracker</Link></nav>
      <Outlet />
    </main>
  );
}

function ProjectIssues() {
  const { projectId } = useParams();
  const [search] = useSearchParams();
  const status = readStatus(search.get('status'));
  const visible = status === 'all' ? issues : issues.filter((issue) => issue.status === status);

  return (
    <section>
      <h2>Project {projectId}</h2>
      <p>Filter: {status}</p>
      <ul>
        {visible.map((issue) => (
          <li key={issue.id}><Link to={`/issues/${issue.id}`}>{issue.title}</Link></li>
        ))}
      </ul>
    </section>
  );
}

function IssuePage() {
  const { issueId } = useParams();

  if (issueId === undefined || !/^ISS-[0-9]+$/.test(issueId)) {
    return <NotFoundPage />;
  }

  const issue = issues.find((candidate) => candidate.id === issueId);
  return issue === undefined
    ? <NotFoundPage />
    : <main><h1>{issue.title}</h1><p>{issue.status}</p></main>;
}

function NotFoundPage() {
  return <main><h1>Page not found</h1><Link to="/workspaces/10/projects/20">Return to project</Link></main>;
}

export function RouteDemo() {
  return (
    <Routes>
      <Route path="/workspaces/:workspaceId" element={<WorkspaceLayout />}>
        <Route path="projects/:projectId" element={<ProjectIssues />} />
      </Route>
      <Route path="/issues/:issueId" element={<IssuePage />} />
      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  );
}
