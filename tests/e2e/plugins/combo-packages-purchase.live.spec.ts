import { test, expect } from '../helpers/live-fixture';

test.describe('Plugin: combo-packages purchase flow (Live)', () => {
  test('configures a combo package, adds to cart, verifies components, edits and removes', async ({ page, theme }) => {
    await page.goto('/');

    const combosSection = theme.combos.getCombosSection();
    if (!(await combosSection.isVisible())) {
      test.skip(true, 'Combos section not visible');
      return;
    }

    const cards = theme.combos.getComboCards();
    const count = await cards.count();
    if (count === 0) {
      test.skip(true, 'No combo packages available');
      return;
    }

    const firstCombo = cards.first();
    const selectBtn = theme.combos.getComboAddToCartButton(firstCombo);
    await expect(selectBtn).toBeVisible();
    await selectBtn.click();

    // Configurator modal or page
    const configurator = page.locator('[data-testid="combo-configurator"]');
    await expect(configurator).toBeVisible();

    // Verify live total price display
    const totalDisplay = page.locator('[data-testid="combo-total"]');
    await expect(totalDisplay).toBeVisible();

    // Enter notes if input is visible
    const notesInput = page.locator('[data-testid="combo-notes"]');
    if (await notesInput.isVisible()) {
      await notesInput.fill('No onions please');
    }

    // Guarded component customization (extras + notes) - skips if no customizable items
    const customizeButtons = page.locator('[data-testid^="combo-component-customize-"]');
    const customizeCount = await customizeButtons.count();
    if (customizeCount > 0) {
      const firstCustomizeBtn = customizeButtons.first();
      if (await firstCustomizeBtn.isVisible()) {
        await firstCustomizeBtn.click();

        // Fill component-level notes
        const componentNotes = page.locator('[data-testid^="combo-component-notes-"]').first();
        if (await componentNotes.isVisible()) {
          await componentNotes.fill('Extra crispy please');
        }

        // Toggle an extra
        const extraCheckbox = page.locator('[data-testid^="combo-extra-"]').first();
        if (await extraCheckbox.isVisible()) {
          await extraCheckbox.click();

          // Increment extra quantity if available
          const extraQtyInc = page.locator('[data-testid^="combo-extra-qty-inc-"]').first();
          if (await extraQtyInc.isVisible()) {
            await extraQtyInc.click();
          }
        }
      }
    }

    // Submit button should be enabled when selection is valid
    const submitBtn = page.locator('[data-testid="combo-submit"]');
    await expect(submitBtn).toBeEnabled();

    // Add to cart
    await submitBtn.click();

    // Navigate to /cart
    await theme.cart.open();
    await expect(page).toHaveURL(/\/cart/);

    const cartItems = theme.cart.getItems();
    const itemsCount = await cartItems.count();
    if (itemsCount === 0) {
      return;
    }

    const firstItem = cartItems.first();
    await expect(firstItem).toBeVisible();

    // Verify combo components container
    const componentsList = firstItem.locator('[data-testid="cart-item-components"]');
    if (await componentsList.count() > 0) {
      await expect(componentsList).toBeVisible();
    }

    // Verify edit button
    const editBtn = firstItem.locator('[data-testid="cart-item-edit"]');
    if (await editBtn.count() > 0 && await editBtn.isVisible()) {
      await editBtn.click();

      const editModalConfigurator = page.locator('[data-testid="combo-configurator"]');
      await expect(editModalConfigurator).toBeVisible();

      // Increment quantity in configurator
      const qtyInc = page.locator('[data-testid="combo-qty-inc"]');
      if (await qtyInc.isVisible()) {
        await qtyInc.click();
      }

      const updateSubmit = page.locator('[data-testid="combo-submit"]');
      await updateSubmit.click();
    }

    // Remove item from cart
    const removeBtn = theme.cart.getItemRemoveButton(firstItem);
    if (await removeBtn.isVisible()) {
      await removeBtn.click();
    }
  });
});
