# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: order-management\23-order-filters.live.spec.ts >> search and status filters query order history and can be cleared
- Location: tests\e2e\plugins\order-management\23-order-filters.live.spec.ts:3:1

# Error details

```
Error: expect(received).toBe(expected) // Object.is equality

Expected: false
Received: true

Call Log:
- Timeout 10000ms exceeded while waiting on the predicate
```

# Page snapshot

```yaml
- generic [ref=e2]:
  - generic [ref=e3]:
    - banner [ref=e4]:
      - generic [ref=e6]:
        - link "Restaurant" [ref=e7] [cursor=pointer]:
          - /url: /
        - navigation [ref=e13]:
          - link "الرئيسية" [ref=e14] [cursor=pointer]:
            - /url: /
          - link "القائمة" [ref=e15] [cursor=pointer]:
            - /url: /menu
        - searchbox "بحث" [ref=e18]
        - generic [ref=e19]:
          - button "تغيير اللغة" [ref=e20]
          - button "الإشعارات" [ref=e22]
          - link [ref=e24] [cursor=pointer]:
            - /url: /cart
            - button "View Cart" [ref=e25]
          - button "قائمة المستخدم" [ref=e26]:
            - generic [ref=e27]: T
    - generic [ref=e31]:
      - banner [ref=e32]:
        - heading "طلباتي" [level=1] [ref=e33]
      - region "فلاتر الطلبات" [ref=e34]:
        - generic [ref=e35]:
          - generic [ref=e36]:
            - text: رقم الطلب
            - textbox "رقم الطلب" [ref=e37]:
              - /placeholder: ابحث برقم الطلب
          - generic [ref=e38]:
            - text: حالة الطلب
            - combobox "حالة الطلب" [ref=e39]:
              - generic: كل الحالات
          - generic [ref=e42]:
            - text: حالة الدفع
            - combobox "حالة الدفع" [ref=e43]:
              - generic: كل الحالات
          - button "مسح كل الفلاتر" [disabled]
      - generic [ref=e46]:
        - generic [ref=e47]:
          - generic [ref=e49]:
            - generic [ref=e50]:
              - generic [ref=e51]: "الطلب #TEST-123"
              - generic [ref=e52]: 6 نوفمبر 2026 في 02:00 م
            - generic [ref=e53]:
              - generic [ref=e54]: في الانتظار
              - generic [ref=e59]: في الانتظار
          - generic [ref=e60]:
            - generic [ref=e61]:
              - generic [ref=e62]:
                - generic [ref=e63]:
                  - generic [ref=e64]: الفرع
                  - generic [ref=e65]: Test branch
                - generic [ref=e66]:
                  - generic [ref=e67]: طريقة الدفع
                  - generic [ref=e68]: الدفع عند الاستلام
              - generic [ref=e69]: العناصر (0)
            - generic [ref=e71]:
              - generic [ref=e72]:
                - generic [ref=e73]: المجموع
                - generic [ref=e74]: ‏60 ج.م.‏
              - link "عرض التفاصيل" [ref=e75] [cursor=pointer]:
                - /url: /orders/1
        - generic [ref=e76]:
          - button "1" [ref=e77]
          - button "2" [ref=e78]
    - contentinfo [ref=e79]:
      - generic [ref=e80]:
        - generic [ref=e81]:
          - generic [ref=e82]:
            - link "Restaurant" [ref=e83] [cursor=pointer]:
              - /url: /
            - paragraph [ref=e89]: Your favorite restaurant
          - generic [ref=e90]:
            - heading "روابط سريعة" [level=4] [ref=e91]
            - list [ref=e92]:
              - listitem [ref=e93]:
                - link "الرئيسية" [ref=e94] [cursor=pointer]:
                  - /url: /
              - listitem [ref=e95]:
                - link "القائمة" [ref=e96] [cursor=pointer]:
                  - /url: /menu
              - listitem [ref=e97]:
                - link "من نحن" [ref=e98] [cursor=pointer]:
                  - /url: /pages/about-us
              - listitem [ref=e99]:
                - link "اتصل بنا" [ref=e100] [cursor=pointer]:
                  - /url: /pages/contact-us
          - generic [ref=e101]:
            - heading "الدعم" [level=4] [ref=e102]
            - list [ref=e103]:
              - listitem [ref=e104]:
                - link "معلومات التوصيل" [ref=e105] [cursor=pointer]:
                  - /url: /pages/delivery-policy
              - listitem [ref=e106]:
                - link "تتبع الطلب" [ref=e107] [cursor=pointer]:
                  - /url: /orders
              - listitem [ref=e108]:
                - link "الإرجاع" [ref=e109] [cursor=pointer]:
                  - /url: /pages/return-policy
          - generic [ref=e110]:
            - heading "اتصل بنا" [level=4] [ref=e111]
            - list [ref=e112]:
              - listitem [ref=e113]:
                - generic [ref=e117]: 123 Restaurant St, City, State 12345
        - paragraph [ref=e119]: © 2026 Restaurant. جميع الحقوق محفوظة..
  - region "Notifications alt+T"
```

