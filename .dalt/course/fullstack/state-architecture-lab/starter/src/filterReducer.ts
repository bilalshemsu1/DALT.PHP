import type { IssueStatus } from './issue';

export type FilterState = {
  status: IssueStatus;
  query: string;
  page: number;
};

/**
 * The action names are the vocabulary of the screen. `page-changed` is a different
 * event from `query-changed`, even though both end up changing `page`.
 */
export type FilterAction =
  | { type: 'status-changed'; status: IssueStatus }
  | { type: 'query-changed'; query: string }
  | { type: 'page-changed'; page: number };

export const initialFilterState: FilterState = { status: 'open', query: '', page: 1 };

export function filterReducer(state: FilterState, action: FilterAction): FilterState {
  switch (action.type) {
    // Narrowing the result set has to return to the first page, or the screen shows
    // "no results" for a page that no longer exists.
    case 'status-changed':
      return { ...state, status: action.status, page: 1 };
    case 'query-changed':
      return { ...state, query: action.query, page: 1 };
    case 'page-changed':
      return { ...state, page: action.page };
  }
}
