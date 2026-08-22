import { useEffect, useState } from 'react';

export type Connect = (issueId: string, onEvent: (event: string) => void) => () => void;

/**
 * Cleanup is part of a hook's contract. Whatever a hook starts, it stops — on unmount
 * and on every change of its reactive inputs.
 */
export function useIssueEvents(connect: Connect, issueId: string): string | null {
  const [latest, setLatest] = useState<string | null>(null);

  useEffect(() => {
    setLatest(null);

    return connect(issueId, setLatest);
  }, [connect, issueId]);

  return latest;
}
