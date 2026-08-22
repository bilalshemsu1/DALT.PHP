import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import type { Issue, IssueStatus } from './issue';
import type { IssueApi } from './issueApi';
import { queryKeys } from './queryKeys';

/**
 * A plain mutation: write, then tell the cache which facts the write changed.
 * No optimism, because a create has nothing correct to show before the server answers.
 */
export function CreateIssueForm({ api, projectId }: { api: IssueApi; projectId: string }) {
  const [draft, setDraft] = useState('');

  const create = useMutation({
    mutationFn: (title: string) => api.createIssue(projectId, title),
    onSuccess: async (created, _title, _onMutateResult, context) => {
      setDraft('');
      await context.client.invalidateQueries({
        queryKey: queryKeys.issues(projectId, created.status),
      });
    },
  });

  return (
    <form
      onSubmit={(event) => {
        event.preventDefault();
        create.mutate(draft);
      }}
    >
      <label>
        New issue
        <input value={draft} onChange={(event) => setDraft(event.target.value)} />
      </label>
      <button type="submit" disabled={create.isPending}>
        {create.isPending ? 'Creating…' : 'Create issue'}
      </button>
      {create.isError ? <p role="alert">Could not create the issue.</p> : null}
    </form>
  );
}

/**
 * An optimistic mutation. Note the callback arguments: the value returned by onMutate
 * arrives as the third argument, and the mutation context — which carries the query
 * client — is always last.
 */
export function IssueStatusCard({ api, issueId }: { api: IssueApi; issueId: string }) {
  const issue = useQuery({
    queryKey: queryKeys.issue(issueId),
    queryFn: () => api.getIssue(issueId),
    staleTime: 60_000,
  });

  const setStatus = useMutation({
    mutationFn: (next: IssueStatus) => api.setIssueStatus(issueId, next),
    onMutate: async (next, context) => {
      // Stop an in-flight read from landing on top of the optimistic value.
      await context.client.cancelQueries({ queryKey: queryKeys.issue(issueId) });
      const previous = context.client.getQueryData<Issue>(queryKeys.issue(issueId));
      if (previous !== undefined) {
        context.client.setQueryData<Issue>(queryKeys.issue(issueId), { ...previous, status: next });
      }

      return { previous };
    },
    onError: (_error, _next, onMutateResult, context) => {
      if (onMutateResult?.previous !== undefined) {
        context.client.setQueryData(queryKeys.issue(issueId), onMutateResult.previous);
      }
    },
    onSettled: (_data, _error, _next, _onMutateResult, context) =>
      context.client.invalidateQueries({ queryKey: queryKeys.issue(issueId) }),
  });

  if (issue.isPending) return <p>Loading issue…</p>;
  if (issue.isError) return <p role="alert">Could not load the issue.</p>;

  const next: IssueStatus = issue.data.status === 'open' ? 'closed' : 'open';

  return (
    <div>
      <p>
        {issue.data.title} — {issue.data.status}
      </p>
      <button onClick={() => setStatus.mutate(next)}>Mark {next}</button>
      {setStatus.isError ? <p role="alert">Could not change the status.</p> : null}
    </div>
  );
}
