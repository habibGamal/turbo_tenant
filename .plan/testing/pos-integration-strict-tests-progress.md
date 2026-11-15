# Delegation Progress: PosIntegration strict tests vs `CREATE_ORDER_API_DOC.md`

**Started:** 2026-10-28 · **Base commit:** `e6e1b7f` on `refactoring`
**Implementer:** Antigravity CLI `agy`, model `gemini-3.8-flash-high`, effort high, full access (owner approved), 3 parallel instances, then 1.
**Reviewer / committer:** Claude Code. agy never commits; each lane is reviewed and committed on its own branch.
**Isolation:** one git worktree per lane under `D:\turbo_tenant_wt\pos-<lane>`, each with its own `vendor/` copy and SQLite test DB. `node_modules` is a junction.
**Plan:** `C:\Users\habib\.claude\plans\i-want-to-setup-fancy-rain.md`

## Baseline
25 of 75 POS tests failing on `e6e1b7f`: `OrderPOSPayloadBuilder::mapOrderType(?string)` receives the `OrderType` enum, so every real order hits a TypeError before reaching the POS. Tests also use the invalid types `'delivery'` and `'takeaway'`.

## Owner decisions
1. Doc violations are fixed in code (test first).
2. Wallet, kiosk and bank transfer are sent as `payment_method: "card"`.
3. `"N/A"` customer fallbacks and dropping unmapped items are kept, and tests lock them in.
4. The stored `shift_id` is reused; it is fetched only when null.

## Lanes

| Lane | Branch | Scope | Status |
|---|---|---|---|
| A | `pos/a-client` | F5 Accept header on GETs; can-accept-order and get-shift-id contracts; `POSApiClientTest` | reviewed + committed `393c3da` |
| B | `pos/b-payload` | F1 enum type mapping, F2 payment mapping; fixture; request and posRefObj contracts | reviewed + committed `928f143` |
| merge | `pos/strict-tests` | A + B + C merged at `0816903` (in worktree `pos-a-client`) | 104 passed; the 3 old duplicate files still fail (17), removal in lane D |
| C | `pos/c-responses` | F3 doc-only error handling (incl. 422); response contract | reviewed + committed `2bb78ac` |
| D | `pos/strict-tests` | F4 job marks Scenario B FAILED; lifecycle contract; delete duplicate tests | reviewed + committed `771d7e0` |

## Review notes
- **A** (`393c3da`): Diff is a 4-line Accept header on both GETs and nothing else. The tests failed before the fix (17 failed) and pass after. I re-ran them myself: 25 passed, Pint clean. Connection-failure cases only assert the exception, because Laravel's fake doesn't record a request that throws.
- **C** (`2bb78ac`): The handler now follows the doc's order: 400 Scenario B, 400 Scenario A, 422 message, then the generic error. The legacy keys and the `message` fallback are gone. The response suite stubs the payload builder so it runs independently of lane B. I re-ran it myself: 29 passed, Pint clean.

- **B** (`928f143`): `mapOrderType(OrderType)` uses an exhaustive match, and POS orders throw. `buildWebPreferences` uses the enum only. `posMapping()` maps wallet, kiosk and bank transfer to `card`. The doc-example fixture matches the doc's request body exactly; the only omission is `transaction_id` when it is null. `orders.type` is NOT NULL, so the enum signature is safe. I re-ran 77 tests myself (POS + OrderUx), all passing, and Pint is clean.

- **D** (`771d7e0`): The job marks Scenario B FAILED with the message and doesn't retry. I confirmed the Scenario B test fails without the fix. Reviewer changes on top of agy's output: (1) the loose `isset($result['success'])` / `?? null` became `$result['success'] === false` / `$result['message']`, since `placeOrder()` always returns both; (2) agy skipped the plugin-wiring test, so I rewrote it to build the plugin from its real `plugin.json` and assert the exact raw listener entries (`Event::hasListeners()` was already true because of other plugins). `final class` on the job comes from the repo's Pint `final_class` rule. All 3 old files were deleted, and every case is mapped to a new test, except the legacy `notFoundProducts` and `'delivery'`/`'takeaway'` cases, which were dropped on purpose. Result: 132 passed, 0 skipped.

## End-of-run verification
Each of the 88 Feature/Unit test files was run on its own, on both `e6e1b7f` and `pos/strict-tests@771d7e0`. **No file regressed.** POS files: the new contract suite has 85 passing tests; `OrderPOSPayloadBuilderTest` went from 6 failed to 10 passed; `POSApiClientTest` from 8 to 13 passed; `POSErrorResponseHandlerTest` from 7 to 15 passed. 14 files fail identically on both commits (auth starter kit, Filament login/tenant/weight options, guest orders, cart/pricing, `PlaceOrderServiceTest` 1, and `tests/Unit/Services/ProductPOSImporterServiceTest.php` 15/15). The last one is a stale copy that still constructs the importer with `SettingService`; the Feature copy is current and passes 15/15.

## Full-suite note
`php artisan test` with no path also runs `tests/Browser` (Pest browser plugin + Playwright), which hung for more than 40 minutes on both the baseline and the new branch. The full comparison therefore runs `tests/Feature tests/Unit` only.

## Needs your eyes
- **B:** a POS-type order now throws `نوع الطلب غير مدعوم في نقطة البيع: pos`. Before this change it was silently sent as `web_delivery`.
- **C:** a 400 whose `error` starts with `منتجات غير موجودة:` but has an array that can't be parsed falls through to Scenario A, so the raw error text becomes the failure reason. This seems sensible, but the doc doesn't define it.
