import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router';
import type { Issue, IssueStatus } from './issue';
import { isIssueStatus } from './issue';
import type { IssueApi } from './issueApi';

/**
 * Part 04's manual approach, kept on purpose. Every value below is held by hand so we
 * can watch which kinds of value tolerate that and which kinds do not.
 */
export function IssueBoard({ api, projectId }: { api: IssueApi; projectId: string }) {
  const [searchParams, setSearchParams] = useSearchParams();
  const statusParam = searchParams.get('status') ?? 'open';
  const status: IssueStatus = isIssueStatus(statusParam) ? statusParam : 'open';

  // Local: private to this board, gone when it unmounts.
  const [draft, setDraft] = useState('');

  // Server snapshot copied into component state, plus a second copy of a fact that
  // could have been computed. The second copy is the mistake this experiment measures.
  const [issues, setIssues] = useState<Issue[]>([]);
  const [storedOpenCount, setStoredOpenCount] = useState(0);

  useEffect(() => {
    let live = true;
    void api.listIssues(projectId, status).then((loaded) => {
      if (!live) return;
      setIssues(loaded);
      setStoredOpenCount(loaded.filter((issue) => issue.status === 'open').length);
    });

    return () => {
      live = false;
    };
  }, [api, projectId, status]);

  const derivedOpenCount = issues.filter((issue) => issue.status === 'open').length;

  async function close(issueId: string) {
    const updated = await api.setIssueStatus(issueId, 'closed');
    setIssues((current) => current.map((issue) => (issue.id === issueId ? updated : issue)));
    // storedOpenCount is not updated here. That omission is the whole point.
  }

  return (
    <section>
      <h1>Issues ({status})</h1>

      <button onClick={() => setSearchParams({ status: status === 'open' ? 'closed' : 'open' })}>
        Show {status === 'open' ? 'closed' : 'open'}
      </button>

      <label>
        New issue
        <input value={draft} onChange={(event) => setDraft(event.target.value)} />
      </label>

      <p>Derived open: {derivedOpenCount}</p>
      <p>Stored open: {storedOpenCount}</p>

      <ul>
        {issues.map((issue) => (
          <li key={issue.id}>
            {issue.title} — {issue.status}
            <button onClick={() => void close(issue.id)}>Close {issue.title}</button>
          </li>
        ))}
      </ul>
    </section>
  );
}

/** One remote fact, fetched independently by one component. */
export function IssueStatusReader({
  api,
  issueId,
  label,
}: {
  api: IssueApi;
  issueId: string;
  label: string;
}) {
  const [issue, setIssue] = useState<Issue | null>(null);

  useEffect(() => {
    let live = true;
    void api.getIssue(issueId).then((loaded) => {
      if (live) setIssue(loaded);
    });

    return () => {
      live = false;
    };
  }, [api, issueId]);

  async function close() {
    setIssue(await api.setIssueStatus(issueId, 'closed'));
  }

  return (
    <p>
      {label}: {issue?.status ?? 'loading'}
      <button onClick={() => void close()}>Close from {label}</button>
    </p>
  );
}
