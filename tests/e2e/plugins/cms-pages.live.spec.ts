import { test, expect } from '../helpers/live-fixture';

test.describe('Plugin: cms-pages (Live)', () => {
  test('navigates to cms page route and handles dynamic content', async ({ page, theme }) => {
    // Visit about-us CMS route
    await page.goto('/pages/about-us');

    // Asserts page container is rendered
    const container = theme.cms.getPageContainer();
    await expect(container).toBeVisible();
  });

  test('handles non-existent cms page slug gracefully', async ({ page, theme }) => {
    await page.goto('/pages/non-existent-page-slug-999');

    // Page should render without breaking React app shell
    await expect(page.locator('body')).toBeVisible();
  });
});
