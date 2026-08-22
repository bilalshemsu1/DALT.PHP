// STAGE 1 — this import is the boundary violation the checker reports. A shared module
// must not depend on a feature; the dependency has to point the other way.
import { issueStatusLabels } from '../features/issues/issueStatusLabels';
import type { Issue } from './types';

export function formatIssueLabel(issue: Issue): string {
  return `${issue.title} (${issueStatusLabels[issue.status]})`;
}
