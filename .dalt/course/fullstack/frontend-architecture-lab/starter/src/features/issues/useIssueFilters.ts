import { useCallback } from 'react';
import { useSearchParams } from 'react-router';
import type { IssueStatus } from '../../shared/types';
import { isIssueStatus } from '../../shared/types';

export type IssueFilters = {
  status: IssueStatus;
  query: string;
  page: number;
  setStatus: (status: IssueStatus) => void;
  setQuery: (query: string) => void;
  setPage: (page: number) => void;
};

/**
 * The URL stays the owner of these values. The hook is a named reading of it, not a
 * second copy: every setter writes back to the address bar.
 */
export function useIssueFilters(): IssueFilters {
  const [searchParams, setSearchParams] = useSearchParams();

  const rawStatus = searchParams.get('status') ?? 'open';
  const status: IssueStatus = isIssueStatus(rawStatus) ? rawStatus : 'open';
  const query = searchParams.get('q') ?? '';
  const parsedPage = Number.parseInt(searchParams.get('page') ?? '1', 10);
  const page = Number.isInteger(parsedPage) && parsedPage > 0 ? parsedPage : 1;

  const write = useCallback(
    (next: { status: IssueStatus; query: string; page: number }) => {
      const params: Record<string, string> = { status: next.status, page: String(next.page) };
      if (next.query !== '') {
        params.q = next.query;
      }
      setSearchParams(params);
    },
    [setSearchParams],
  );

  return {
    status,
    query,
    page,
    // Narrowing the result set returns to page one. The rule lives here, once, instead
    // of in every component that changes a filter.
    setStatus: (nextStatus) => write({ status: nextStatus, query, page: 1 }),
    setQuery: (nextQuery) => write({ status, query: nextQuery, page: 1 }),
    setPage: (nextPage) => write({ status, query, page: nextPage }),
  };
}
