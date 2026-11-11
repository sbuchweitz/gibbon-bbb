import { defineConfig, devices } from '@playwright/test';
import { GIBBON_URL } from './support/env.mjs';

export default defineConfig({
  testDir: './specs',
  globalSetup: './global-setup.mjs',
  fullyParallel: false,
  workers: 1, // specs share one Gibbon database and one mock BBB
  timeout: 90_000,
  expect: { timeout: 15_000 },
  reporter: [['list']],
  use: {
    baseURL: GIBBON_URL,
    ignoreHTTPSErrors: true,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        // Production Gibbon runs on HTTPS; treat the plain-HTTP harness as a secure origin so the embedded class works.
        // The full Chromium build is needed: the default headless shell ignores this flag.
        channel: 'chromium',
        launchOptions: GIBBON_URL.startsWith('http:') ? { args: [`--unsafely-treat-insecure-origin-as-secure=${new URL(GIBBON_URL).origin}`] } : {},
      },
    },
    {
      // Safari's engine. The upgrade spec tests the database upgrade, not the browser: Chromium covers it.
      name: 'webkit',
      use: { ...devices['Desktop Safari'] },
      testIgnore: /upgrade\.spec/,
    },
  ],
});
