import { test, expect, register, login, logout, resetLink, resetLinks, openReset, expectLoginAfterReset, expectProfile } from '../../auth/fixture';
test.use({ trace: 'off', screenshot: 'off', video: 'off' });

test('registers a customer and keeps the session after refresh', async ({ page, auth }) => {
  await register(page, auth.email);
  await expect(page.locator('#email')).toHaveValue(auth.email);
  expect(auth.inspect().admin).toBe(false);
  await page.reload();
  await expect(page.locator('#email')).toHaveValue(auth.email);
});

test('signs in, logs out, and protects the profile after logout', async ({ page, auth }) => {
  auth.create();
  await login(page, auth.email);
  await expect(page.locator('#email')).toHaveValue(auth.email);
  await logout(page);
  await page.goto('/profile');
  await expect(page).toHaveURL(/\/login/);
});

test('a guest is sent to login and returns to profile after signing in', async ({ page, auth }) => {
  auth.create();
  await page.goto('/profile');
  await expect(page).toHaveURL(/\/login/);
  await page.goto('/login?redirect=/profile');
  await page.locator('#email').fill(auth.email);
  await page.locator('#password').fill(auth.password);
  await page.locator('form button[type=submit]').click();
  await expect(page).toHaveURL(/\/profile$/);
  await expectProfile(page, auth.email);
});

for (const [label, invalid] of [
  ['wrong password', 'WrongPassword123!'],
  ['empty password', ''],
]) {
  test(`login rejects ${label} and stays signed out`, async ({ page, auth }) => {
    auth.create();
    await page.goto('/login');
    await page.locator('#email').fill(auth.email);
    await page.locator('#password').fill(invalid);
    await page.locator('form button[type=submit]').click();
    await expect(page.locator('form .text-destructive').first()).toBeVisible();
    await page.goto('/profile');
    await expect(page).toHaveURL(/\/login/);
  });
}

for (const [label, field, value] of [
  ['missing name', 'name', ''],
  ['missing email', 'email', ''],
  ['short password', 'password', '123'],
  ['mismatched confirmation', 'password_confirmation', 'different'],
] as const) {
  test(`registration rejects ${label}`, async ({ page, auth }) => {
    await page.goto('/register');
    for (const [key, initial] of Object.entries({ name: 'Auth E2E', email: auth.email, password: auth.password, password_confirmation: auth.password })) {
      await page.locator(`#${key}`).fill(key === field ? value : initial);
    }
    await page.locator('form button[type=submit]').click();
    await expect(page.locator('form .text-destructive').first()).toBeVisible();
    await expect(page).toHaveURL(/\/register/);
  });
}

test('registration rejects an email already in use', async ({ page, auth }) => {
  auth.create();
  await page.goto('/register');
  await page.locator('#name').fill('Duplicate');
  await page.locator('#email').fill(auth.email);
  await page.locator('#password').fill(auth.password);
  await page.locator('#password_confirmation').fill(auth.password);
  await page.locator('form button[type=submit]').click();
  await expect(page.locator('form .text-destructive').first()).toBeVisible();
  await expect(page).toHaveURL(/\/register/);
});

test('uppercase registration email is rejected and the lowercase correction succeeds', async ({ page, auth }) => {
  await page.goto('/register?redirect=/profile');
  await page.locator('#name').fill('Auth E2E Customer');
  await page.locator('#email').fill(auth.email.toUpperCase());
  await page.locator('#password').fill(auth.password);
  await page.locator('#password_confirmation').fill(auth.password);
  await page.locator('form button[type=submit]').click();
  await expect(page.locator('form .text-destructive').first()).toBeVisible();
  await page.locator('#email').fill(auth.email);
  await page.locator('form button[type=submit]').click();
  await expect(page).toHaveURL(/\/profile$/);
  await expectProfile(page, auth.email);
});

