import { Page } from '@playwright/test';

/**
 * Logs in a customer on the live tenant storefront.
 * Uses browser-native fetch to leverage Chrome's host-resolver-rules and session cookie jar.
 */
export async function loginAsCustomer(
  page: Page,
  email: string = 'admin@example.com',
  password: string = 'password'
): Promise<void> {
  if (page.url() === 'about:blank') {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
  }

  const result = await page.evaluate(async ({ email, password }) => {
    try {
      const res = await fetch('/api/tenant/auth/login', {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ email, password }),
        credentials: 'same-origin',
      });

      return { success: res.ok, status: res.status, data: await res.json() };
    } catch (err: any) {
      return { success: false, status: 500, data: err.message };
    }
  }, { email, password });

  if (!result.success) {
    throw new Error(`Failed to log in as ${email}: ${result.status} ${JSON.stringify(result.data)}`);
  }
}
