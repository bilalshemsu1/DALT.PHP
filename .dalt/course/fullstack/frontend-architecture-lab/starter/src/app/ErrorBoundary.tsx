import { Component } from 'react';
import type { ErrorInfo, ReactNode } from 'react';

type Props = {
  section: string;
  children: ReactNode;
};

type State = {
  error: Error | null;
};

/**
 * An error boundary catches errors thrown while rendering its subtree. It does not catch
 * errors thrown from event handlers, timers, or rejected promises: those never pass
 * through React's rendering, so React has nothing to intercept.
 */
export class ErrorBoundary extends Component<Props, State> {
  state: State = { error: null };

  static getDerivedStateFromError(error: Error): State {
    return { error };
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    // In a real application this is where the report goes. Keep the component stack:
    // it is the only part that says which subtree failed.
    void error;
    void info;
  }

  render(): ReactNode {
    if (this.state.error !== null) {
      return (
        <div role="alert">
          <p>{this.props.section} is unavailable.</p>
          <button onClick={() => this.setState({ error: null })}>Try again</button>
        </div>
      );
    }

    return this.props.children;
  }
}
