export type IssueStatus = 'open' | 'closed';

export type Issue = {
  id: string;
  title: string;
  status: IssueStatus;
};

export function isIssueStatus(value: string): value is IssueStatus {
  return value === 'open' || value === 'closed';
}
