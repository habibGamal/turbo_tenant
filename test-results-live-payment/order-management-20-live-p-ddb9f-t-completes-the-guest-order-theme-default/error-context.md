# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: order-management\20-live-payment.live.spec.ts >> a live Paymob card payment completes the guest order
- Location: tests\e2e\plugins\order-management\20-live-payment.live.spec.ts:7:1

# Error details

```
TimeoutError: page.waitForResponse: Timeout 15000ms exceeded while waiting for event "response"
```

# Page snapshot

```yaml
- generic [ref=f1e2]:
  - generic [ref=f1e3]:
    - banner [ref=f1e4]:
      - generic [ref=f1e6]:
        - link "Restaurant" [ref=f1e7] [cursor=pointer]:
          - /url: /
        - navigation [ref=f1e13]:
          - link "الرئيسية" [ref=f1e14] [cursor=pointer]:
            - /url: /
          - link "القائمة" [ref=f1e15] [cursor=pointer]:
            - /url: /menu
          - link "تتبع الطلب" [ref=f1e16] [cursor=pointer]:
            - /url: /track-order
        - searchbox "بحث" [ref=f1e19]
        - generic [ref=f1e20]:
          - button "تغيير اللغة" [ref=f1e21]
          - link [ref=f1e24] [cursor=pointer]:
            - /url: /cart
            - button "View Cart" [ref=f1e25]:
              - generic [ref=f1e26]: "1"
          - button "تسجيل الدخول" [ref=f1e27]
    - main [ref=f1e31]:
      - generic [ref=f1e32]:
        - heading "الدفع" [level=1] [ref=f1e33]
        - paragraph [ref=f1e34]: أكمل طلبك
      - generic [ref=f1e35]:
        - generic [ref=f1e36]:
          - generic [ref=f1e37]:
            - generic [ref=f1e38]: نوع الطلب
            - radiogroup [ref=f1e41]:
              - generic [ref=f1e42]:
                - radio "توصيل" [ref=f1e43]
                - generic [ref=f1e44] [cursor=pointer]: توصيل
              - generic [ref=f1e45]:
                - radio "استلام" [checked] [ref=f1e46]
                - generic [ref=f1e50] [cursor=pointer]: استلام
          - generic [ref=f1e52]:
            - generic [ref=f1e53]: يرجى اختيار فرع
            - combobox [ref=f1e56]:
              - generic: E2E Main Branch
          - generic [ref=f1e59]:
            - generic [ref=f1e60]:
              - generic [ref=f1e61]: معلومات الزائر
              - generic [ref=f1e62]: يرجى تقديم معلومات الاتصال والتوصيل الخاصة بك
            - generic [ref=f1e63]:
              - generic [ref=f1e64]:
                - text: الاسم الكامل
                - textbox "الاسم الكامل" [ref=f1e65]:
                  - /placeholder: أدخل اسمك الكامل
                  - text: Live Payment Order
              - generic [ref=f1e66]:
                - text: رقم الهاتف
                - generic [ref=f1e68]:
                  - button [ref=f1e69]:
                    - generic [ref=f1e70]:
                      - img "EG"
                  - textbox "رقم الهاتف" [ref=f1e71]:
                    - /placeholder: أدخل رقم هاتفك
                    - text: 010 21153539
              - generic [ref=f1e72]:
                - generic [ref=f1e73]: البريد الإلكتروني (اختياري)
                - textbox "البريد الإلكتروني (اختياري)" [ref=f1e74]:
                  - /placeholder: أدخل بريدك الإلكتروني
          - generic [ref=f1e75]:
            - generic [ref=f1e76]: طريقة الدفع
            - radiogroup [ref=f1e79]:
              - generic [ref=f1e80]:
                - radio "الدفع عند الاستلام" [ref=f1e81]
                - generic [ref=f1e82] [cursor=pointer]: الدفع عند الاستلام
              - generic [ref=f1e87]:
                - radio "بطاقة ائتمان" [checked] [ref=f1e88]
                - generic [ref=f1e92] [cursor=pointer]: بطاقة ائتمان
          - generic [ref=f1e97]:
            - generic [ref=f1e98]:
              - generic [ref=f1e99]: ملاحظات الطلب
              - generic [ref=f1e100]: أضف ملاحظات الطلب (اختياري)
            - textbox "أي تعليمات خاصة لطلبك..." [ref=f1e102]
          - generic [ref=f1e103]:
            - generic [ref=f1e104]: رمز الكوبون
            - generic [ref=f1e108]:
              - textbox "أدخل رمز الكوبون" [ref=f1e109]
              - button "تطبيق" [disabled]
        - generic [ref=f1e111]:
          - generic [ref=f1e112]: ملخص الطلب
          - generic [ref=f1e114]:
            - generic [ref=f1e116]:
              - generic [ref=f1e117]: DEV MODE
              - generic [ref=f1e119]:
                - generic [ref=f1e120]:
                  - generic [ref=f1e121]: E2E Espresso
                  - generic [ref=f1e124]: ‏166 ج.م.‏
                - generic [ref=f1e125]: "الكمية: 1"
                - generic [ref=f1e127]: ‏166 ج.م.‏
            - generic [ref=f1e128]:
              - generic [ref=f1e129]:
                - generic [ref=f1e130]: المجموع الفرعي
                - generic [ref=f1e131]: ‏166 ج.م.‏
              - generic [ref=f1e132]:
                - generic [ref=f1e133]: المجموع
                - generic [ref=f1e134]: ‏166 ج.م.‏
            - button "جاري إرسال الطلب..." [disabled]
    - contentinfo [ref=f1e135]:
      - generic [ref=f1e136]:
        - generic [ref=f1e137]:
          - generic [ref=f1e138]:
            - link "Restaurant" [ref=f1e139] [cursor=pointer]:
              - /url: /
            - paragraph [ref=f1e145]: Your favorite restaurant
          - generic [ref=f1e146]:
            - heading "روابط سريعة" [level=4] [ref=f1e147]
            - list [ref=f1e148]:
              - listitem [ref=f1e149]:
                - link "الرئيسية" [ref=f1e150] [cursor=pointer]:
                  - /url: /
              - listitem [ref=f1e151]:
                - link "القائمة" [ref=f1e152] [cursor=pointer]:
                  - /url: /menu
              - listitem [ref=f1e153]:
                - link "من نحن" [ref=f1e154] [cursor=pointer]:
                  - /url: /pages/about-us
              - listitem [ref=f1e155]:
                - link "اتصل بنا" [ref=f1e156] [cursor=pointer]:
                  - /url: /pages/contact-us
          - generic [ref=f1e157]:
            - heading "الدعم" [level=4] [ref=f1e158]
            - list [ref=f1e159]:
              - listitem [ref=f1e160]:
                - link "معلومات التوصيل" [ref=f1e161] [cursor=pointer]:
                  - /url: /pages/delivery-policy
              - listitem [ref=f1e162]:
                - link "تتبع الطلب" [ref=f1e163] [cursor=pointer]:
                  - /url: /track-order
              - listitem [ref=f1e164]:
                - link "الإرجاع" [ref=f1e165] [cursor=pointer]:
                  - /url: /pages/return-policy
          - generic [ref=f1e166]:
            - heading "اتصل بنا" [level=4] [ref=f1e167]
            - list [ref=f1e168]:
              - listitem [ref=f1e169]:
                - generic [ref=f1e173]: 123 Restaurant St, City, State 12345
        - paragraph [ref=f1e175]: © 2026 Restaurant. جميع الحقوق محفوظة..
  - region "Notifications alt+T"
```

