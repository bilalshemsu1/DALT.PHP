import { useState } from 'react';

export type IssueSelection = {
  selected: string[];
  isSelected: (issueId: string) => boolean;
  toggle: (issueId: string) => void;
  clear: () => void;
};

/**
 * The state lives in the caller. Two components calling this hook get two independent
 * selections — a hook is shared behavior, never a shared value.
 */
export function useIssueSelection(): IssueSelection {
  const [selected, setSelected] = useState<string[]>([]);

  return {
    selected,
    isSelected: (issueId) => selected.includes(issueId),
    toggle: (issueId) =>
      setSelected((current) =>
        current.includes(issueId)
          ? current.filter((id) => id !== issueId)
          : [...current, issueId],
      ),
    clear: () => setSelected([]),
  };
}
