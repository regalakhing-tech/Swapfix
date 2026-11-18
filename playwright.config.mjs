import { defineConfig } from '@playwright/test';

const baseURL = process.env.SWAPFIX_TEST_ORIGIN || 'http://127.0.0.1:8000';

export default defineConfig({
  testDir: './tests/browser',
  outputDir: './.tools/playwright-results',
  reporter: 'list',
  workers: 2,
  use: {
    baseURL,
    channel: process.env.SWAPFIX_TEST_BROWSER_CHANNEL || undefined,
    trace: 'retain-on-failure',
  },
  projects: [
    { name: 'desktop', use: { viewport: { width: 1440, height: 900 } } },
    { name: 'tablet', use: { viewport: { width: 768, height: 1024 }, hasTouch: true } },
    { name: 'phone', use: { viewport: { width: 390, height: 844 }, hasTouch: true } },
  ],
});
