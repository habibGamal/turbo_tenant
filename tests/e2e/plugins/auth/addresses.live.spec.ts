import { type Page, type Locator } from '@playwright/test';
import { test, expect, login } from '../../auth/fixture';

async function addressTab(page: Page) {
  await page.getByRole('tab').nth(2).click();
  await expect(page.getByRole('button', { name: 'Add New Address' })).toBeVisible();
}
async function fillAddress(dialog: Locator, street: string, isDefault = false) {
  const selects = dialog.getByRole('combobox');
  await expect(selects.nth(1)).toBeDisabled();
  await selects.nth(0).click();
  await dialog.page().getByRole('option', { name: 'E2E Cairo' }).click();
  await selects.nth(1).click();
  await dialog.page().getByRole('option', { name: /E2E Downtown/ }).click();
  const field = (label: string) => dialog.locator('div.space-y-2').filter({ hasText: new RegExp(`^${label}`) }).locator('input');
  await field('Street').fill(street);
  await field('Building').fill('4');
  await dialog.locator('input[type=tel]').fill('1012345678');
  await field('Floor').fill('1');
  await field('Apartment').fill('2');
  if (isDefault) await dialog.locator('input[type=checkbox]').check();
}
async function createAddress(page: Page, street: string, isDefault = false) {
  await page.getByRole('button', { name: 'Add New Address' }).click();
  const dialog = page.getByRole('dialog');
  await fillAddress(dialog, street, isDefault);
  const [response] = await Promise.all([
    page.waitForResponse(r => r.url().includes('/api/locations-delivery/addresses') && r.request().method() === 'POST'),
    dialog.locator('button[type=submit]').click(),
  ]);
  await expect(dialog).toBeHidden();
  await expect(page.getByText(`${street}, 4`)).toBeVisible();
  return (await response.json()).data.id as number;
}

test('adds a delivery address and retains it after reload', async ({ page, auth }) => {
  auth.create();
  await login(page, auth.email);
  await addressTab(page);
  await createAddress(page, 'E2E First Street');
  await page.reload();
  await addressTab(page);
  await expect(page.getByText('E2E First Street, 4')).toBeVisible();
});

test('edits an address and keeps the updated values', async ({ page, auth }) => {
  auth.create();
  await login(page, auth.email);
  await addressTab(page);
  await createAddress(page, 'E2E Old Street');
  const card = page.locator('div.border.rounded-lg').filter({ hasText: 'E2E Old Street' });
  await card.locator('button').first().click();
  const dialog = page.getByRole('dialog');
  await dialog.locator('div.space-y-2').filter({ hasText: 'Street' }).locator('input').fill('E2E New Street');
  await dialog.locator('textarea').fill('Leave at door');
  await dialog.locator('button[type=submit]').click();
  await expect(page.getByText('E2E New Street, 4')).toBeVisible();
  await page.reload();
  await addressTab(page);
  await expect(page.getByText('E2E New Street, 4')).toBeVisible();
  await page.locator('div.border.rounded-lg').filter({ hasText: 'E2E New Street' }).locator('button').first().click();
  await expect(page.getByRole('dialog').locator('textarea')).toHaveValue('Leave at door');
  await expect(page.getByRole('dialog').locator('input[type=tel]')).toHaveValue(/\+20\s*10\s*12345678/);
});

test('setting a second default address replaces the first default', async ({ page, auth }) => {
  auth.create();
  await login(page, auth.email);
  await addressTab(page);
  await createAddress(page, 'E2E First Street', true);
  await createAddress(page, 'E2E Second Street', true);
  await page.reload();
  await addressTab(page);
  const cards = page.locator('div.border.rounded-lg');
  await expect(cards.filter({ hasText: 'E2E Second Street' })).toContainText('Default');
  await expect(cards.filter({ hasText: 'E2E First Street' })).not.toContainText('Default');
});

test('canceling then confirming address deletion retains then removes it', async ({ page, auth }) => {
  auth.create();
  await login(page, auth.email);
  await addressTab(page);
  await createAddress(page, 'E2E Delete Street');
  const card = page.locator('div.border.rounded-lg').filter({ hasText: 'E2E Delete Street' });
  page.once('dialog', dialog => dialog.dismiss());
  await card.locator('button').last().click();
  await expect(card).toBeVisible();
  page.once('dialog', dialog => dialog.accept());
  await card.locator('button').last().click();
  await expect(card).toBeHidden();
  await page.reload();
  await addressTab(page);
  await expect(page.getByText('E2E Delete Street, 4')).toBeHidden();
});

test('address required fields prevent submitting an incomplete form', async ({ page, auth }) => {
  auth.create();
  await login(page, auth.email);
  await addressTab(page);
  await page.getByRole('button', { name: 'Add New Address' }).click();
  const dialog = page.getByRole('dialog');
  await dialog.locator('button[type=submit]').click();
  await expect(dialog).toBeVisible();
  await expect(dialog.locator('input:invalid').first()).toBeVisible();
  await fillAddress(dialog, 'E2E Corrected Street');
  await dialog.locator('button[type=submit]').click();
  await expect(page.getByText('E2E Corrected Street, 4')).toBeVisible();
});

test('address rejects an invalid Egyptian phone and accepts a correction', async ({ page, auth }) => {
  auth.create();
  await login(page, auth.email);
  await addressTab(page);
  await page.getByRole('button', { name: 'Add New Address' }).click();
  const dialog = page.getByRole('dialog');
  await fillAddress(dialog, 'E2E Phone Street');
  await dialog.locator('input[type=tel]').fill('123');
  await dialog.locator('button[type=submit]').click();
  await expect(dialog.locator('.text-destructive').first()).toBeVisible();
  await dialog.locator('input[type=tel]').fill('1012345678');
  await dialog.locator('button[type=submit]').click();
  await expect(page.getByText('E2E Phone Street, 4')).toBeVisible();
});

test('a different customer cannot view, edit or delete the owner address', async ({ page, browser, baseURL, auth }) => {
  test.setTimeout(90_000);
  auth.create();
  const otherEmail = auth.anotherEmail();
  auth.create(otherEmail);
  await login(page, auth.email);
  await addressTab(page);
  const addressId = await createAddress(page, 'E2E Private Street');
  const other = await browser.newContext({ baseURL });
  try {
    const second = await other.newPage();
    await second.addInitScript(() => localStorage.setItem('i18nextLng', 'en'));
    await login(second, otherEmail);
    await addressTab(second);
    await expect(second.getByText('E2E Private Street, 4')).toHaveCount(0);
    const statuses = await second.evaluate(async id => {
      const cookie = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='));
      const csrf = decodeURIComponent(cookie?.slice('XSRF-TOKEN='.length) || '');
      const headers = { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' };
      const url = `/api/locations-delivery/addresses/${id}`;
      const payload = { area_id: 1, street: 'Changed', building: '4', phone_number: '+201012345678', floor: '1', apartment: '2' };
      const patch = await fetch(url, { method: 'PATCH', headers, body: JSON.stringify(payload) });
      const remove = await fetch(url, { method: 'DELETE', headers });
      return [patch.status, remove.status];
    }, addressId);
    expect(statuses).toEqual([403, 403]);
    await page.reload();
    await addressTab(page);
    await expect(page.getByText('E2E Private Street, 4')).toBeVisible();
  } finally { await other.close(); }
});
