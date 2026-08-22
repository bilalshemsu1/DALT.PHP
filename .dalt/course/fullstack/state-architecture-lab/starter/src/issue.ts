export type IssueStatus = 'open' | 'closed';

export type Issue = {
  id: string;
  title: string;
  status: IssueStatus;
};

export function isIssueStatus(value: string): value is IssueStatus {
  return value === 'open' || value === 'closed';
}

/** Route parameters are untrusted strings, so an issue id is validated before use. */
export function parseIssueId(value: string | undefined): string | null {
  return value !== undefined && /^ISS-\d+$/.test(value) ? value : null;
}
