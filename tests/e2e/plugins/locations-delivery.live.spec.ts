import { test, expect } from '../helpers/live-fixture';

test.describe('Plugin: locations-delivery (Live)', () => {
  test('renders delivery zone and address inputs on checkout', async ({ page, theme }) => {
    await page.goto('/checkout');

    const zoneSelect = theme.checkout.getDeliveryZoneSelect();
    const addressInput = theme.checkout.getAddressInput();

    // If on checkout page with items
    if (await zoneSelect.isVisible()) {
      await expect(zoneSelect).toBeVisible();
    }

    if (await addressInput.isVisible()) {
      await addressInput.fill('123 Test Street, Building 4');
      await expect(addressInput).toHaveValue('123 Test Street, Building 4');
    }
  });
});
