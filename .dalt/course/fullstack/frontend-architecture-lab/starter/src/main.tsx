import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { App } from './app/App';
import { appConfig } from './app/config';
import { ErrorBoundary } from './app/ErrorBoundary';

// Reading the configuration here means a missing value stops the application at startup.
console.info('API base URL:', appConfig.apiBaseUrl);

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <ErrorBoundary section="Issues">
      <App />
    </ErrorBoundary>
  </StrictMode>,
);
