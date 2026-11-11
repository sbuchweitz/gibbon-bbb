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
    ...devices['Desktop Chrome'],
    baseURL: GIBBON_URL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
});
