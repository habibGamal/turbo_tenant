# Storefront performance verification — 2026-11-03

Target: http://e2e.localhost:8000/ (local E2E tenant, production Vite build).

Repeated desktop Lighthouse audits with the same preset and Chromium:

| Metric | Before | Final |
| --- | --- | --- |
| Performance | 40 | 83 |
| First contentful paint | 2.9 s | 1.7 s |
| Largest contentful paint | 6.4 s | 1.7 s |
| Cumulative layout shift | 0.349 | 0 |
| Total blocking time | 0 ms | 40 ms |
| Startup JavaScript | 1,434 KB | 773 KB |

The user supplied a separate audit scoring 46. The before figures above are the reproduced audit from this session. Scores vary with local server load; these are lab results, not field measurements. The remaining initial HTML response takes roughly 0.6 seconds in this environment.

Changes: embed session bootstrap and active plugin query data safely in the document; share catalog/combo readers with the APIs; hydrate React Query before mounting; defer account and plugin screens including the 458 KB phone-input chunk; serve licensed Tajawal fonts locally and preload heading fonts; remove the blocking loading overlay; synchronize category/section loading; prioritize the first hero image.

Validation: TypeScript/Vite build; targeted hydration, catalog/cart contract and plugin contribution PHP tests; desktop/mobile Playwright performance regressions; six route smoke checks per viewport with no page errors; engineering-atlas fixture/extractor/render tests, source freshness and 40-diagram viewer validation. Tests use the isolated test tenant; live checks were read-only.

Reproduce browser regressions:

```powershell
npx playwright test --config=tests/performance/playwright.config.cjs --output=C:/Users/habib/.codex/tmp/turbo-perf-results
```

Evidence: `C:/Users/habib/.codex/tmp/turbo-perf-lighthouse-final.report.html` and `.report.json`. The initial Lighthouse JSON is `C:/Users/habib/AppData/Local/Temp/lighthouse-before.json`.

Generated `public/build` assets were rebuilt locally and are ignored by Git. Deployments must include a fresh production build. Existing unrelated working-tree changes were preserved.

## Follow-up: compression, caching and blocking styles

The user's follow-up audit scored 58 with FCP 4.5 s / LCP 4.9 s. It correctly identified remaining uncompressed static assets, missing cache lifetimes and a separate blocking tenant stylesheet. The earlier desktop score was not representative of that run.

Implemented:

- Local PHP router negotiates gzip for versioned JavaScript/CSS/SVG, supplies immutable one-year caching, `Vary: Accept-Encoding`, byte-correct lengths and representation-specific ETags/304 responses. Files are restricted to the resolved build-assets directory. Apache configuration declares text compression and hashed-asset caching as well; Apache availability/module loading has not been exercised here.
- Tenant theme CSS is generated from the tenant's `theme` store setting and embedded inline, with closing style tags escaped. The initial page no longer requests `/theme.css`.
- Tailwind explicitly scans storefront source rather than compiled/admin views. CSS falls from 150,597 to 105,661 bytes before compression, and transfers at roughly 17 KiB after compression.
- Startup JavaScript transfers at roughly 234 KiB instead of 755 KiB.
- HTML bootstrap includes the current session cart through its active plugin contribution, removing the initial `/api/cart` request without changing the cart API or mutation cache updates.
- The six regular/medium/bold Arabic/Latin fonts used above the fold are discovered in the document head.

Final Lighthouse 13.6 local lab runs:

| Configuration | Score | Simulated FCP / LCP | TBT | CLS | Observed trace FCP / LCP |
| --- | --- | --- | --- | --- | --- |
| Default mobile (150 ms RTT, approximately 1.6 Mbps, 4x CPU) | 94 | 0.7 s / 0.7 s | 270 ms | 0 | 1.87 s / 1.87 s |
| Desktop preset | 100 | 0.2 s / 0.2 s | 0 ms | 0 | 1.33 s / 1.33 s |

These are Lighthouse simulation scores, not guaranteed browser timings. The actual local PHP document response still took approximately 0.95–1.38 s in these traces; application boot latency remains a possible further improvement. No application/session HTML is cached publicly by these changes.

Reports:

- `C:/Users/habib/.codex/tmp/turbo-perf-mobile-delivery-final.report.html` / `.report.json`
- `C:/Users/habib/.codex/tmp/turbo-perf-desktop-delivery-final.report.html` / `.report.json`

Checks: 16 PHP tests (367 assertions), four desktop/mobile browser regressions including gzip/identity body equivalence and conditional caching, six-route smoke checks on both viewports, production build and atlas fixture/render/viewer/freshness validation. No browser page errors were observed.

## Follow-up: eliminate the stylesheet request and reduce PHP boot time

The next user audit still measured FCP 1.8 s / LCP 2.7 s, an 800 ms blocking CSS request, and an 824 ms document response.

Production CSS is now an explicit Vite entry and is embedded in the document with `Vite::content`. The hot-development path retains the usual Vite stylesheet loading. The gzip-compressed HTML grows to approximately 20 KiB, replacing the previously separate 16.6 KiB stylesheet request. Browser regressions assert that no external CSS is requested during initial rendering.

PHP's OPcache DLL was installed but not loaded. `php artisan serve:fast` extends Laravel's local server launcher to load it where available, enable CLI opcode caching, validate PHP source timestamps, and revalidate on every request. It leaves the global PHP configuration unchanged. Port 8000 is currently running through this command. Use `php artisan serve:fast` for later development-server starts to retain the benefit. A process restart still incurs a cold compilation cost.

Measured warm document response: approximately 355–371 ms with curl versus 739 ms immediately before the change (824 ms in the supplied audit). The final desktop Lighthouse trace observed a 280 ms document response and 632 ms FCP/LCP. The final mobile Lighthouse simulation scored 100, but this score is not a substitute for actual throttled measurements.

Separate Chromium tests with browser caching disabled, 150 ms latency, about 1.6 Mbps download and 4x CPU produced repeated FCP/LCP of 2.26–2.34 s, CLS 0, zero external CSS requests and no page errors. A fresh browser process consistently added about 2.4 s before DNS lookup on its first navigation; its first FCP/LCP was about 4.7 s. The trace separates that browser startup delay from the PHP request, but its OS/browser cause remains unverified.

Evidence:

- `C:/Users/habib/.codex/tmp/turbo-inline-opcache-desktop.report.html` and `.report.json`
- `C:/Users/habib/.codex/tmp/turbo-inline-opcache-mobile.report.html` and `.report.json`
- `C:/Users/habib/.codex/tmp/turbo-cold-loads.cjs` (actual throttled browser probe)

Validation: production build; 18 PHP tests / 373 assertions; four desktop/mobile browser regressions; atlas fixture tests, rebuilt 40 rendered diagrams, viewer checks and source freshness. The opcode-cache launcher test verifies source timestamp validation remains enabled.

## Requested restoration of `/api/cart`

The cart hydration optimization has been reverted at the user's request. The document no longer seeds the `['cart']` React Query cache. The existing `useCart()` hook again calls `GET /api/cart` on page load for the cart count, preserving its original query/mutation behavior. Bootstrap still exposes its pre-existing `cart_count` field. CSS delivery, compression, caching, and the opcode-cached server launcher remain unchanged. The earlier performance measurements above describe runs before this restoration and have not been remeasured.
