import { test, expect } from '../../helpers/live-fixture';

test.describe('CatalogProducts Suite 11: Storefront Menu Page & Infinite Scroll', () => {
  test('renders menu page with top navigation search, categories, and initial product grid', async ({ page, theme }) => {
    await page.goto('/menu', { waitUntil: 'domcontentloaded' });

    // Assert that menu page is mounted
    const menuPage = page.locator('[data-testid="menu-page"]');
    await expect(menuPage).toBeVisible({ timeout: 10000 });

    // Top navigation search bar is visible
    const searchInput = theme.catalog.getSearchInput();
    await expect(searchInput).toBeVisible({ timeout: 10000 });

    // Category filter options rendered
    const categoryFilters = page.locator('[data-testid="menu-category-filter"]');
    await expect(categoryFilters.first()).toBeVisible({ timeout: 10000 });
    const catCount = await categoryFilters.count();
    expect(catCount).toBeGreaterThan(0);

    // Initial products are loaded
    const productCards = page.locator('[data-testid="menu-product-card"]');
    await expect(productCards.first()).toBeVisible({ timeout: 10000 });
    const productCount = await productCards.count();
    expect(productCount).toBeGreaterThan(0);
  });

  test('filters products by category and updates URL query parameters', async ({ page }) => {
    await page.goto('/menu', { waitUntil: 'domcontentloaded' });

    // Target a category that has products (Turkish coffee / قهوة تركي)
    const targetCat = page.locator('[data-testid="menu-category-filter"]', { hasText: /تركي|Turkish/i }).first();
    await expect(targetCat).toBeVisible({ timeout: 10000 });
    await targetCat.click();

    // URL should be updated with category parameter
    await expect(page).toHaveURL(/category/);

    // Products list should remain populated and reflect filter
    const productCards = page.locator('[data-testid="menu-product-card"]');
    await expect(productCards.first()).toBeVisible({ timeout: 10000 });
  });

  test('displays empty state when filtering a category with zero products', async ({ page }) => {
    await page.goto('/menu', { waitUntil: 'domcontentloaded' });

    // Select category with no products (Coffee tools / ادوات قهوة)
    const emptyCat = page.locator('[data-testid="menu-category-filter"]', { hasText: /ادوات|tools/i }).first();
    await expect(emptyCat).toBeVisible({ timeout: 10000 });
    await emptyCat.click();

    // Empty state should be visible
    const emptyHeading = page.locator('h3', { hasText: /No products found|لم يتم العثور على منتجات/i });
    await expect(emptyHeading).toBeVisible({ timeout: 10000 });
  });

  test('live search in top navigation filters products by keyword on Enter', async ({ page, theme }) => {
    await page.goto('/menu', { waitUntil: 'domcontentloaded' });

    const searchInput = theme.catalog.getSearchInput();
    await expect(searchInput).toBeVisible({ timeout: 10000 });

    const searchPromise = page.waitForResponse(
      (r) => r.url().includes('/api/catalog-products') && r.url().includes('search=Coffee') && r.status() === 200
    );

    await searchInput.fill('Coffee');
    await searchInput.press('Enter');

    // URL should have ?search=Coffee
    await expect(page).toHaveURL(/search=Coffee/i);
    await searchPromise;

    const productCards = page.locator('[data-testid="menu-product-card"]');
    await expect(productCards.first()).toBeVisible({ timeout: 10000 });
  });

  test('top search bar pre-fills search term from URL query parameter', async ({ page, theme }) => {
    await page.goto('/menu?search=Latte', { waitUntil: 'domcontentloaded' });

    const searchInput = theme.catalog.getSearchInput();
    await expect(searchInput).toBeVisible({ timeout: 10000 });
    await expect(searchInput).toHaveValue('Latte');
  });

  test('mobile screen hides top navigation cart icon and auto-expands search', async ({ page }) => {
    // Set mobile viewport (iPhone dimensions)
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/menu', { waitUntil: 'domcontentloaded' });

    // Top header cart trigger must be hidden on mobile screen
    const topHeaderCart = page.locator('header [data-testid="cart-trigger"]');
    await expect(topHeaderCart).toBeHidden();

    // Search bar below top navigation is auto-expanded and visible
    const mobileSearchInput = page.locator('[data-testid="search-input"]:visible');
    await expect(mobileSearchInput).toBeVisible({ timeout: 10000 });
  });

  test('infinite scroll sentinel or load more fetches next page of products', async ({ page }) => {
    await page.goto('/menu', { waitUntil: 'domcontentloaded' });

    const productCards = page.locator('[data-testid="menu-product-card"]');
    await expect(productCards.first()).toBeVisible({ timeout: 10000 });
    const initialCount = await productCards.count();

    // Scroll to bottom to trigger intersection observer
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));

    // Wait for additional products to load via intersection observer
    await expect.poll(async () => await productCards.count(), {
      timeout: 10000,
    }).toBeGreaterThan(initialCount);
  });
});
