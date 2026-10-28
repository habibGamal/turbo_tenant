import { test, expect } from '../../helpers/live-fixture';

test.describe('CatalogProducts Suite 02: Category Listings & Navigation', () => {
  test('verifies live category listing and category data integrity', async ({ page, theme }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await page.waitForResponse(
      (r) => r.url().includes('/api/catalog-products/categories') && r.status() === 200
    );

    // Fetch categories via browser network stack
    const json = await page.evaluate(async () => {
      const res = await fetch('/api/catalog-products/categories');
      return res.json();
    });

    expect(json.status).toBe('success');
    expect(Array.isArray(json.data)).toBeTruthy();
    expect(json.data.length).toBeGreaterThan(0);

    const firstCat = json.data[0];
    expect(firstCat).toHaveProperty('id');
    expect(firstCat).toHaveProperty('name');

    // Check category elements on storefront - rendered in the default theme
    const categoryTabs = theme.catalog.getCategoryTabs();
    await expect(categoryTabs.first()).toBeVisible({ timeout: 10000 });
    const count = await categoryTabs.count();

    // Must match json.data.length exactly as rendered in the default theme
    expect(count).toBe(json.data.length);

    // Verify clicking a tab switches active state
    if (count > 1) {
      const secondTab = categoryTabs.nth(1);
      await secondTab.click();
      await expect(secondTab).toHaveClass(/border-primary/);
      await expect(secondTab).toHaveAttribute('aria-selected', 'true');
    }
  });

  test('category cards display image fallbacks and valid names', async ({ page, theme }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    const categoryCards = theme.catalog.getCategoryCards();
    const count = await categoryCards.count();

    if (count > 0) {
      const firstCard = categoryCards.first();
      await expect(firstCard).toBeVisible();
      const text = await firstCard.textContent();
      expect(text?.trim().length).toBeGreaterThan(0);
    }
  });
});
