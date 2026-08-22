import { createContext, useContext, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { useShallow } from 'zustand/react/shallow';
import type { Density } from './workspaceStore';
import { useWorkspaceStore } from './workspaceStore';

/** Counts renders so the cost of each approach is measured rather than asserted. */
export const renderCounts = new Map<string, number>();

function countRender(name: string): void {
  renderCounts.set(name, (renderCounts.get(name) ?? 0) + 1);
}

type WorkspaceContextValue = {
  density: Density;
  sidebarOpen: boolean;
  setDensity: (density: Density) => void;
  toggleSidebar: () => void;
};

const WorkspaceContext = createContext<WorkspaceContextValue | null>(null);

export function WorkspaceProvider({ children }: { children: ReactNode }) {
  const [density, setDensity] = useState<Density>('comfortable');
  const [sidebarOpen, setSidebarOpen] = useState(true);

  const value = useMemo<WorkspaceContextValue>(
    () => ({
      density,
      sidebarOpen,
      setDensity,
      toggleSidebar: () => setSidebarOpen((open) => !open),
    }),
    [density, sidebarOpen],
  );

  return <WorkspaceContext.Provider value={value}>{children}</WorkspaceContext.Provider>;
}

function useWorkspaceContext(): WorkspaceContextValue {
  const value = useContext(WorkspaceContext);
  if (value === null) {
    throw new Error('useWorkspaceContext must be used inside a WorkspaceProvider');
  }

  return value;
}

export function ContextDensity() {
  countRender('context-density');
  const { density } = useWorkspaceContext();

  return <p>context density: {density}</p>;
}

export function ContextSidebar() {
  countRender('context-sidebar');
  const { sidebarOpen, toggleSidebar } = useWorkspaceContext();

  return (
    <div>
      <p>context sidebar: {sidebarOpen ? 'open' : 'closed'}</p>
      <button onClick={toggleSidebar}>Toggle context sidebar</button>
    </div>
  );
}

export function StoreDensity() {
  countRender('store-density');
  const density = useWorkspaceStore((state) => state.density);

  return <p>store density: {density}</p>;
}

export function StoreSidebar() {
  countRender('store-sidebar');
  const sidebarOpen = useWorkspaceStore((state) => state.sidebarOpen);
  const toggleSidebar = useWorkspaceStore((state) => state.toggleSidebar);

  return (
    <div>
      <p>store sidebar: {sidebarOpen ? 'open' : 'closed'}</p>
      <button onClick={toggleSidebar}>Toggle store sidebar</button>
    </div>
  );
}

/**
 * A selector that builds a new object every call. Without `useShallow` the new identity
 * would re-render this component on any store change at all.
 */
export function StoreSummary() {
  countRender('store-summary');
  const { density } = useWorkspaceStore(useShallow((state) => ({ density: state.density })));

  return <p>store summary: {density}</p>;
}
