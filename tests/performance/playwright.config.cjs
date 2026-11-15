const { devices } = require('@playwright/test');
module.exports = {
    testDir: '.',
    testMatch: '*.spec.cjs',
    workers: 1,
    reporter: 'list',
    use: { baseURL: process.env.E2E_BASE_URL || 'http://e2e.localhost:8000', headless: true },
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
        { name: 'mobile', use: { ...devices['iPhone 13'], browserName: 'chromium' } },
    ],
};

