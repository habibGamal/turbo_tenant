# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: auth\session.live.spec.ts >> Arabic mobile >> registration shows a working RTL profile
- Location: tests\e2e\plugins\auth\session.live.spec.ts:96:1

# Error details

```
Error: expect(locator).toHaveAttribute(expected) failed

Locator:  locator('#email')
Expected: "ltr"
Received: ""
Timeout:  10000ms

Call log:
  - Expect "toHaveAttribute" locator('#email') with timeout 10000ms
  - waiting for locator('#email')
    23 × locator resolved to <input id="email" required="" type="email" autocomplete="username" value="auth-e2e-74843d18-9e38-45b9-8fe7-43203543510a@example.test" class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 md:text-sm mt-1 block w-ful…/>
       - unexpected value "null"

```

```yaml
- textbox "البريد الإلكتروني": auth-e2e-74843d18-9e38-45b9-8fe7-43203543510a@example.test
```

# Test source

```ts
  13  |   ]);
  14  |   expect(response.request().postDataJSON().remember).toBe(true);
  15  |   const emitted = (await response.headersArray()).filter(header => header.name.toLowerCase() === 'set-cookie').map(header => header.value.split('=')[0]);
  16  |   await expect(page).toHaveURL(/\/profile$/);
  17  |   await expectProfile(page, auth.email);
  18  |   const cookies = await context.cookies();
  19  |   const recaller = cookies.filter(cookie => cookie.name.startsWith('remember_web_'));
  20  |   expect(recaller, `Cookie names: ${cookies.map(cookie => cookie.name).join(', ')}; emitted names: ${emitted.join(', ')}`).toHaveLength(1);
  21  |   expect(recaller[0].expires).toBeGreaterThan(Date.now() / 1000);
  22  |   const fresh = await browser.newContext({ baseURL });
  23  |   try {
  24  |     await fresh.addCookies(recaller);
  25  |     const second = await fresh.newPage();
  26  |     await second.goto('/profile');
  27  |     await expect(second.locator('#email')).toHaveValue(auth.email);
  28  |   } finally { await fresh.close(); }
  29  | });
  30  | 
  31  | test('without remember me, a new context is signed out', async ({ page, context, browser, auth, baseURL }) => {
  32  |   auth.create();
  33  |   await login(page, auth.email);
  34  |   await page.reload();
  35  |   await expect(page.locator('#email')).toHaveValue(auth.email);
  36  |   const keys = await page.evaluate(async () => Object.keys((await (await fetch('/api/tenant/auth/user', { headers: { Accept: 'application/json' } })).json()).user));
  37  |   expect(keys).not.toContain('password');
  38  |   expect(keys).not.toContain('remember_token');
  39  |   expect((await context.cookies()).filter(cookie => cookie.name.startsWith('remember_web_'))).toHaveLength(0);
  40  |   const fresh = await browser.newContext({ baseURL });
  41  |   try {
  42  |     const second = await fresh.newPage();
  43  |     await second.goto('/profile');
  44  |     await expect(second).toHaveURL(/\/login/);
  45  |   } finally { await fresh.close(); }
  46  | });
  47  | 
  48  | test('logout invalidates the shared session in another tab after reload', async ({ page, context, auth }) => {
  49  |   test.setTimeout(90_000);
  50  |   auth.create();
  51  |   await login(page, auth.email);
  52  |   const second = await context.newPage();
  53  |   await second.goto('/profile');
  54  |   await expect(second.locator('#email')).toHaveValue(auth.email);
  55  |   await logout(page);
  56  |   await second.reload();
  57  |   await expect(second).toHaveURL(/\/login/);
  58  | });
  59  | 
  60  | test('browser back after logout cannot reopen the profile and login still works', async ({ page, auth }) => {
  61  |   auth.create();
  62  |   await login(page, auth.email);
  63  |   await page.locator('nav a[href="/"]').first().click();
  64  |   await page.locator('button[aria-label="User Menu"]').click();
  65  |   await page.getByRole('menuitem', { name: 'Profile' }).click();
  66  |   await expect(page).toHaveURL(/\/profile$/);
  67  |   await expectProfile(page, auth.email);
  68  |   await logout(page);
  69  |   await page.goBack();
  70  |   await expect(page).toHaveURL(/\/login(?:\?redirect=\/profile)?$/);
  71  |   await login(page, auth.email);
  72  | });
  73  | 
  74  | test('guest protected account endpoints reject writes with a real CSRF session', async ({ page }) => {
  75  |   await page.goto('/login');
  76  |   await page.waitForLoadState('networkidle');
  77  |   const result = await page.evaluate(async () => {
  78  |     const csrf = decodeURIComponent(document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))?.slice('XSRF-TOKEN='.length) || '');
  79  |     const headers = { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' };
  80  |     const user = await fetch('/api/tenant/auth/user', { headers });
  81  |     const profile = await fetch('/api/tenant/auth/profile', { method: 'PATCH', headers, body: JSON.stringify({ name: 'Unauthorized', email: 'unauthorized@example.test' }) });
  82  |     const password = await fetch('/api/tenant/auth/password', { method: 'PUT', headers, body: JSON.stringify({}) });
  83  |     const addresses = await fetch('/api/locations-delivery/addresses', { headers });
  84  |     const createAddress = await fetch('/api/locations-delivery/addresses', { method: 'POST', headers, body: JSON.stringify({}) });
  85  |     return { user: await user.json(), profile: profile.status, password: password.status, addresses: addresses.status, createAddress: createAddress.status };
  86  |   });
  87  |   expect(result.user.user).toBeNull();
  88  |   expect(result.profile).toBe(401);
  89  |   expect(result.password).toBe(401);
  90  |   expect(result.addresses).toBe(401);
  91  |   expect(result.createAddress).toBe(401);
  92  | });
  93  | 
  94  | test.describe('Arabic mobile', () => {
  95  | test.use({ language: 'ar' });
  96  | test('registration shows a working RTL profile', async ({ page, auth }) => {
  97  |   test.setTimeout(90_000);
  98  |   await page.setViewportSize({ width: 390, height: 844 });
  99  |   await page.goto('/');
  100 |   await page.locator('nav a[href="/login"]').last().click();
  101 |   await page.locator('a[href^="/register"]').last().click();
  102 |   await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  103 |   await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
  104 |   await page.locator('#name').fill('عميل الاختبار');
  105 |   await page.locator('#email').fill(auth.email);
  106 |   await page.locator('#password').fill(auth.password);
  107 |   await page.locator('#password_confirmation').fill(auth.password);
  108 |   await page.locator('form button[type=submit]').click();
  109 |   await expect(page).toHaveURL(new URL('/', auth.origin).href);
  110 |   await page.locator('nav a[href="/profile"]').last().click();
  111 |   await expect(page).toHaveURL(/\/profile$/);
  112 |   await expectProfile(page, auth.email);
> 113 |   await expect(page.locator('#email')).toHaveAttribute('dir', 'ltr');
      |                                        ^ Error: expect(locator).toHaveAttribute(expected) failed
  114 |   await expect(page.locator('#phone')).toHaveAttribute('dir', 'ltr');
  115 |   await page.locator('#name').fill('عميل جديد');
  116 |   await page.locator('form button').click();
  117 |   await expect(page.getByText('تم الحفظ.', { exact: true })).toBeVisible();
  118 |   await page.reload();
  119 |   await expect(page.locator('#name')).toHaveValue('عميل جديد');
  120 |   expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBeTruthy();
  121 |   await page.getByRole('tab').nth(1).click();
  122 |   await expect(page.locator('#current_password')).toBeVisible();
  123 |   await logout(page);
  124 | });
  125 | });
  126 | 
```