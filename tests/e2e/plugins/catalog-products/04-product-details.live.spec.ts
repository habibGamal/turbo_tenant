import { test, expect } from '../../helpers/live-fixture';

test.describe('CatalogProducts Suite 04: Product Detail Page & Meta Information', () => {
  test('navigates from card click to product details page and renders all sections', async ({ page, theme }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    const firstCard = theme.catalog.getProductCard(0);
    await expect(firstCard).toBeVisible({ timeout: 10000 });

    const cardTitle = await theme.catalog.getProductTitle(firstCard).textContent();

    // Click card to navigate
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('/api/catalog-products/') && r.status() === 200),
      // The card itself isn't clickable any more; the title is a real link
      firstCard.locator('[data-testid="product-card-title"] a').click(),
    ]);

    await expect(page).toHaveURL(/\/products\/\d+/);

    // Verify details container
    const detailsContainer = theme.catalog.getProductDetailsContainer();
    await expect(detailsContainer).toBeVisible({ timeout: 10000 });

    // Verify title matches
    if (cardTitle) {
      await expect(page.locator(`text=${cardTitle.trim()}`).first()).toBeVisible();
    }

    // Verify Add to Cart button on detail page
    const detailAddToCart = theme.catalog.getDetailAddToCartButton();
    await expect(detailAddToCart).toBeVisible();
  });

  test('breadcrumb navigation allows returning to home page', async ({ page }) => {
    await page.goto('/products/5', { waitUntil: 'domcontentloaded' });
    await page.waitForResponse(
      (r) => r.url().includes('/api/catalog-products/5') && r.status() === 200
    );

    // Locate home breadcrumb link
    const homeLink = page.locator('nav a[href="/"], main a[href="/"]').first();
    await expect(homeLink).toBeVisible();
    await homeLink.click();

    await expect(page).toHaveURL(/\/$/);
  });
});
