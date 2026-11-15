import { test, expect, login, logout, expectProfile } from '../../auth/fixture';

test('remember me restores identity in a fresh browser context with only the recaller cookie', async ({ page, context, browser, auth, baseURL }) => {
  test.setTimeout(90_000);
  auth.create();
  await page.goto('/login?redirect=/profile');
  await page.locator('#email').fill(auth.email);
  await page.locator('#password').fill(auth.password);
  await page.locator('input[name=remember]').check();
  const [response] = await Promise.all([
    page.waitForResponse(r => r.url().includes('/api/tenant/auth/login') && r.request().method() === 'POST'),
    page.locator('form button[type=submit]').click(),
  ]);
  expect(response.request().postDataJSON().remember).toBe(true);
  const emitted = (await response.headersArray()).filter(header => header.name.toLowerCase() === 'set-cookie').map(header => header.value.split('=')[0]);
  await expect(page).toHaveURL(/\/profile$/);
  await expectProfile(page, auth.email);
  const cookies = await context.cookies();
  const recaller = cookies.filter(cookie => cookie.name.startsWith('remember_web_'));
  expect(recaller, `Cookie names: ${cookies.map(cookie => cookie.name).join(', ')}; emitted names: ${emitted.join(', ')}`).toHaveLength(1);
  expect(recaller[0].expires).toBeGreaterThan(Date.now() / 1000);
  const fresh = await browser.newContext({ baseURL });
  try {
    await fresh.addCookies(recaller);
    const second = await fresh.newPage();
    await second.goto('/profile');
    await expect(second.locator('#email')).toHaveValue(auth.email);
  } finally { await fresh.close(); }
});

test('without remember me, a new context is signed out', async ({ page, context, browser, auth, baseURL }) => {
  auth.create();
  await login(page, auth.email);
  await page.reload();
  await expect(page.locator('#email')).toHaveValue(auth.email);
  const keys = await page.evaluate(async () => Object.keys((await (await fetch('/api/tenant/auth/user', { headers: { Accept: 'application/json' } })).json()).user));
  expect(keys).not.toContain('password');
  expect(keys).not.toContain('remember_token');
  expect((await context.cookies()).filter(cookie => cookie.name.startsWith('remember_web_'))).toHaveLength(0);
  const fresh = await browser.newContext({ baseURL });
  try {
    const second = await fresh.newPage();
    await second.goto('/profile');
    await expect(second).toHaveURL(/\/login/);
  } finally { await fresh.close(); }
});

test('logout invalidates the shared session in another tab after reload', async ({ page, context, auth }) => {
  test.setTimeout(90_000);
  auth.create();
  await login(page, auth.email);
  const second = await context.newPage();
  await second.goto('/profile');
  await expect(second.locator('#email')).toHaveValue(auth.email);
  await logout(page);
  await second.reload();
  await expect(second).toHaveURL(/\/login/);
});

test('browser back after logout cannot reopen the profile and login still works', async ({ page, auth }) => {
  auth.create();
  await login(page, auth.email);
  await page.locator('nav a[href="/"]').first().click();
  await page.locator('button[aria-label="User Menu"]').click();
  await page.getByRole('menuitem', { name: 'Profile' }).click();
  await expect(page).toHaveURL(/\/profile$/);
  await expectProfile(page, auth.email);
  await logout(page);
  await page.goBack();
  await expect(page).toHaveURL(/\/login(?:\?redirect=\/profile)?$/);
  await login(page, auth.email);
});

test('guest protected account endpoints reject writes with a real CSRF session', async ({ page }) => {
  await page.goto('/login');
  await page.waitForLoadState('networkidle');
  const result = await page.evaluate(async () => {
    const csrf = decodeURIComponent(document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))?.slice('XSRF-TOKEN='.length) || '');
    const headers = { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' };
    const user = await fetch('/api/tenant/auth/user', { headers });
    const profile = await fetch('/api/tenant/auth/profile', { method: 'PATCH', headers, body: JSON.stringify({ name: 'Unauthorized', email: 'unauthorized@example.test' }) });
    const password = await fetch('/api/tenant/auth/password', { method: 'PUT', headers, body: JSON.stringify({}) });
    const addresses = await fetch('/api/locations-delivery/addresses', { headers });
    const createAddress = await fetch('/api/locations-delivery/addresses', { method: 'POST', headers, body: JSON.stringify({}) });
    return { user: await user.json(), profile: profile.status, password: password.status, addresses: addresses.status, createAddress: createAddress.status };
  });
  expect(result.user.user).toBeNull();
  expect(result.profile).toBe(401);
  expect(result.password).toBe(401);
  expect(result.addresses).toBe(401);
  expect(result.createAddress).toBe(401);
});

test.describe('Arabic mobile', () => {
test.use({ language: 'ar' });
test('registration shows a working RTL profile', async ({ page, auth }) => {
  test.setTimeout(90_000);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/');
  await page.locator('nav a[href="/login"]').last().click();
  await page.locator('a[href^="/register"]').last().click();
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
  await page.locator('#name').fill('عميل الاختبار');
  await page.locator('#email').fill(auth.email);
  await page.locator('#password').fill(auth.password);
  await page.locator('#password_confirmation').fill(auth.password);
  await page.locator('form button[type=submit]').click();
  await expect(page).toHaveURL(new URL('/', auth.origin).href);
  await page.locator('nav a[href="/profile"]').last().click();
  await expect(page).toHaveURL(/\/profile$/);
  await expectProfile(page, auth.email);
  await expect(page.locator('#email')).toHaveAttribute('dir', 'ltr');
  await expect(page.locator('#phone')).toHaveAttribute('dir', 'ltr');
  await page.locator('#name').fill('عميل جديد');
  await page.locator('form button').click();
  await expect(page.getByText('تم الحفظ.', { exact: true })).toBeVisible();
  await page.reload();
  await expect(page.locator('#name')).toHaveValue('عميل جديد');
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBeTruthy();
  await page.getByRole('tab').nth(1).click();
  await expect(page.locator('#current_password')).toBeVisible();
  await logout(page);
});
});
