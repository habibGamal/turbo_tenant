import { test, expect, login, logout } from '../../auth/fixture';

test('profile name change persists after refresh', async ({ page, auth }) => {
  auth.create();
  await login(page, auth.email);
  await page.locator('#name').fill('Updated Customer');
  await page.locator('form button').click();
  await expect(page.getByText('Saved.', { exact: true })).toBeVisible();
  await page.reload();
  await expect(page.locator('#name')).toHaveValue('Updated Customer');
  await expect(page.locator('#email')).toHaveValue(auth.email);
  expect(auth.inspect().verified).toBe(true);
});

test('profile email change allows login with the new address', async ({ page, auth }) => {
  auth.create();
  const next = auth.anotherEmail();
  await login(page, auth.email);
  await page.locator('#email').fill(next);
  await page.locator('form button').click();
  await expect(page.getByText('Saved.', { exact: true })).toBeVisible();
  await page.reload();
  await expect(page.locator('#email')).toHaveValue(next);
  expect(auth.inspect(next).verified).toBe(false);
  await logout(page);
  await page.goto('/login');
  await page.locator('#email').fill(auth.email);
  await page.locator('#password').fill(auth.password);
  await page.locator('form button[type=submit]').click();
  await expect(page.locator('form .text-destructive').first()).toBeVisible();
  await login(page, next);
  await expect(page.locator('#email')).toHaveValue(next);
});

test('profile rejects a duplicate email without persisting changes', async ({ page, auth }) => {
  auth.create();
  await login(page, auth.email);
  await page.locator('#name').fill('Should Not Persist');
  await page.locator('#email').fill('admin@example.com');
  await page.locator('form button').click();
  await expect(page.locator('form .text-destructive')).toBeVisible();
  await page.reload();
  await expect(page.locator('#name')).toHaveValue('Auth E2E Customer');
  await expect(page.locator('#email')).toHaveValue(auth.email);
});

test('profile rejects a blank name', async ({ page, auth }) => {
  auth.create();
  await login(page, auth.email);
  await page.locator('#name').fill('');
  await page.locator('form button').click();
  await expect(page.locator('#name:invalid')).toBeVisible();
  await page.reload();
  await expect(page.locator('#name')).toHaveValue('Auth E2E Customer');
});

test('profile rejects empty, malformed and uppercase email and retains the identity', async ({ page, auth }) => {
  auth.create();
  await login(page, auth.email);
  const email = page.locator('#email');
  await email.fill('');
  await page.locator('form button').click();
  await expect(page.locator('#email:invalid')).toBeVisible();
  await email.fill('not-an-email');
  await page.locator('form button').click();
  await expect(page.locator('#email:invalid')).toBeVisible();
  await email.fill(auth.email.toUpperCase());
  await page.locator('form button').click();
  await expect(page.locator('form .text-destructive').first()).toBeVisible();
  await page.reload();
  await expect(page.locator('#email')).toHaveValue(auth.email);
});

test('profile phone persists after saving', async ({ page, auth }) => {
  auth.create();
  await login(page, auth.email);
  await page.locator('#phone').fill('01012345678');
  await page.locator('form button').click();
  await expect(page.getByText('Saved.', { exact: true })).toBeVisible();
  await page.reload();
  await expect(page.locator('#email')).toHaveValue(auth.email);
  test.fail(true, 'Known gap: profile request and controller do not persist the phone field.');
  await expect(page.locator('#phone')).toHaveValue('01012345678');
});

test('password change preserves the session and accepts the new password after logout', async ({ page, auth }) => {
  auth.create();
  await login(page, auth.email);
  await page.getByRole('tab').nth(1).click();
  await page.locator('#current_password').fill(auth.password);
  await page.locator('#password').fill('NewValidPass123!');
  await page.locator('#password_confirmation').fill('NewValidPass123!');
  await page.locator('form button').click();
  await expect(page.getByText('Saved.', { exact: true })).toBeVisible();
  await expect(page.locator('#current_password')).toHaveValue('');
  await page.reload();
  await expect(page.locator('#email')).toHaveValue(auth.email);
  await logout(page);
  await page.goto('/login');
  await page.locator('#email').fill(auth.email);
  await page.locator('#password').fill(auth.password);
  await page.locator('form button[type=submit]').click();
  await expect(page.locator('form .text-destructive').first()).toBeVisible();
  await login(page, auth.email, '/profile', 'NewValidPass123!');
});

for (const [label, current, next, confirmation] of [
  ['wrong current password', 'wrong', 'NewValidPass123!', 'NewValidPass123!'],
  ['short password', 'ValidPass123!', '123', '123'],
  ['mismatched confirmation', 'ValidPass123!', 'NewValidPass123!', 'different'],
]) {
  test(`password change rejects ${label}`, async ({ page, auth }) => {
    auth.create();
    await login(page, auth.email);
    await page.getByRole('tab').nth(1).click();
    await page.locator('#current_password').fill(current);
    await page.locator('#password').fill(next);
    await page.locator('#password_confirmation').fill(confirmation);
    await page.locator('form button').click();
    await expect(page.locator('form .text-destructive').first()).toBeVisible();
    if (label === 'wrong current password') {
      await expect(page.locator('#current_password')).toHaveValue('');
      await expect(page.locator('#current_password')).toBeFocused();
    } else {
      await expect(page.locator('#password')).toHaveValue('');
      await expect(page.locator('#password_confirmation')).toHaveValue('');
      await expect(page.locator('#password')).toBeFocused();
    }
    await page.locator('#current_password').fill(auth.password);
    await page.locator('#password').fill('NewValidPass123!');
    await page.locator('#password_confirmation').fill('NewValidPass123!');
    await page.locator('form button').click();
    await expect(page.getByText('Saved.', { exact: true })).toBeVisible();
    await logout(page);
    await login(page, auth.email, '/profile', 'NewValidPass123!');
  });
}
