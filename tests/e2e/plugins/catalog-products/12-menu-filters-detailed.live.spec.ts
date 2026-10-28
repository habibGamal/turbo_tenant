import { test, expect } from '../../helpers/live-fixture';

test.describe('CatalogProducts Suite 12: Detailed Menu Page Filters', () => {

  test('sorts products by price ascending (Low to High) and descending (High to Low)', async ({ page }) => {
    await page.goto('/menu', { waitUntil: 'domcontentloaded' });

    // Wait for initial products
    const productCards = page.locator('[data-testid="menu-product-card"]');
    await expect(productCards.first()).toBeVisible({ timeout: 10000 });

    // Helper to extract numeric prices from visible product cards
    const getDisplayedPrices = async (): Promise<number[]> => {
      const texts = await productCards.allInnerTexts();
      const prices: number[] = [];
      for (const text of texts) {
        // Match numbers before or after currency (e.g. "135.00 EGP" or "135.00 ج.م")
        const match = text.match(/(\d+(?:\.\d+)?)\s*(?:EGP|ج\.م)/i) || text.match(/(?:EGP|ج\.م)\s*(\d+(?:\.\d+)?)/i);
        if (match) {
          prices.push(parseFloat(match[1]));
        }
      }
      return prices;
    };

    // 1. Select "Price: Low to High"
    const sortTrigger = page.locator('[data-testid="desktop-sort-select"]');
    await sortTrigger.click();

    const priceAscOption = page.locator('[data-testid="sort-price-asc"]');
    await expect(priceAscOption).toBeVisible({ timeout: 5000 });

    const ascResponse = page.waitForResponse(
      (r) => r.url().includes('/api/catalog-products') && r.status() === 200
    );
    await priceAscOption.click();
    await ascResponse;

    // Verify URL parameter
    await expect(page).toHaveURL(/sort_by=base_price/);
    await expect(page).toHaveURL(/sort_order=asc/);

    await expect(productCards.first()).toBeVisible({ timeout: 10000 });
    const ascPrices = await getDisplayedPrices();
    expect(ascPrices.length).toBeGreaterThan(1);

    // Assert prices are sorted ascending
    for (let i = 0; i < ascPrices.length - 1; i++) {
      expect(ascPrices[i]).toBeLessThanOrEqual(ascPrices[i + 1]);
    }

    // 2. Select "Price: High to Low"
    await sortTrigger.click();
    const priceDescOption = page.locator('[data-testid="sort-price-desc"]');
    await expect(priceDescOption).toBeVisible({ timeout: 5000 });

    const descResponse = page.waitForResponse(
      (r) => r.url().includes('/api/catalog-products') && r.status() === 200
    );
    await priceDescOption.click();
    await descResponse;

    // Verify URL parameter
    await expect(page).toHaveURL(/sort_by=base_price/);
    await expect(page).toHaveURL(/sort_order=desc/);

    await expect(productCards.first()).toBeVisible({ timeout: 10000 });
    const descPrices = await getDisplayedPrices();
    expect(descPrices.length).toBeGreaterThan(1);

    // Assert prices are sorted descending
    for (let i = 0; i < descPrices.length - 1; i++) {
      expect(descPrices[i]).toBeGreaterThanOrEqual(descPrices[i + 1]);
    }
  });

  test('filters products within price range via Enter key submission and clears chip', async ({ page }) => {
    await page.goto('/menu', { waitUntil: 'domcontentloaded' });

    const productCards = page.locator('[data-testid="menu-product-card"]');
    await expect(productCards.first()).toBeVisible({ timeout: 10000 });

    const minInput = page.locator('[data-testid="desktop-min-price"]');
    const maxInput = page.locator('[data-testid="desktop-max-price"]');

    await minInput.fill('600');
    await maxInput.fill('850');

    // Press Enter to apply filters and wait for network response
    const priceFilterResponse = page.waitForResponse(
      (r) => r.url().includes('/api/catalog-products') && r.status() === 200
    );
    await maxInput.press('Enter');
    await priceFilterResponse;

    // Verify URL params
    await expect(page).toHaveURL(/min_price=600/);
    await expect(page).toHaveURL(/max_price=850/);

    // Verify active filter price badge
    const priceChip = page.locator('[data-testid="active-filter-price"]');
    await expect(priceChip).toBeVisible({ timeout: 5000 });
    await expect(priceChip).toContainText('600');
    await expect(priceChip).toContainText('850');

    // Wait for filtered products to render
    await expect(productCards.first()).toBeVisible({ timeout: 10000 });
    const count = await productCards.count();
    expect(count).toBeGreaterThan(0);

    for (let i = 0; i < count; i++) {
      const text = await productCards.nth(i).innerText();
      const match = text.match(/(\d+(?:\.\d+)?)\s*(?:EGP|ج\.م)/i) || text.match(/(?:EGP|ج\.م)\s*(\d+(?:\.\d+)?)/i);
      if (match) {
        const price = parseFloat(match[1]);
        expect(price).toBeGreaterThanOrEqual(600);
        expect(price).toBeLessThanOrEqual(850);
      }
    }

    // Clear price range via chip X button
    const removePriceChip = page.locator('[data-testid="remove-price-chip"]');
    await removePriceChip.click();

    // Verify price params are removed from URL
    await expect(page).not.toHaveURL(/min_price=/);
    await expect(page).not.toHaveURL(/max_price=/);
    await expect(priceChip).toBeHidden();
  });

  test('auto-swaps inverted price range when min is greater than max', async ({ page }) => {
    await page.goto('/menu', { waitUntil: 'domcontentloaded' });

    const minInput = page.locator('[data-testid="desktop-min-price"]');
    const maxInput = page.locator('[data-testid="desktop-max-price"]');
    const applyBtn = page.locator('[data-testid="desktop-apply-filters"]');

    // Enter inverted price range (Min: 800, Max: 200)
    await minInput.fill('800');
    await maxInput.fill('200');
    await applyBtn.click();

    // URL should be auto-swapped to min_price=200&max_price=800
    await expect(page).toHaveURL(/min_price=200/);
    await expect(page).toHaveURL(/max_price=800/);

    // Input fields should reflect swapped values
    await expect(minInput).toHaveValue('200');
    await expect(maxInput).toHaveValue('800');
  });

  test('supports multiple category selection and individual chip removal', async ({ page }) => {
    await page.goto('/menu', { waitUntil: 'domcontentloaded' });

    // Select category 1: Turkish coffee / قهوة تركي
    const turkishCat = page.locator('[data-testid="desktop-filter-panel"] [data-testid="menu-category-filter"]', {
      hasText: /تركي|Turkish/i,
    }).first();
    await expect(turkishCat).toBeVisible({ timeout: 10000 });
    await turkishCat.click();

    await expect(page).toHaveURL(/category/);

    // Select category 2: espresso / اسبرسو
    const espressoCat = page.locator('[data-testid="desktop-filter-panel"] [data-testid="menu-category-filter"]', {
      hasText: /اسبرسو|espresso/i,
    }).first();
    await expect(espressoCat).toBeVisible({ timeout: 10000 });
    await espressoCat.click();

    // Two active category chips should be present
    const categoryChips = page.locator('[data-testid="active-filter-category"]');
    await expect(categoryChips).toHaveCount(2, { timeout: 5000 });

    // Remove one category chip via X button
    const firstChipClose = categoryChips.first().locator('svg');
    await firstChipClose.click();

    // Exactly 1 category chip remains
    await expect(categoryChips).toHaveCount(1, { timeout: 5000 });
  });

  test('opens mobile filter drawer, applies filters, and updates storefront', async ({ page }) => {
    // Switch to mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/menu', { waitUntil: 'domcontentloaded' });

    // Mobile filter trigger button is visible
    const mobileTrigger = page.locator('[data-testid="mobile-filter-trigger"]');
    await expect(mobileTrigger).toBeVisible({ timeout: 10000 });
    await mobileTrigger.click();

    // Sheet drawer content is visible
    const mobileSheet = page.locator('[data-testid="mobile-filter-sheet"]');
    await expect(mobileSheet).toBeVisible({ timeout: 5000 });

    // Inside mobile drawer: select category and apply
    const mobileCat = page.locator('[data-testid="mobile-filter-panel"] [data-testid="menu-category-filter"]', {
      hasText: /تركي|Turkish/i,
    }).first();
    await expect(mobileCat).toBeVisible({ timeout: 5000 });
    await mobileCat.click();

    const mobileApplyBtn = page.locator('[data-testid="mobile-apply-filters"]');
    await mobileApplyBtn.click();

    // Sheet should close and URL should reflect category filter
    await expect(mobileSheet).toBeHidden({ timeout: 5000 });
    await expect(page).toHaveURL(/category/);

    const productCards = page.locator('[data-testid="menu-product-card"]');
    await expect(productCards.first()).toBeVisible({ timeout: 10000 });
  });

  test('clears all active filters and restores default state', async ({ page }) => {
    // Navigate with multiple active query params
    await page.goto('/menu?search=Coffee&min_price=100&max_price=800', { waitUntil: 'domcontentloaded' });

    const activeFiltersBar = page.locator('[data-testid="active-filters-bar"]');
    await expect(activeFiltersBar).toBeVisible({ timeout: 10000 });

    // Click "Clear All" button
    const clearAllBtn = page.locator('[data-testid="clear-all-filters"]');
    await expect(clearAllBtn).toBeVisible();
    await clearAllBtn.click();

    // All query parameters should be gone
    await expect(page).toHaveURL(/\/menu(?:\?.*)?$/);
    await expect(page).not.toHaveURL(/min_price=/);
    await expect(page).not.toHaveURL(/max_price=/);
    await expect(page).not.toHaveURL(/search=/);

    // Active filters bar should disappear
    await expect(activeFiltersBar).toBeHidden({ timeout: 5000 });
  });
});
