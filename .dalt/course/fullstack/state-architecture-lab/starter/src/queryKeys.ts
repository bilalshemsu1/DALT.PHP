import type { IssueStatus } from './issue';

/**
 * A query key is the address of one remote fact. Every input that changes the response
 * belongs in the key; nothing else does.
 */
export const queryKeys = {
  issues: (projectId: string, status: IssueStatus) =>
    ['projects', projectId, 'issues', { status }] as const,
  issue: (issueId: string) => ['issues', issueId] as const,
};
