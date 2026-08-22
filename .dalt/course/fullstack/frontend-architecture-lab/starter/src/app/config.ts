export type AppConfig = {
  apiBaseUrl: string;
};

/**
 * Client configuration is public, build-time text. It is baked into the bundle, so it
 * may never hold a secret — and it should fail at startup rather than at the first
 * request that needed it.
 */
export function readConfig(env: Record<string, unknown>): AppConfig {
  const apiBaseUrl = env.VITE_API_BASE_URL;

  if (typeof apiBaseUrl !== 'string' || apiBaseUrl === '') {
    throw new Error('VITE_API_BASE_URL is missing. Set it in .env before starting the app.');
  }

  return { apiBaseUrl };
}

export const appConfig = readConfig(import.meta.env);
