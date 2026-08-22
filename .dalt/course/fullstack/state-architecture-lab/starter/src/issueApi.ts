import type { Issue, IssueStatus } from './issue';

export class ApiError extends Error {
  constructor(readonly status: number, message: string) {
    super(message);
    this.name = 'ApiError';
  }
}

/**
 * The transport boundary from FS04.4: one module decides where the application talks to
 * the server. TanStack Query sits above this; it never replaces it.
 */
export type IssueApi = {
  listIssues(projectId: string, status: IssueStatus): Promise<Issue[]>;
  getIssue(issueId: string): Promise<Issue>;
  createIssue(projectId: string, title: string): Promise<Issue>;
  setIssueStatus(issueId: string, status: IssueStatus): Promise<Issue>;
};

/**
 * An in-memory stand-in for DALT, so the experiments stay disposable and deterministic.
 * `calls` records what the cache actually asked for, which is the evidence several
 * lessons in Part 08 depend on.
 */
export type FakeServer = IssueApi & {
  calls: string[];
  failNextWrite: (error: ApiError) => void;
  failReads: (error: ApiError | null) => void;
};

export function createFakeServer(seed: Issue[] = []): FakeServer {
  let issues: Issue[] = seed.map((issue) => ({ ...issue }));
  let nextId = seed.length + 1;
  let writeError: ApiError | null = null;
  let readError: ApiError | null = null;
  const calls: string[] = [];

  function find(issueId: string): Issue {
    const issue = issues.find((candidate) => candidate.id === issueId);
    if (issue === undefined) {
      throw new ApiError(404, `No issue ${issueId}`);
    }

    return { ...issue };
  }

  return {
    calls,
    failNextWrite: (error) => {
      writeError = error;
    },
    failReads: (error) => {
      readError = error;
    },
    async listIssues(projectId, status) {
      calls.push(`listIssues:${projectId}:${status}`);
      if (readError !== null) {
        throw readError;
      }

      return issues.filter((issue) => issue.status === status).map((issue) => ({ ...issue }));
    },
    async getIssue(issueId) {
      calls.push(`getIssue:${issueId}`);
      if (readError !== null) {
        throw readError;
      }

      return find(issueId);
    },
    async createIssue(projectId, title) {
      calls.push(`createIssue:${projectId}`);
      if (writeError !== null) {
        const error = writeError;
        writeError = null;
        throw error;
      }

      const created: Issue = { id: `ISS-${nextId++}`, title, status: 'open' };
      issues = [...issues, created];

      return { ...created };
    },
    async setIssueStatus(issueId, status) {
      calls.push(`setIssueStatus:${issueId}:${status}`);
      if (writeError !== null) {
        const error = writeError;
        writeError = null;
        throw error;
      }

      const updated = { ...find(issueId), status };
      issues = issues.map((issue) => (issue.id === issueId ? updated : issue));

      return { ...updated };
    },
  };
}
