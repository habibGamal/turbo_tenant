import { test, expect } from '../../helpers/live-fixture';

test.describe('CatalogProducts Suite 01: Storefront Sections Display', () => {
  test('renders homepage sections and product cards with valid metadata', async ({ page, theme }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    // Verify sections container
    const sections = theme.catalog.getSections();
    await expect(sections.first()).toBeVisible({ timeout: 10000 });
    const sectionCount = await sections.count();
    expect(sectionCount).toBeGreaterThan(0);

    // Verify first section title
    const firstSection = theme.catalog.getSection(0);
    const titleLocator = theme.catalog.getSectionTitle(firstSection);
    await expect(titleLocator).toBeVisible();
    const titleText = await titleLocator.textContent();
    expect(titleText?.trim().length).toBeGreaterThan(0);

    // Verify product cards inside section
    const productCards = firstSection.locator('[data-testid="product-card"]');
    await expect(productCards.first()).toBeVisible();
    const cardCount = await productCards.count();
    expect(cardCount).toBeGreaterThan(0);

    // Check first product title and price
    const firstCard = productCards.first();
    const productTitle = theme.catalog.getProductTitle(firstCard);
    await expect(productTitle).toBeVisible();
    const productPrice = theme.catalog.getProductPrice(firstCard);
    await expect(productPrice).toBeVisible();

    const priceText = await productPrice.textContent();
    expect(priceText).toMatch(/\d+(\.\d{2})?\s*(EGP|ج\.م)/);
  });

  test('renders sale badges on discounted products', async ({ page, theme }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    const productCards = theme.catalog.getProductCards();
    await expect(productCards.first()).toBeVisible({ timeout: 10000 });

    // Find card with discount badge if present
    const saleBadges = page.locator('[data-testid="product-card"] .bg-destructive, [data-testid="product-card"] span:has-text("Sale"), [data-testid="product-card"] span:has-text("خصم")');
    const saleCount = await saleBadges.count();

    if (saleCount > 0) {
      await expect(saleBadges.first()).toBeVisible();
    }
  });
});
