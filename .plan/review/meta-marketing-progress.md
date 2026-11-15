# Meta Pixel / CAPI / Catalog — delegation progress

Plan: `docs/META_PIXEL_AND_CAPI_PLAN.md` (rev 2 + decisions in §10).
Implementer: Antigravity `agy`, model `gemini-3.8-flash-high`, effort high, full permissions, one detached
worktree per instance under `D:/tt-wt/<task-id>`. Merged into `D:\turbo_tenant` **without commits**.
Helpers: `D:/tt-wt/_meta/{mkwt.sh, rmwt.sh, taskdiff.sh}`, briefs in `D:/tt-wt/_meta/briefs/`.

## Baseline (2026-11-05, main tree incl. foundation)

`php artisan test tests/Feature tests/Unit --compact`: **54 failed, 1241 passed**
(43 distinct failing tests, list in `D:/tt-wt/_meta/baseline-failures.txt`; all pre-existing, none in MetaMarketing).

## Foundation (orchestrator, done)

- New plugin `app/Plugins/MetaMarketing` (`meta-marketing`, deps: order-management, catalog-products, store-settings).
- `MetaSettingKey` (Pixel ID keeps the `facebook_app_id` key), `MetaSettingsSection` (Arabic tab; access token
  encrypted, never echoed, blank keeps it; catalog token auto-generated + regenerate action), `MetaMarketingConfig`
  (tenant-scoped, decrypts token), `MetaContentId`, bootstrap contributor (`meta_pixel_id`, `meta_debug_mode`,
  `meta_server_events_enabled`), routes, stub entry points, `config/services.php` → `meta.graph_version` = v26.0.
- Pixel ID field + `SettingKey::FACEBOOK_APP_ID` removed from StoreSettings (key value unchanged, so no data migration).
- Tests: `tests/Feature/MetaMarketing/MetaMarketingFoundationTest.php` (4), `SettingsUxTest` tab list updated.

## Wave 1 (parallel)

