import { useQuery } from '@tanstack/react-query';
import type { IssueStatus } from './issue';
import { parseIssueId } from './issue';
import type { IssueApi } from './issueApi';
import { queryKeys } from './queryKeys';

/** One issue, read by whichever component needs it. The key is the shared address. */
export function IssueTitle({
  api,
  issueId,
  label,
}: {
  api: IssueApi;
  issueId: string;
  label: string;
}) {
  const issue = useQuery({
    queryKey: queryKeys.issue(issueId),
    queryFn: () => api.getIssue(issueId),
  });

  if (issue.isPending) return <p>{label}: loading</p>;
  if (issue.isError) return <p>{label}: unavailable</p>;

  return (
    <p>
      {label}: {issue.data.title}
    </p>
  );
}

/**
 * A query whose input is not ready yet must not run. `enabled` expresses that; an
 * `undefined` spliced into a URL does not.
 */
export function OptionalIssueTitle({ api, issueId }: { api: IssueApi; issueId?: string }) {
  const parsed = parseIssueId(issueId);
  const issue = useQuery({
    queryKey: queryKeys.issue(parsed ?? 'none'),
    queryFn: () => api.getIssue(parsed as string),
    enabled: parsed !== null,
  });

  return (
    <p>
      pending={String(issue.isPending)} loading={String(issue.isLoading)} fetching=
      {String(issue.isFetching)} title={issue.data?.title ?? '-'}
    </p>
  );
}

/** The five states a read can be in, rendered honestly. */
export function IssueListPanel({
  api,
  projectId,
  status,
  staleTime = 0,
}: {
  api: IssueApi;
  projectId: string;
  status: IssueStatus;
  staleTime?: number;
}) {
  const issues = useQuery({
    queryKey: queryKeys.issues(projectId, status),
    queryFn: () => api.listIssues(projectId, status),
    staleTime,
  });

  if (issues.isPending) return <p>Loading issues…</p>;

  if (issues.isError && issues.data === undefined) {
    return (
      <div>
        <p role="alert">Could not load issues.</p>
        <button onClick={() => void issues.refetch()}>Try again</button>
      </div>
    );
  }

  return (
    <div>
      {issues.isError ? <p role="alert">Showing the last known list — could not refresh.</p> : null}
      <button onClick={() => void issues.refetch()}>Refresh</button>
      {issues.data.length === 0 ? (
        <p>No {status} issues yet.</p>
      ) : (
        <ul>
          {issues.data.map((issue) => (
            <li key={issue.id}>{issue.title}</li>
          ))}
        </ul>
      )}
    </div>
  );
}
