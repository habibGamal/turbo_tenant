import { test as baseTest, expect, Page } from '@playwright/test';
import { IThemeAdapter } from '../themes/theme.contract';
import { getThemeAdapter } from '../themes/theme-factory';

export interface LiveTestFixtures {
  theme: IThemeAdapter;
  activeThemeName: string;
}

/**
 * Custom Playwright test fixture for Live-Mode Turbo Tenant E2E testing.
 * Automatically injects the theme adapter and applies high-speed network optimizations.
 */
export const test = baseTest.extend<LiveTestFixtures>({
  activeThemeName: [process.env.E2E_THEME || 'default', { option: true }],

  theme: async ({ page, activeThemeName }, use) => {
    // 1. High-Speed Live Optimization: Block heavy 3rd party tracking/analytics requests
    await page.route(
      /(connect\.facebook\.net|google-analytics\.com|googletagmanager\.com)/,
      (route) => route.abort()
    );

    // 2. Set default viewport
    await page.setViewportSize({ width: 1280, height: 800 });

    // 3. Resolve active theme adapter
    const theme = getThemeAdapter(page, activeThemeName);

    // 4. Provide theme to test
    await use(theme);
  },
});

export { expect };