test('registration rejects an external redirect but creates a usable customer', async ({ page, auth, baseURL }) => {
  await page.goto(`/register?redirect=${encodeURIComponent('https://example.com/')}`);
  await page.locator('#name').fill('Auth E2E Customer');
  await page.locator('#email').fill(auth.email);
  await page.locator('#password').fill(auth.password);
  await page.locator('#password_confirmation').fill(auth.password);
  await page.locator('form button[type=submit]').click();
  await expect(page).toHaveURL(new URL('/', baseURL).href);
  await logout(page);
  await login(page, auth.email);
});

test('a corrected password signs in after a visible credential error', async ({ page, auth }) => {
  auth.create();
  await page.goto('/login?redirect=/profile');
  await page.locator('#email').fill(auth.email);
  await page.locator('#password').fill('WrongPassword123!');
  await page.locator('form button[type=submit]').click();
  await expect(page.locator('form .text-destructive').first()).toBeVisible();
  await page.locator('#password').fill(auth.password);
  await page.locator('form button[type=submit]').click();
  await expect(page).toHaveURL(/\/profile$/);
  await expectProfile(page, auth.email);
});

test('forgot password validates blank and malformed email', async ({ page }) => {
  await page.goto('/forgot-password');
  await page.locator('form button[type=submit]').click();
  await expect(page.locator('form .text-destructive').first()).toBeVisible();
  await page.locator('#email').fill('not-an-email');
  await page.locator('form button[type=submit]').click();
  await expect(page.locator('#email:invalid')).toBeVisible();
});

test('repeated wrong passwords lock out even a subsequent correct password', async ({ page, auth }) => {
  auth.create();
  await page.goto('/login');
  await page.locator('#email').fill(auth.email);
  await page.locator('#password').fill('WrongPassword123!');
  for (let attempt = 0; attempt < 5; attempt++) {
    const [response] = await Promise.all([
      page.waitForResponse(r => r.url().includes('/api/tenant/auth/login') && r.request().method() === 'POST'),
      page.locator('form button[type=submit]').click(),
    ]);
    expect(response.status()).toBe(422);
  }
  await page.locator('#password').fill(auth.password);
  const [response] = await Promise.all([
    page.waitForResponse(r => r.url().includes('/api/tenant/auth/login') && r.request().method() === 'POST'),
    page.locator('form button[type=submit]').click(),
  ]);
  expect(response.status()).toBe(422);
  await expect(page.locator('form .text-destructive').first()).toContainText(/too many|seconds/i);
  await expect(page).toHaveURL(/\/login/);
});

for (const redirect of ['https://example.com/', '//example.com/path', '/\\example.com', '/path\\evil', '/\nexample']) {
  test(`login rejects unsafe redirect ${redirect}`, async ({ page, auth, baseURL }) => {
    auth.create();
    await page.goto(`/login?redirect=${encodeURIComponent(redirect)}`);
    await page.locator('#email').fill(auth.email);
    await page.locator('#password').fill(auth.password);
    await page.locator('form button[type=submit]').click();
    await expect(page).toHaveURL(new URL('/', baseURL).href);
  });
}

test('login and registration links preserve the intended local destination', async ({ page }) => {
  await page.goto('/login?redirect=%2Fprofile');
  await page.locator('a[href^="/register"]').last().click();
  await expect(page).toHaveURL(/\/register\?redirect=%2Fprofile/i);
  await page.locator('a[href^="/login"]').last().click();
  await expect(page).toHaveURL(/\/login\?redirect=%2Fprofile/i);
});

test('desktop account entry and password recovery links open the expected forms', async ({ page }) => {
  await page.goto('/');
  await page.getByRole('button', { name: 'Login', exact: true }).first().click();
  await expect(page).toHaveURL(/\/login$/);
  await page.locator('a[href="/forgot-password"]').click();
  await expect(page).toHaveURL(/\/forgot-password$/);
  await page.locator('a[href="/login"]').last().click();
  await expect(page).toHaveURL(/\/login$/);
});

test('login preserves a local destination with query and fragment', async ({ page, auth, baseURL }) => {
  auth.create();
  const destination = '/profile?tab=profile#top';
  await page.goto(`/login?redirect=${encodeURIComponent(destination)}`);
  await page.locator('#email').fill(auth.email);
  await page.locator('#password').fill(auth.password);
  await page.locator('form button[type=submit]').click();
  await expect(page).toHaveURL(new URL(destination, baseURL).href);
  await expectProfile(page, auth.email);
});

