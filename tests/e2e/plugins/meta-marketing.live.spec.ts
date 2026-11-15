import { test, expect } from '../helpers/live-fixture';
import { openCheckout } from '../helpers/checkout';

test.describe('Plugin: meta-marketing (Live Pixel & Conversions API Tracking)', () => {
  test('tracks full funnel from browsing to COD order placement with shared eventIDs and no purchase relay', async ({
    page,
    theme,
  }) => {
    // 1. Stub window.fbq with sessionStorage persistence to record all calls across navigation and page reloads
    await page.addInitScript(() => {
      let calls: any[] = [];
      try {
        const stored = window.sessionStorage.getItem('__fbqCalls');
        if (stored) {
          calls = JSON.parse(stored);
        }
      } catch {}
      (window as any).__fbqCalls = calls;

      const fbqMock: any = function (...args: any[]) {
        (window as any).__fbqCalls.push(args);
        try {
          window.sessionStorage.setItem('__fbqCalls', JSON.stringify((window as any).__fbqCalls));
        } catch {}
      };
      fbqMock.push = fbqMock;
      fbqMock.loaded = true;
      fbqMock.version = '2.0';
      fbqMock.queue = [];
      (window as any).fbq = fbqMock;
      (window as any)._fbq = fbqMock;

      let bootstrapData: any = null;
      Object.defineProperty(window, '__TENANT_BOOTSTRAP__', {
        configurable: true,
        enumerable: true,
        get() {
          return bootstrapData;
        },
        set(val) {
          if (val && typeof val === 'object') {
            val.settings = val.settings || {};
            val.settings.meta_pixel_id = '999999999999999';
            val.settings.currency = 'EGP';
            val.settings.meta_debug_mode = true;
            val.settings.meta_server_events_enabled = true;
          }
          bootstrapData = val;
        },
      });
    });

    // 2. Intercept POST /api/meta/events to capture server-relayed funnel events
    const relayedEvents: Array<{
      event_name: string;
      event_id: string;
      event_source_url: string;
      custom_data?: Record<string, any>;
    }> = [];

    await page.route('**/api/meta/events', async (route) => {
      const data = route.request().postDataJSON();
      relayedEvents.push(data);
      await route.fulfill({
        status: 204,
        contentType: 'application/json',
        body: '',
      });
    });

    // 3. Ensure bootstrap provides active meta-marketing settings
    await page.route('**/api/tenant/bootstrap', async (route) => {
      const request = route.request();
      const targetUrl = request.url().replace('e2e.localhost:8000', '127.0.0.1:8000');
      const headers = { ...request.headers(), host: 'e2e.localhost:8000' };
      const response = await route.fetch({ url: targetUrl, headers });
      const json = await response.json();
      const settings = json.settings || (json.data ? json.data.settings : null) || {};
      settings.meta_pixel_id = '999999999999999';
      settings.currency = 'EGP';
      settings.meta_debug_mode = true;
      settings.meta_server_events_enabled = true;
      json.settings = settings;
      if (json.data) {
        json.data.settings = settings;
      }
      await route.fulfill({
        response,
        contentType: 'application/json',
        body: JSON.stringify(json),
      });
    });

    // 4. Ensure order placement returns tracking payload for dedup
    const testPurchaseEventId = 'purchase.order_test_999';
    await page.route('**/api/orders/place', async (route) => {
      const request = route.request();
      const targetUrl = request.url().replace('e2e.localhost:8000', '127.0.0.1:8000');
      const headers = { ...request.headers(), host: 'e2e.localhost:8000' };
      const response = await route.fetch({ url: targetUrl, headers });
      const json = await response.json();
      if (json.data) {
        json.data.tracking = {
          purchase_event_id: testPurchaseEventId,
          order_id: String(json.data.order_id || '999'),
          value: Number(json.data.total ?? 120.0),
          currency: 'EGP',
          content_type: 'product',
          content_ids: ['15', '5-2'],
          contents: [
            { id: '15', quantity: 1, item_price: 166.0 },
            { id: '5-2', quantity: 1, item_price: 175.0 },
          ],
          num_items: 2,
        };
      }
      await route.fulfill({ response, json });
    });

    // Helper to retrieve fbq calls from browser
    const getFbqCalls = async (): Promise<any[][]> => {
      return page.evaluate(() => {
        try {
          const stored = window.sessionStorage.getItem('__fbqCalls');
          return stored ? JSON.parse(stored) : (window as any).__fbqCalls || [];
        } catch {
          return (window as any).__fbqCalls || [];
        }
      });
    };

    // --- STEP A: Browse Home Page (PageView on first load) ---
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    const homeCatalog = page.locator('[data-testid="product-card"], [data-testid="catalog-section"]').first();
    await expect(homeCatalog).toBeVisible({ timeout: 10000 });

    let calls = await getFbqCalls();
    // Verify Pixel init was called with configured pixel ID
    const initCall = calls.find((c) => c[0] === 'init');
    expect(initCall).toBeDefined();
    expect(initCall![1]).toBe('999999999999999');

    // Verify initial PageView fired exactly once on initial load
    const initialPageViewCalls = calls.filter((c) => c[0] === 'track' && c[1] === 'PageView');
    expect(initialPageViewCalls).toHaveLength(1);
    const homePageViewEventId = initialPageViewCalls[0][3]?.eventID;
    expect(typeof homePageViewEventId).toBe('string');
    expect(homePageViewEventId.length).toBeGreaterThan(0);

    // Verify initial PageView was relayed with matching event_id
    await expect.poll(() => {
      return relayedEvents.some(
        (e) => e.event_name === 'PageView' && e.event_id === homePageViewEventId
      );
    }, { timeout: 10000 }).toBe(true);

    // --- STEP B: Client-Side Navigation to Menu (PageView on navigation) ---
    const menuLink = page.locator('header a[href="/menu"], nav a[href="/menu"]').first();
    await expect(menuLink).toBeVisible({ timeout: 5000 });
    await menuLink.click();
    await expect(page).toHaveURL(/\/menu/);

    // Wait for menu products to be loaded and visible
    const menuProductCards = page.locator('[data-testid="product-card"]');
    await expect(menuProductCards.first()).toBeVisible({ timeout: 10000 });

    // PageView must fire exactly once for the client-side navigation (total 2)
    await expect.poll(async () => {
      const c = await getFbqCalls();
      return c.filter((x) => x[0] === 'track' && x[1] === 'PageView').length;
    }, { timeout: 10000 }).toBe(2);

    calls = await getFbqCalls();
    const navigationPageViewCalls = calls.filter((c) => c[0] === 'track' && c[1] === 'PageView');
    const menuPageViewEventId = navigationPageViewCalls[1][3]?.eventID;
    expect(menuPageViewEventId).not.toBe(homePageViewEventId);

    await expect.poll(() => {
      return relayedEvents.some(
        (e) => e.event_name === 'PageView' && e.event_id === menuPageViewEventId
      );
    }, { timeout: 10000 }).toBe(true);

    // --- STEP C: AddToCart from Menu Quick Add ---
    // Product 15 ("E2E Espresso") is a standard product with no options/variants
    const firstMenuCard = menuProductCards.first();
    const quickAddBtn = theme.catalog.getAddToCartButton(firstMenuCard);
    await expect(quickAddBtn).toBeVisible({ timeout: 5000 });

    const [quickAddResponse] = await Promise.all([
      page.waitForResponse(
        (r) => new URL(r.url()).pathname === '/api/cart/items' && r.request().method() === 'POST'
      ),
      quickAddBtn.click(),
    ]);
    expect([200, 201]).toContain(quickAddResponse.status());

    // Verify cart badge reflects addition
    const badge = theme.cart.getBadgeCount();
    await expect(badge).toBeVisible({ timeout: 5000 });

    await expect.poll(async () => {
      const c = await getFbqCalls();
      return c.filter((x) => x[0] === 'track' && x[1] === 'AddToCart').length;
    }, { timeout: 10000 }).toBe(1);

    calls = await getFbqCalls();
    const quickAddToCartCalls = calls.filter((c) => c[0] === 'track' && c[1] === 'AddToCart');
    const quickAddToCartCall = quickAddToCartCalls[0];
    const quickAddToCartParams = quickAddToCartCall[2];
    const quickAddToCartEventId = quickAddToCartCall[3]?.eventID;

    // Content IDs must be string array in format "{p}"
    expect(Array.isArray(quickAddToCartParams.content_ids)).toBe(true);
    expect(quickAddToCartParams.content_ids.every((id: any) => typeof id === 'string')).toBe(true);
    expect(quickAddToCartParams.content_ids[0]).toMatch(/^\d+$/);
    expect(typeof quickAddToCartParams.value).toBe('number');
    expect(quickAddToCartParams.value).toBeGreaterThan(0);
    expect(quickAddToCartParams.currency).toBe('EGP');
    expect(quickAddToCartParams.content_type).toBe('product');

    // Relay must match eventID
    await expect.poll(() => {
      return relayedEvents.some(
        (e) => e.event_name === 'AddToCart' && e.event_id === quickAddToCartEventId
      );
    }, { timeout: 10000 }).toBe(true);

    // --- STEP D: Navigate to Product Page, ViewContent & AddToCart from Product Page ---
    // Product 5 ("E2E Simple Coffee") has variants ("Mid", "سادة") and weight options
    await Promise.all([
      page.waitForResponse(
        (r) => r.url().includes('/api/catalog-products/5') && r.status() === 200
      ),
      page.goto('/products/5', { waitUntil: 'domcontentloaded' }),
    ]);

    const productDetails = theme.catalog.getProductDetailsContainer();
    await expect(productDetails).toBeVisible({ timeout: 10000 });

    // 1. Initial ViewContent on product mount (product_group for unselected variant)
    await expect.poll(async () => {
      const c = await getFbqCalls();
      return c.filter((x) => x[0] === 'track' && x[1] === 'ViewContent').length;
    }, { timeout: 10000 }).toBeGreaterThanOrEqual(1);

    calls = await getFbqCalls();
    let viewContentCalls = calls.filter((c) => c[0] === 'track' && c[1] === 'ViewContent');
    const initialViewContent = viewContentCalls[0];
    const initialViewParams = initialViewContent[2];
    expect(initialViewParams.content_type).toBe('product_group');
    expect(initialViewParams.content_ids).toEqual(['5']);
    expect(initialViewParams.currency).toBe('EGP');

    // 2. Select variant ("سادة", id: 2) -> Fires ViewContent with content_type: "product" and content_ids: ["5-2"]
    const variantOptions = theme.catalog.getVariantOptions();
    await expect(variantOptions.nth(1)).toBeVisible({ timeout: 5000 });
    await variantOptions.nth(1).click();

    // Select weight option (250g)
    const weightOptions = theme.catalog.getWeightOptions();
    await expect(weightOptions.nth(1)).toBeVisible({ timeout: 5000 });
    await weightOptions.nth(1).click();

    await expect.poll(async () => {
      const c = await getFbqCalls();
      return c.filter((x) => x[0] === 'track' && x[1] === 'ViewContent').length;
    }, { timeout: 10000 }).toBeGreaterThanOrEqual(2);

    calls = await getFbqCalls();
    viewContentCalls = calls.filter((c) => c[0] === 'track' && c[1] === 'ViewContent');
    const variantViewContent = viewContentCalls[viewContentCalls.length - 1];
    const variantViewParams = variantViewContent[2];
    const variantViewEventId = variantViewContent[3]?.eventID;
    expect(variantViewParams.content_type).toBe('product');
    expect(variantViewParams.content_ids).toEqual(['5-2']); // Format "{p}-{v}"
    expect(typeof variantViewParams.value).toBe('number');
    expect(variantViewParams.currency).toBe('EGP');

    await expect.poll(() => {
      return relayedEvents.some(
        (e) => e.event_name === 'ViewContent' && e.event_id === variantViewEventId
      );
    }, { timeout: 10000 }).toBe(true);

    // 3. Add to cart from Product Page
    const detailAddBtn = theme.catalog.getDetailAddToCartButton();
    await expect(detailAddBtn).toBeVisible({ timeout: 5000 });

    const [detailAddResponse] = await Promise.all([
      page.waitForResponse(
        (r) => new URL(r.url()).pathname === '/api/cart/items' && r.request().method() === 'POST'
      ),
      detailAddBtn.click(),
    ]);
    expect([200, 201]).toContain(detailAddResponse.status());

    await expect.poll(async () => {
      const c = await getFbqCalls();
      return c.filter((x) => x[0] === 'track' && x[1] === 'AddToCart').length;
    }, { timeout: 10000 }).toBe(2);

    calls = await getFbqCalls();
    const allAddToCartCalls = calls.filter((c) => c[0] === 'track' && c[1] === 'AddToCart');
    const detailAddToCartCall = allAddToCartCalls[1];
    const detailAddToCartParams = detailAddToCartCall[2];
    const detailAddToCartEventId = detailAddToCartCall[3]?.eventID;

    expect(detailAddToCartParams.content_ids).toEqual(['5-2']); // Format "{p}-{v}"
    expect(detailAddToCartParams.content_type).toBe('product');
    expect(typeof detailAddToCartParams.value).toBe('number');
    expect(detailAddToCartParams.value).toBeGreaterThan(0);
    expect(detailAddToCartParams.currency).toBe('EGP');

    await expect.poll(() => {
      return relayedEvents.some(
        (e) => e.event_name === 'AddToCart' && e.event_id === detailAddToCartEventId
      );
    }, { timeout: 10000 }).toBe(true);

    // --- STEP E: Navigate to Checkout via openCheckout helper (InitiateCheckout) ---
    const { cart } = await openCheckout(page);
    expect(cart.items.length).toBeGreaterThanOrEqual(2);

    // Wait for checkout form to render
    const checkoutForm = theme.checkout.getCheckoutForm();
    await expect(checkoutForm).toBeVisible({ timeout: 10000 });

    await expect.poll(async () => {
      const c = await getFbqCalls();
      return c.filter((x) => x[0] === 'track' && x[1] === 'InitiateCheckout').length;
    }, { timeout: 10000 }).toBe(1);

    calls = await getFbqCalls();
    const initiateCheckoutCalls = calls.filter((c) => c[0] === 'track' && c[1] === 'InitiateCheckout');
    const checkoutCall = initiateCheckoutCalls[0];
    const checkoutParams = checkoutCall[2];
    const checkoutEventId = checkoutCall[3]?.eventID;

    expect(Array.isArray(checkoutParams.content_ids)).toBe(true);
    expect(checkoutParams.content_ids.every((id: any) => typeof id === 'string')).toBe(true);
    expect(checkoutParams.content_ids).toContain('15');
    expect(checkoutParams.content_ids).toContain('5-2');
    expect(typeof checkoutParams.num_items).toBe('number');
    expect(checkoutParams.num_items).toBeGreaterThanOrEqual(2);
    expect(typeof checkoutParams.value).toBe('number');
    expect(checkoutParams.value).toBeGreaterThan(0);
    expect(checkoutParams.currency).toBe('EGP');

    await expect.poll(() => {
      return relayedEvents.some(
        (e) => e.event_name === 'InitiateCheckout' && e.event_id === checkoutEventId
      );
    }, { timeout: 10000 }).toBe(true);

    // --- STEP F: Fill Guest Checkout Form and Submit COD Order (Purchase) ---
    const guestNameInput = page.locator('[data-testid="guest-name-input"], input#guest_name').first();
    await expect(guestNameInput).toBeVisible({ timeout: 5000 });
    await guestNameInput.fill('Meta Test Customer');

    const guestPhoneInput = page.locator('[data-testid="guest-phone-input"], input#guest_phone').first();
    await expect(guestPhoneInput).toBeVisible({ timeout: 5000 });
    await guestPhoneInput.fill('01012345678');

    const areaTrigger = page.locator('[data-testid="guest-area-trigger"], #guest_area').first();
    await expect(areaTrigger).toBeVisible({ timeout: 5000 });
    await areaTrigger.click();

    const firstOption = page.locator('[role="option"]').first();
    await expect(firstOption).toBeVisible({ timeout: 5000 });
    await firstOption.click();

    const streetInput = page.locator('#guest_street, input[id="guest_street"]').first();
    if (await streetInput.isVisible()) {
      await streetInput.fill('Street 15');
    }

    const buildingInput = page.locator('#guest_building, input[id="guest_building"]').first();
    if (await buildingInput.isVisible()) {
      await buildingInput.fill('Building 2');
    }

    // Submit COD Order
    const placeOrderBtn = theme.orders.getPlaceOrderButton();
    await expect(placeOrderBtn).toBeVisible({ timeout: 5000 });
    await expect(placeOrderBtn).toBeEnabled({ timeout: 5000 });

    const [placeOrderResponse] = await Promise.all([
      page.waitForResponse((r) => r.url().includes('/api/orders/place') && r.status() === 200),
      placeOrderBtn.click(),
    ]);
    const placeOrderData = await placeOrderResponse.json();
    expect(placeOrderData.status).toBe('success');

    // Wait for navigation after order submission
    await expect(page).toHaveURL(/orders\/(track|\d+)/, { timeout: 10000 });

    await expect.poll(async () => {
      const c = await getFbqCalls();
      return c.filter((x) => x[0] === 'track' && x[1] === 'Purchase').length;
    }, { timeout: 10000 }).toBe(1);

    calls = await getFbqCalls();
    const purchaseCalls = calls.filter((c) => c[0] === 'track' && c[1] === 'Purchase');
    const purchaseCall = purchaseCalls[0];
    const purchaseParams = purchaseCall[2];
    const purchaseEventId = purchaseCall[3]?.eventID;

    // Purchase eventID must equal tracking.purchase_event_id
    expect(purchaseEventId).toBe(testPurchaseEventId);
    expect(purchaseParams.value).toBe(placeOrderData.data.tracking.value);
    expect(purchaseParams.currency).toBe('EGP');
    expect(purchaseParams.content_ids).toEqual(['15', '5-2']);

    // Assert Purchase is NEVER sent to the /api/meta/events relay
    const purchaseRelayed = relayedEvents.find((e) => e.event_name === 'Purchase');
    expect(purchaseRelayed).toBeUndefined();

    // --- STEP G: Verify every non-Purchase event was relayed with matching event_id ---
    const nonPurchaseTrackCalls = calls.filter(
      (c) => c[0] === 'track' && c[1] !== 'Purchase'
    );
    expect(nonPurchaseTrackCalls.length).toBeGreaterThan(0);

    for (const trackCall of nonPurchaseTrackCalls) {
      const eventName = trackCall[1];
      const eventID = trackCall[3]?.eventID;
      expect(typeof eventID).toBe('string');

      const matchingRelay = relayedEvents.find(
        (e) => e.event_name === eventName && e.event_id === eventID
      );
      expect(
        matchingRelay,
        `Expected relay event for ${eventName} with eventID ${eventID}`
      ).toBeDefined();
    }

    // --- STEP H: Clean up cart if any items remain ---
    await page.goto('/cart', { waitUntil: 'domcontentloaded' });
    const emptyMsg = theme.cart.getEmptyCartMessage();
    const clearBtn = theme.cart.getClearCartButton();
    if (await clearBtn.isVisible()) {
      await clearBtn.click();
      const confirmClearBtn = theme.cart.getClearCartConfirmButton();
      if (await confirmClearBtn.isVisible()) {
        await confirmClearBtn.click();
      }
    }
    await expect(emptyMsg).toBeVisible({ timeout: 5000 });
  });
});
