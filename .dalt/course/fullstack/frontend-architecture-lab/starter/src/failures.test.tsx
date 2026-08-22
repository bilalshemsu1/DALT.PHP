import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ErrorBoundary } from './app/ErrorBoundary';
import { readConfig } from './app/config';

let shouldThrow = true;

function Chart() {
  if (shouldThrow) {
    throw new Error('chart data is malformed');
  }

  return <p>chart rendered</p>;
}

function ExplodingButton() {
  return (
    <button
      onClick={() => {
        throw new Error('handler failed');
      }}
    >
      Save
    </button>
  );
}

beforeEach(() => {
  shouldThrow = true;
  // React reports a caught render error to the console. Silence it here so a passing
  // run stays readable; never silence it in application code.
  vi.spyOn(console, 'error').mockImplementation(() => {});
});

afterEach(() => {
  vi.restoreAllMocks();
});

it('fails loudly when a required public value is missing', () => {
  expect(() => readConfig({})).toThrow('VITE_API_BASE_URL is missing');
  expect(readConfig({ VITE_API_BASE_URL: '/api' })).toEqual({ apiBaseUrl: '/api' });
});

it('exposes only prefixed values to the client bundle', () => {
  expect(import.meta.env.VITE_API_BASE_URL).toBe('/api');
  // The same .env file defines APP_SESSION_SECRET, and it never reaches the browser.
  expect(import.meta.env.APP_SESSION_SECRET).toBeUndefined();
});

it('contains a render failure inside its own section', () => {
  render(
    <>
      <ErrorBoundary section="Chart">
        <Chart />
      </ErrorBoundary>
      <p>issue list still works</p>
    </>,
  );

  expect(screen.getByRole('alert')).toHaveTextContent('Chart is unavailable.');
  expect(screen.getByText('issue list still works')).toBeInTheDocument();
});

it('recovers when the cause is gone and the boundary is reset', async () => {
  const user = userEvent.setup();

  render(
    <ErrorBoundary section="Chart">
      <Chart />
    </ErrorBoundary>,
  );

  expect(screen.getByRole('alert')).toBeInTheDocument();

  shouldThrow = false;
  await user.click(screen.getByRole('button', { name: 'Try again' }));

  expect(screen.getByText('chart rendered')).toBeInTheDocument();
  expect(screen.queryByRole('alert')).not.toBeInTheDocument();
});

it('does not catch an error thrown from an event handler', () => {
  render(
    <ErrorBoundary section="Editor">
      <ExplodingButton />
    </ErrorBoundary>,
  );

  // React does not swallow it: it reports the error to the global handler instead.
  const reported: string[] = [];
  const onError = (event: ErrorEvent) => {
    reported.push((event.error as Error).message);
    event.preventDefault();
  };
  window.addEventListener('error', onError);

  fireEvent.click(screen.getByRole('button', { name: 'Save' }));

  window.removeEventListener('error', onError);

  expect(reported).toEqual(['handler failed']);
  expect(screen.queryByRole('alert')).not.toBeInTheDocument();
});
