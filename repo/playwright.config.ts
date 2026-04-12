import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/Playwright',
  timeout: 90000,
  retries: 0,
  workers: 1,
  use: {
    baseURL: 'http://127.0.0.1:8080',
    headless: true,
    screenshot: 'on',
    trace: 'on-first-retry',
    actionTimeout: 15000,
  },
  projects: [
    {
      name: 'chromium',
      use: { browserName: 'chromium' },
    },
  ],
  outputDir: './tests/Playwright/artifacts',
  reporter: [['list'], ['html', { outputFolder: './tests/Playwright/report', open: 'never' }]],
  retries: 1,
});