# Test source

```ts
  1  | import { test, expect } from '../../helpers/live-fixture';
  2  | 
  3  | test('search and status filters query order history and can be cleared', async ({ page }) => {
  4  |   await page.addInitScript(() => {
  5  |     let bootstrap: any;
  6  |     Object.defineProperty(window, '__TENANT_BOOTSTRAP__', { configurable: true, get: () => bootstrap, set: value => { bootstrap = { ...value, user: { id: 101, name: 'Test Customer', email: 'test@example.com' } }; } });
  7  |   });
  8  |   await page.route('**/api/tenant/auth/user', route => route.fulfill({ json: { user: { id: 101, name: 'Test Customer', email: 'test@example.com' } } }));
  9  |   const requests: string[] = [];
  10 |   await page.route(/\/api\/orders(?:\?.*)?$/, route => {
  11 |     const url = new URL(route.request().url());
  12 |     requests.push(url.search);
  13 |     const found = url.searchParams.get('search') !== 'missing';
  14 |     return route.fulfill({ json: { status: 'success', data: found ? [{ id: 1, order_number: 'TEST-123', created_at: '2026-11-06T12:00:00Z', status: 'pending', payment_status: 'pending', payment_method: 'cod', branch: { name: 'Test branch' }, items: [], total: 60 }] : [], meta: { last_page: 2, current_page: Number(url.searchParams.get('page')) || 1 } } });
  15 |   });
  16 |   await page.goto('/orders', { waitUntil: 'domcontentloaded' });
  17 |   await expect(page.getByTestId('order-history-item')).toBeVisible();
  18 |   await page.getByRole('button', { name: '2', exact: true }).click();
  19 |   await expect.poll(() => requests.some(q => new URLSearchParams(q).get('page') === '2')).toBe(true);
  20 |   await page.locator('#orders-search').fill('missing');
  21 |   await expect(page.getByTestId('no-matching-orders')).toBeVisible();
  22 |   expect(new URLSearchParams(requests.at(-1)).get('search')).toBe('missing');
  23 |   expect(new URLSearchParams(requests.at(-1)).get('page')).toBe('1');
  24 |   await page.locator('#orders-status').click();
  25 |   await page.getByRole('option').filter({ hasText: /مكتمل|completed/i }).click();
  26 |   await expect.poll(() => new URLSearchParams(requests.at(-1)).get('status')).toBe('completed');
  27 |   await page.locator('#orders-payment-status').click();
  28 |   await page.getByRole('option').filter({ hasText: /فشل|failed/i }).click();
  29 |   await expect.poll(() => new URLSearchParams(requests.at(-1)).get('payment_status')).toBe('failed');
  30 |   await page.getByTestId('no-matching-orders').getByRole('button').click();
  31 |   await expect(page.getByTestId('order-history-item')).toBeVisible();
  32 |   await expect(page.locator('#orders-search')).toHaveValue('');
> 33 |   await expect.poll(() => new URLSearchParams(requests.at(-1)).has('status')).toBe(false);
     |                                                                               ^ Error: expect(received).toBe(expected) // Object.is equality
  34 | });
  35 | 
```