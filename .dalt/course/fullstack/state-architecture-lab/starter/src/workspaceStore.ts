import { create } from 'zustand';

export type Density = 'comfortable' | 'compact';

type WorkspaceState = {
  density: Density;
  sidebarOpen: boolean;
  setDensity: (density: Density) => void;
  toggleSidebar: () => void;
};

const initial = { density: 'comfortable' as Density, sidebarOpen: true };

/**
 * Client preferences only. No issue, comment, or user record belongs here: those have
 * an address in the query cache and a server that owns them.
 */
export const useWorkspaceStore = create<WorkspaceState>()((set) => ({
  ...initial,
  setDensity: (density) => set({ density }),
  toggleSidebar: () => set((state) => ({ sidebarOpen: !state.sidebarOpen })),
}));

/** A module-level store outlives a test. Reset it, or the next test inherits it. */
export function resetWorkspaceStore(): void {
  useWorkspaceStore.setState(initial);
}