| Task | Worktree | Status | Notes |
|---|---|---|---|
| A storefront Pixel + event bus + relay calls | `D:/tt-wt/meta-a-frontend` | **merged** (uncommitted) | Round 1: core bus `core/analytics`, frontend plugin `plugins/meta-marketing` (Pixel init + advanced matching, eventID, relay via fetch keepalive + XSRF, debug mode), route tracker via new `router.tracking` slot, AddToCart in `useAddToCart` (all add paths), wishlist in favorites hook, Search, CompleteRegistration, InitiateCheckout, AddPaymentInfo. Round 2 (review fixes): default store currency, buffer events until settings sync, Purchase in `usePlaceOrder`/`usePaymentStatus` hooks only, shared `cartItemToCommerceItem` helper (extras + weight). Merged tree: tsc 0 errors, build OK. **Orchestrator e2e run:** PageView/AddToCart/ViewContent assertions passed but cart was empty at checkout → InitiateCheckout 0; cause: spec's add-to-cart steps were wrapped in `if`s and silently skipped. Round 3 = make the spec deterministic + unconditional, pass twice against the live e2e server. **Round 3:** spec rewritten (helpers, real cart adds, unconditional asserts, `expect.poll`); passed twice for agy and once for the orchestrator from the main tree (30 s). No app-code changes. Note: it lets `/api/orders/place` hit the real e2e backend (creates a COD order in the e2e tenant) and injects `tracking`; host rewrite to 127.0.0.1 is hard-coded for `e2e.localhost:8000`. |
| B Conversions API (Purchase, relay endpoint, delivery log) | `D:/tt-wt/meta-b-capi` | **merged** (uncommitted) | 2 tenant migrations (`meta_order_tracking`, `meta_capi_events`), client (`sendEvents`, `verifyCredentials`), `UserDataBuilder` (EG phone normalization), capture listener, idempotent queued Purchase job, relay endpoint + request, response `tracking`, `_fbp/_fbc` excluded from cookie encryption; 28 tests re-run green. Out-of-scope edits were only Pint (my brief told it to Pint the plugin dir). **Orchestrator fixes:** shared `OrderPurchaseContents` (contents + `eventId()`; was duplicated in job and contributor); `event_time` fixed at dispatch (was `now()` per retry); relay validates `contents.*` and relays only validated keys (raw input was passed through), `num_items` numeric (weight items), `status` boolean; provider imports tidied. MetaMarketing 57 tests + arch green (2 pre-existing failures), phpstan OK. |
| C Catalog feed (CSV/XML/Arabic override, validator) | `D:/tt-wt/meta-c-catalog` | **merged** (uncommitted) | 10 classes in `src/Catalog/`, controller, 29 tests (33 with foundation) re-run green by orchestrator. Pricing mirrors `CartPriceCalculator` + parity test. Arabic override = `id, override (ar_AR), title` (no Arabic description exists). Cache busted on Product/Variant/Category save/delete (bulk query updates don't fire events → up to 30 min stale). **Orchestrator fix:** explicit `fputcsv` escape arg (PHP 8.4 deprecation). Provider tidy (FQCN → imports) deferred until B merges. agy reported full suite 54 failed = baseline. |

## Wave 2

| Task | Worktree | Status | Notes |
|---|---|---|---|
| D Admin diagnostics page | `D:/tt-wt/meta-d-diagnostics` | **merged** (uncommitted) | Filament page `MetaDiagnostics` (group المحتوى والتسويق): config badges, token check, test PageView with test code (confirmation without one), delivery log table with retry (failed Purchase only, original event_time) + payload modal, catalog URLs/check/preview, ID consistency check. Logic in `src/Diagnostics/` services. 9 tests; plugin total 66 green, pint/phpstan OK. One allowed provider line: `loadViewsFrom`. |

## Atlas (orchestrator)

`definition.json`: 2 moved anchors fixed, Meta added to the system-context diagram, 2 source-reviewed observations
(Meta Purchase/funnel workflow; catalog feed + secrets). Rebuilt with `--observe` (renderer deps installed in
`D:/tt-wt/atlas-tooling`, outside the app). `test_atlas.py` OK, `atlas.py check .` valid (42 diagrams).

## Final verification (2026-11-05, merged tree)

- `php artisan test tests/Feature tests/Unit --compact`: **54 failed, 1303 passed** — same 43 failing tests as the
  baseline, 0 new (+62 tests).
- MetaMarketing: 66 tests green; Pint clean; PHPStan OK on the plugin; architecture tests: only the 2 pre-existing
  OrderManagement→PosIntegration failures.
- `npx tsc --noEmit` 0 errors; `npm run build` OK.
- e2e `tests/e2e/plugins/meta-marketing.live.spec.ts` passed against the local e2e server.
- Atlas rebuilt and `atlas.py check .` valid.
- Worktrees removed (node_modules intact). Nothing committed.

## Follow-up (2026-11-06, orchestrator, inline)

- **Diagnostics page rebuilt** with native Filament 4 schema components (`content()`: Tabs → Sections, TextEntry
  badges, EmbeddedTable, EmptyState, RepeatableEntry tables, CodeEntry payload modal). The agy version rendered a
  custom Blade view whose Tailwind classes are not in the panel theme (broken layout); views + `loadViewsFrom`
  removed. Duplicate "Verify Token" action removed; test event works before the CAPI toggle (needs pixel + token);
  tab keys `?tab=status|log|catalog|ids`. Verified in the browser on the `kofe` tenant (desktop + narrow).
- **Catalog validator fixed**: it re-implemented pricing and flagged "sale price ≥ price" on rows where the feed
  sends no sale price (variants, equal discount) — 64 false issues on kofe. Row checks now run on the
  `CatalogItemBuilder` output; new data warning only when `price_after_discount` > `base_price`. Kofe now: 1 real
  issue (product 30 has no image). Tests updated/added; MetaMarketing 67 tests green, Pint + PHPStan clean.
- **Atlas**: 3 reviewed diagrams (`meta-integration`, `meta-purchase-sequence`, `meta-catalog-feed`) and 2
  observations (content ID scheme, operating guide). Rebuilt with `--observe`; `test_atlas.py` OK, `check` valid
  (45 diagrams).

## Fix (2026-11-06): feed cache removed

`/meta/catalog/*.xml` returned 500 on kofe: "This cache store does not support tagging". Tenancy's
CacheTenancyBootstrapper tags every cache call, and the app runs `CACHE_STORE=database` (not taggable); tests use
`array`, so they never caught it. The same `Cache::forever` ran from Product/Variant/Category `saved`/`deleted`
hooks, so **product saves in the admin were failing too**. Feed is now generated per fetch (no cache, never
stale), `CatalogFeedCache` + model hooks deleted, cache tests renamed to freshness tests, guard test added (no
Cache use anywhere in the plugin). Verified live on kofe: csv/xml/ar.csv 200, wrong token 404. 70 plugin tests
green, PHPStan OK. Atlas catalog diagram + plan doc updated; atlas check valid.
