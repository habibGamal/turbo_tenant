import { test, expect } from '../../helpers/live-fixture';

test.describe('CatalogProducts Suite 03: Live Autocompletion & Search Suggestions', () => {
  test('typing in search bar triggers autocompletion API and displays product suggestions', async ({ page, theme }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    const searchInput = theme.catalog.getSearchInput();
    await expect(searchInput).toBeVisible({ timeout: 10000 });

    // Type search query and wait for suggestions API response
    const [response] = await Promise.all([
      page.waitForResponse(
        (r) => r.url().includes('/api/catalog-products/suggestions') && r.status() === 200
      ),
      searchInput.fill('Coffee'),
    ]);

    expect(response.ok()).toBeTruthy();
    const json = await response.json();
    expect(json.status).toBe('success');

    // Suggestions container should appear
    const suggestionsContainer = theme.catalog.getSearchSuggestions();
    await expect(suggestionsContainer).toBeVisible({ timeout: 5000 });

    // Suggestion items should be visible
    const firstSuggestion = theme.catalog.getSearchSuggestionItem(0);
    await expect(firstSuggestion).toBeVisible();
    const itemText = await firstSuggestion.textContent();
    expect(itemText?.trim().length).toBeGreaterThan(0);
  });

  test('clicking a search suggestion navigates directly to product details', async ({ page, theme }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    const searchInput = theme.catalog.getSearchInput();
    await expect(searchInput).toBeVisible({ timeout: 10000 });

    await Promise.all([
      page.waitForResponse(
        (r) => r.url().includes('/api/catalog-products/suggestions') && r.status() === 200
      ),
      searchInput.fill('Coffee'),
    ]);

    const firstSuggestion = theme.catalog.getSearchSuggestionItem(0);
    await expect(firstSuggestion).toBeVisible({ timeout: 5000 });

    // Click suggestion and verify route transitions to /products/:id
    await firstSuggestion.click();
    await expect(page).toHaveURL(/\/products\/\d+/);

    const detailsContainer = theme.catalog.getProductDetailsContainer();
    await expect(detailsContainer).toBeVisible({ timeout: 10000 });
  });

  test('clearing search query dismisses suggestions dropdown', async ({ page, theme }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    const searchInput = theme.catalog.getSearchInput();
    await expect(searchInput).toBeVisible({ timeout: 10000 });

    await Promise.all([
      page.waitForResponse(
        (r) => r.url().includes('/api/catalog-products/suggestions') && r.status() === 200
      ),
      searchInput.fill('Coffee'),
    ]);

    const suggestionsContainer = theme.catalog.getSearchSuggestions();
    await expect(suggestionsContainer).toBeVisible({ timeout: 5000 });

    // Clear search input to 1 char or empty
    await searchInput.fill('');
    await expect(suggestionsContainer).not.toBeVisible();
  });
});
