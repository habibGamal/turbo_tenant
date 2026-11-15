const { test, expect } = require('@playwright/test');

test('home loads its content without startup API waterfalls or large layout shifts', async ({ page }) => {
    await page.addInitScript(() => {
        window.shifts = [];
        new PerformanceObserver((list) => {
            window.shifts.push(...list.getEntries().filter((entry) => !entry.hadRecentInput).map((entry) => entry.value));
        }).observe({ type: 'layout-shift', buffered: true });
    });
    const requests = [];
    const errors = [];
    page.on('request', (request) => requests.push(request.url()));
    page.on('pageerror', (error) => errors.push(error.message));
    const cartResponse = page.waitForResponse((response) => new URL(response.url()).pathname === '/api/cart');
    await page.goto('/');
    expect((await cartResponse).status()).toBe(200);
    await expect(page.getByTestId('section-title').first()).toBeVisible();
    await page.waitForTimeout(2500);
    expect(requests.filter((url) => /\/api\/(tenant\/(bootstrap|auth\/user)|hero-banners|catalog-products\/(sections|categories)|packages)$/.test(url))).toEqual([]);
    expect(requests.filter((url) => /fonts\.(googleapis|gstatic|bunny)\./.test(url))).toEqual([]);
    expect(requests.filter((url) => /\.css(?:\?|$)/.test(url))).toEqual([]);
    expect(requests.filter((url) => new URL(url).pathname === '/api/cart')).toHaveLength(1);
    expect(errors).toEqual([]);
    expect(await page.evaluate(() => window.shifts.reduce((sum, shift) => sum + shift, 0))).toBeLessThan(0.1);
});