test('login password visibility can be toggled', async ({ page }) => {
  await page.goto('/login');
  const input = page.locator('#password');
  await input.fill('VisibleSecret');
  await expect(input).toHaveAttribute('type', 'password');
  await input.locator('..').locator('button').click();
  await expect(input).toHaveAttribute('type', 'text');
  await input.locator('..').locator('button').click();
  await expect(input).toHaveAttribute('type', 'password');
});

test('Google entry point hands off to the configured server route', async ({ page }) => {
  await page.goto('/login');
  await expect(page.locator('a[href^="/auth/google"]')).toHaveAttribute('href', '/auth/google');
});

test('forgot password rejects an unknown email', async ({ page, auth }) => {
  await page.goto('/forgot-password');
  await page.locator('#email').fill(auth.email);
  await page.locator('form button[type=submit]').click();
  await expect(page.locator('form .text-destructive')).toBeVisible();
});

test('reset rejects an invalid token', async ({ page, auth }) => {
  auth.create();
  await page.goto(`/reset-password/invalid-token?email=${encodeURIComponent(auth.email)}`);
  await page.locator('#password').fill('NewValidPass123!');
  await page.locator('#password_confirmation').fill('NewValidPass123!');
  await page.locator('form button[type=submit]').click();
  await expect(page.locator('form .text-destructive').first()).toBeVisible();
  await login(page, auth.email);
});

test('a real reset notification changes the password and the link cannot be reused', async ({ page, auth }) => {
  test.setTimeout(90_000);
  auth.create();
  await page.goto('/forgot-password');
  await page.locator('#email').fill(auth.email);
  await page.locator('form button[type=submit]').click();
  await expect(page.locator('form .bg-emerald-50')).toBeVisible();
  const link = resetLink(auth.mailLog, auth.email, auth.origin);
  await openReset(page, link);
  await page.locator('#password').fill('NewValidPass123!');
  await page.locator('#password_confirmation').fill('NewValidPass123!');
  await page.locator('form button[type=submit]').click();
  await expectLoginAfterReset(page);
  await expect(page.locator('form .bg-emerald-50')).toBeVisible();
  await page.locator('#email').fill(auth.email);
  await page.locator('#password').fill(auth.password);
  await page.locator('form button[type=submit]').click();
  await expect(page.locator('form .text-destructive').first()).toBeVisible();
  await login(page, auth.email, '/profile', 'NewValidPass123!');
  await logout(page);
  await openReset(page, link);
  await page.locator('#password').fill('OtherValidPass123!');
  await page.locator('#password_confirmation').fill('OtherValidPass123!');
  await page.locator('form button[type=submit]').click();
  await expect(page.locator('form .text-destructive').first()).toBeVisible();
});

test('expired reset link is rejected and the original password still works', async ({ page, auth }) => {
  auth.create();
  await page.goto('/forgot-password');
  await page.locator('#email').fill(auth.email);
  await page.locator('form button[type=submit]').click();
  await expect(page.locator('form .bg-emerald-50')).toBeVisible();
  const link = resetLink(auth.mailLog, auth.email, auth.origin);
  auth.expireReset();
  await openReset(page, link);
  await page.locator('#password').fill('NewValidPass123!');
  await page.locator('#password_confirmation').fill('NewValidPass123!');
  await page.locator('form button[type=submit]').click();
  await expect(page.locator('form .text-destructive').first()).toBeVisible();
  await login(page, auth.email);
});

test('a reset token cannot be used for a different customer', async ({ page, auth }) => {
  test.setTimeout(90_000);
  auth.create();
  const otherEmail = auth.anotherEmail();
  auth.create(otherEmail);
  await page.goto('/forgot-password');
  await page.locator('#email').fill(auth.email);
  await page.locator('form button[type=submit]').click();
  await expect(page.locator('form .bg-emerald-50')).toBeVisible();
  const link = new URL(resetLink(auth.mailLog, auth.email, auth.origin));
  link.searchParams.set('email', otherEmail);
  await openReset(page, link.href);
  await expect(page.locator('#email')).toHaveAttribute('readonly', '');
  await page.locator('#password').fill('NewValidPass123!');
  await page.locator('#password_confirmation').fill('NewValidPass123!');
  await page.locator('form button[type=submit]').click();
  await expect(page.locator('form .text-destructive').first()).toBeVisible();
  await login(page, otherEmail);
  await logout(page);
  await login(page, auth.email);
});

