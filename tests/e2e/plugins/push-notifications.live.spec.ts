import { test, expect } from '../helpers/live-fixture';

test.describe('Plugin: push-notifications (Live)', () => {
  test('verifies notifications trigger and interaction', async ({ page, theme }) => {
    await page.goto('/');

    const notifTrigger = theme.notifications.getTrigger();
    if (await notifTrigger.isVisible()) {
      await notifTrigger.click();

      // Check dropdown dialog opens
      const dropdown = theme.notifications.getDropdown();
      await expect(dropdown).toBeVisible({ timeout: 5000 });
    }
  });
});
