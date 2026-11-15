import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright E2E Configuration for Turbo Tenant Live Storefront.
 * Supports multi-tenant subdomains on Windows and multi-theme testing.
 */
export default defineConfig({
  testDir: './tests/e2e/plugins',
  fullyParallel: true,
  timeout: 45000,
  expect: {
    timeout: 10000,
  },
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: 1,
  reporter: [
    ['list'],
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
  ],

  use: {
    baseURL: process.env.E2E_BASE_URL || 'http://e2e.localhost:8000',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    actionTimeout: 15000,
    navigationTimeout: 30000,

    launchOptions: {
      args: [
        '--host-resolver-rules=MAP *.localhost 127.0.0.1',
        '--disable-gpu',
        '--no-sandbox',
      ],
    },
  },

  projects: [
    {
      name: 'theme-default',
      use: {
        ...devices['Desktop Chrome'],
        activeThemeName: 'default',
      },
    },
    // Future themes plug in here:
    // {
    //   name: 'theme-modern',
    //   use: {
    //     ...devices['Desktop Chrome'],
    //     activeThemeName: 'modern',
    //   },
    // },
  ],
});
