import { test, expect } from '../../helpers/live-fixture';

test.describe('CatalogProducts Suite 10: Error States & 404 Boundaries', () => {
  test('gracefully renders Product Not Found view when visiting non-existent product ID', async ({ page, theme }) => {
    // Start listening for 404 response before navigation to avoid race conditions
    const notFoundResponsePromise = page.waitForResponse(
      (r) => r.url().includes('/api/catalog-products/999999') && r.status() === 404
    );

    // Navigate to non-existent ID
    await page.goto('/products/999999', { waitUntil: 'domcontentloaded' });
    await notFoundResponsePromise;

    // Verify graceful not-found UI is displayed
    const notFoundContainer = theme.catalog.getNotFoundMessage();
    await expect(notFoundContainer).toBeVisible({ timeout: 10000 });

    // Verify Browse Menu fallback button is present
    const browseMenuButton = theme.catalog.getBrowseMenuButton();
    await expect(browseMenuButton).toBeVisible();

    // Click Browse Menu button and verify navigation away from 404
    await browseMenuButton.click();
    await expect(page).toHaveURL(/\/menu/);
  });
});