test('a second immediate reset request is throttled', async ({ page, auth }) => {
  auth.create();
  await page.goto('/forgot-password');
  await page.locator('#email').fill(auth.email);
  await page.locator('form button[type=submit]').click();
  await expect(page.locator('form .bg-emerald-50')).toBeVisible();
  await page.locator('form button[type=submit]').click();
  await expect(page.locator('form .text-destructive').first()).toBeVisible();
});

test('reset validation retains a valid token until corrected passwords are submitted', async ({ page, auth }) => {
  auth.create();
  await page.goto('/forgot-password');
  await page.locator('#email').fill(auth.email);
  await page.locator('form button[type=submit]').click();
  await expect(page.locator('form .bg-emerald-50')).toBeVisible();
  const link = resetLink(auth.mailLog, auth.email, auth.origin);
  await openReset(page, link);
  await page.locator('#password').fill('short');
  await page.locator('#password_confirmation').fill('different');
  await page.locator('form button[type=submit]').click();
  await expect(page.locator('form .text-destructive').first()).toBeVisible();
  await page.locator('#password').fill('NewValidPass123!');
  await page.locator('#password_confirmation').fill('NewValidPass123!');
  await page.locator('form button[type=submit]').click();
  await expect(page.locator('#password')).toBeVisible();
  await expectLoginAfterReset(page);
  await login(page, auth.email, '/profile', 'NewValidPass123!');
});

test('a replacement reset link invalidates the first and remains usable', async ({ page, auth }) => {
  test.setTimeout(90_000);
  auth.create();
  await page.goto('/forgot-password');
  await page.locator('#email').fill(auth.email);
  await page.locator('form button[type=submit]').click();
  await expect(page.locator('form .bg-emerald-50')).toBeVisible();
  const first = resetLink(auth.mailLog, auth.email, auth.origin);
  auth.ageReset();
  const [resend] = await Promise.all([
    page.waitForResponse(r => r.url().includes('/api/tenant/auth/forgot-password') && r.request().method() === 'POST'),
    page.locator('form button[type=submit]').click(),
  ]);
  expect(resend.ok()).toBeTruthy();
  await expect(page.locator('form .bg-emerald-50')).toBeVisible();
  const links = resetLinks(auth.mailLog, auth.email, auth.origin);
  expect(links).toHaveLength(2);
  const second = links[1];
  expect(first === second).toBeFalsy();
  await openReset(page, first);
  await page.locator('#password').fill('NewValidPass123!');
  await page.locator('#password_confirmation').fill('NewValidPass123!');
  await page.locator('form button[type=submit]').click();
  await expect(page.locator('form .text-destructive').first()).toBeVisible();
  await openReset(page, second);
  await page.locator('#password').fill('NewValidPass123!');
  await page.locator('#password_confirmation').fill('NewValidPass123!');
  await page.locator('form button[type=submit]').click();
  await expectLoginAfterReset(page);
  await login(page, auth.email, '/profile', 'NewValidPass123!');
});

test('English mobile can request and use a reset link', async ({ page, auth }) => {
  auth.create();
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/forgot-password');
  await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
  await page.locator('#email').fill(auth.email);
  await page.locator('form button[type=submit]').click();
  await expect(page.locator('form .bg-emerald-50')).toBeVisible();
  const link = resetLink(auth.mailLog, auth.email, auth.origin);
  await openReset(page, link);
  await expect(page.locator('#email')).toHaveValue(auth.email);
  await page.locator('#password').fill('NewValidPass123!');
  await page.locator('#password_confirmation').fill('NewValidPass123!');
  await page.locator('form button[type=submit]').click();
  await expectLoginAfterReset(page);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBeTruthy();
});