# Test source

```ts
  1  | import { test, expect } from '../../helpers/live-fixture';
  2  | import { openCheckout } from '../../helpers/checkout';
  3  | 
  4  | // A person enters payment details and completes any bank authentication in the gateway.
  5  | test.use({ headless: false, trace: 'off', screenshot: 'off', video: 'off' });
  6  | 
  7  | test('a live Paymob card payment completes the guest order', async ({ page, theme }) => {
  8  |   test.skip(process.env.E2E_LIVE_PAYMENT !== '1', 'Set E2E_LIVE_PAYMENT=1 to create and pay for a real order.');
  9  |   test.setTimeout(600_000);
  10 | 
  11 |   const baseUrl = process.env.E2E_BASE_URL;
  12 |   const productId = process.env.E2E_LIVE_PRODUCT_ID;
  13 |   const phone = process.env.E2E_LIVE_GUEST_PHONE;
  14 |   expect(baseUrl, 'Set E2E_BASE_URL to the live tenant HTTPS URL').toBeTruthy();
  15 |   expect(new URL(baseUrl!).protocol).toBe('https:');
  16 |   expect(productId, 'Set E2E_LIVE_PRODUCT_ID to an available simple product').toMatch(/^\d+$/);
  17 |   expect(phone, 'Set E2E_LIVE_GUEST_PHONE').toBeTruthy();
  18 | 
  19 |   await page.goto(`/products/${productId}`, { waitUntil: 'commit' });
  20 |   await expect(theme.catalog.getDetailAddToCartButton()).toBeVisible({ timeout: 60_000 });
  21 |   const [cartResponse] = await Promise.all([
  22 |     page.waitForResponse(r => new URL(r.url()).pathname === '/api/cart/items' && r.request().method() === 'POST'),
  23 |     theme.catalog.getDetailAddToCartButton().click(),
  24 |   ]);
  25 |   expect(cartResponse.status()).toBe(201);
  26 |   await openCheckout(page);
  27 |   await theme.orders.getOrderTypeTakeaway().click();
  28 |   await page.getByTestId('guest-name-input').fill('Live Payment Order');
  29 |   await page.getByTestId('guest-phone-input').fill(phone!);
  30 |   await page.getByRole('radio', { name: /credit card|بطاقة ائتمان/i }).click();
  31 | 
  32 |   const [placement] = await Promise.all([
> 33 |     page.waitForResponse(r => new URL(r.url()).pathname === '/api/orders/place' && r.request().method() === 'POST'),
     |          ^ TimeoutError: page.waitForResponse: Timeout 15000ms exceeded while waiting for event "response"
  34 |     theme.orders.getPlaceOrderButton().click(),
  35 |   ]);
  36 |   expect(placement.request().postDataJSON().payment_method).toBe('card');
  37 |   expect(placement.status()).toBe(200);
  38 |   const placed = (await placement.json()).data;
  39 |   expect(placed.requires_payment).toBe(true);
  40 |   expect(placed.order_id).toBeTruthy();
  41 |   expect(placed.order_number).toBeTruthy();
  42 |   const checkout = new URL(placed.checkout_url);
  43 |   expect(checkout.protocol).toBe('https:');
  44 |   expect(['accept.paymob.com', 'eg.checkout.paymob.com']).toContain(checkout.hostname);
  45 |   expect(checkout.searchParams.get('publicKey')).toBeTruthy();
  46 |   expect(checkout.searchParams.get('clientSecret')).toBeTruthy();
  47 |   expect(checkout.searchParams.get('publicKey')?.includes('_test_')).toBe(false);
  48 |   expect(checkout.searchParams.get('clientSecret')?.includes('_test_')).toBe(false);
  49 |   await expect.poll(() => new URL(page.url()).hostname, { timeout: 30_000 }).toBe(checkout.hostname);
  50 | 
  51 |   // Complete the real card payment and any 3-D Secure challenge in the browser.
  52 |   await page.waitForURL(url => url.origin === new URL(baseUrl!).origin &&
  53 |     url.pathname === `/orders/${placed.order_id}/payment/callback`, { timeout: 480_000 });
  54 |   await expect.poll(async () => page.evaluate(async orderId => {
  55 |     const response = await fetch(`/api/orders/${orderId}/payment-status`, { credentials: 'same-origin' });
  56 |     if (!response.ok) return `HTTP ${response.status}`;
  57 |     const body = await response.json();
  58 |     return body.data?.order?.id === orderId && body.data?.is_paid === true &&
  59 |       body.data?.order?.payment_status === 'completed' ? 'completed' : body.data?.order?.payment_status;
  60 |   }, placed.order_id), { timeout: 120_000, intervals: [3000] }).toBe('completed');
  61 |   await page.reload({ waitUntil: 'domcontentloaded' });
  62 |   await expect(page.getByRole('heading', { name: /payment successful|تم الدفع بنجاح/i })).toBeVisible();
  63 |   await expect(page.getByText(`#${placed.order_number}`, { exact: true })).toBeVisible();
  64 | });
  65 | 
```