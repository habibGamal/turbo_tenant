import { test, expect } from '../../helpers/live-fixture';

test.describe('CatalogProducts Suite 09: Quick-Add & Options Modal Flow', () => {
  test('opens options modal when clicking Add to Cart on product with variants or addons', async ({ page, theme }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    // Product 5 (Simple Coffee) is configured with variants, weights, and addons
    const productCards = theme.catalog.getProductCards();
    await expect(productCards.first()).toBeVisible({ timeout: 10000 });

    // Locate card for Product 5 or first card with options
    const cardWithOptions = page.locator('[data-testid="product-card"]').filter({
      hasText: /Simple Coffee|كوف ساده/i,
    }).first();

    const targetCard = (await cardWithOptions.count()) > 0 ? cardWithOptions : productCards.first();
    const addToCartBtn = theme.catalog.getAddToCartButton(targetCard);
    await expect(addToCartBtn).toBeVisible();

    // Click Add to Cart button on card - should trigger options modal fetch and open modal
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('/api/catalog-products/') && r.status() === 200),
      addToCartBtn.click(),
    ]);

    // Modal should be displayed to choose variants/addons before adding to cart
    const modal = theme.catalog.getOptionsModal();
    await expect(modal).toBeVisible({ timeout: 5000 });

    // Verify modal contains variant options or weight options
    const variants = theme.catalog.getVariantOptions();
    if ((await variants.count()) > 0) {
      await expect(variants.first()).toBeVisible();
      // Select variant inside modal
      await variants.nth(1).click();
    }

    // Add to cart from inside the modal
    const modalAddToCart = modal.locator('[data-testid="product-detail-add-to-cart"]');
    await expect(modalAddToCart).toBeVisible();

    await Promise.all([
      page.waitForResponse(
        (r) => r.url().includes('/api/cart/items') && (r.status() === 201 || r.status() === 200)
      ),
      modalAddToCart.click(),
    ]);

    // Modal should close upon successful addition
    await expect(modal).not.toBeVisible({ timeout: 5000 });

    // Header cart badge increments
    const badge = theme.cart.getBadgeCount();
    await expect(badge).toBeVisible({ timeout: 5000 });
    const badgeText = await badge.textContent();
    expect(Number(badgeText)).toBeGreaterThanOrEqual(1);
  });

  test('detail page add-to-cart button adds configured product to cart', async ({ page, theme }) => {
    const productPromise = page.waitForResponse(
      (r) => r.url().includes('/api/catalog-products/15') && r.status() === 200
    ).catch(() => null);
    await page.goto('/products/15', { waitUntil: 'domcontentloaded' });
    await productPromise;

    const addToCartButton = theme.catalog.getDetailAddToCartButton();
    await expect(addToCartButton).toBeVisible({ timeout: 10000 });

    const [response] = await Promise.all([
      page.waitForResponse(
        (r) => r.url().includes('/api/cart/items') && (r.status() === 201 || r.status() === 200)
      ),
      addToCartButton.click(),
    ]);

    expect(response.ok()).toBeTruthy();

    const badge = theme.cart.getBadgeCount();
    await expect(badge).toBeVisible({ timeout: 5000 });
  });
});
