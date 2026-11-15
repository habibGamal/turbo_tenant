# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: catalog-products\02-categories-navigation.live.spec.ts >> CatalogProducts Suite 02: Category Listings & Navigation >> verifies live category listing and category data integrity
- Location: tests\e2e\plugins\catalog-products\02-categories-navigation.live.spec.ts:4:3

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: locator('[data-testid="category-tab"]').first()
Expected: visible
Timeout: 10000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" locator('[data-testid="category-tab"]').first() with timeout 10000ms
  - waiting for locator('[data-testid="category-tab"]').first()

```

# Test source

```ts
  1  | import { test, expect } from '../../helpers/live-fixture';
  2  | 
  3  | test.describe('CatalogProducts Suite 02: Category Listings & Navigation', () => {
  4  |   test('verifies live category listing and category data integrity', async ({ page, theme }) => {
  5  |     await page.goto('/', { waitUntil: 'domcontentloaded' });
  6  | 
  7  |     // Wait for rendered data whether it came from bootstrap or a query.
  8  |     const categoryTabs = theme.catalog.getCategoryTabs();
> 9  |     await expect(categoryTabs.first()).toBeVisible();
     |                                        ^ Error: expect(locator).toBeVisible() failed
  10 | 
  11 |     // Fetch categories via browser network stack
  12 |     const json = await page.evaluate(async () => {
  13 |       const res = await fetch('/api/catalog-products/categories');
  14 |       return res.json();
  15 |     });
  16 | 
  17 |     expect(json.status).toBe('success');
  18 |     expect(Array.isArray(json.data)).toBeTruthy();
  19 |     expect(json.data.length).toBeGreaterThan(0);
  20 | 
  21 |     const firstCat = json.data[0];
  22 |     expect(firstCat).toHaveProperty('id');
  23 |     expect(firstCat).toHaveProperty('name');
  24 | 
  25 |     // Check category elements on storefront - rendered in the default theme
  26 |     const count = await categoryTabs.count();
  27 | 
  28 |     // Must match json.data.length exactly as rendered in the default theme
  29 |     expect(count).toBe(json.data.length);
  30 | 
  31 |     // Verify clicking a tab switches active state
  32 |     if (count > 1) {
  33 |       const secondTab = categoryTabs.nth(1);
  34 |       await secondTab.click();
  35 |       await expect(secondTab).toHaveClass(/border-primary/);
  36 |       await expect(secondTab).toHaveAttribute('aria-selected', 'true');
  37 |     }
  38 |   });
  39 | 
  40 |   test('category cards display image fallbacks and valid names', async ({ page, theme }) => {
  41 |     await page.goto('/', { waitUntil: 'domcontentloaded' });
  42 |     const categoryCards = theme.catalog.getCategoryCards();
  43 |     const count = await categoryCards.count();
  44 | 
  45 |     if (count > 0) {
  46 |       const firstCard = categoryCards.first();
  47 |       await expect(firstCard).toBeVisible();
  48 |       const text = await firstCard.textContent();
  49 |       expect(text?.trim().length).toBeGreaterThan(0);
  50 |     }
  51 |   });
  52 | });
  53 | 
```