import { expect, Page } from '@playwright/test';

/** Observe the same independent data requests used by the checkout page. */
export async function openCheckout(page: Page) {
  // Commit the navigation first so responses still in flight on the previous
  // page cannot be mistaken for checkout's requests.
  await page.goto('/checkout', { waitUntil: 'commit' });
  const [cartResponse, locationsResponse] = await Promise.all([
    page.waitForResponse(r => new URL(r.url()).pathname === '/api/cart' && r.request().method() === 'GET'),
    page.waitForResponse(r => new URL(r.url()).pathname === '/api/locations-delivery' && r.request().method() === 'GET'),
  ]);
  expect(cartResponse.status(), 'checkout cart response').toBe(200);
  expect(locationsResponse.status(), 'checkout locations response').toBe(200);
  const cart = await cartResponse.json();
  const locations = await locationsResponse.json();
  expect(cart.status).toBe('success');
  expect(locations.status).toBe('success');
  return { cart: cart.data, locations: locations.data };
}
