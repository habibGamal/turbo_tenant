# Architecture review — 2026-11-25

Skill: improve-codebase-architecture. Branch `refactoring` @ d6dbf55, worktree `D:\tt-wt\arch-review` (read-only so far).
Visual report: `architecture-review-2026-11-25.html` (same folder).

Binding rule: plugins never write another plugin's tables (`tests/Feature/Architecture/PluginBoundariesArchTest.php` rule 3; only checks imports). No ADRs exist.

| # | Candidate | Strength | Key evidence |
|---|---|---|---|
| 1 | One Catalog price module (PHP + API `effective_price`; TS stops recomputing) | Strong | 8 rules: `CartPriceCalculator.php:18` charges `price_after_discount ?? base_price`; `resources/js/lib/pricing.ts` shows base unless `0 < d < base`. Discount 0 → shown base, charged free. Line formula with weight differs in `useProductOptions.ts:61-93`. |
| 2 | Checkout shows a server quote (same pipelines, no save) | Strong | `Checkout.tsx:262` `amount * 0.0` tax; own delivery/service math vs `Pipes/Pricing/*` using `OrderSettingKey::TAX_RATE`. |
| 3 | One order-status module in OrderManagement | Strong | 3 writers: `OrderStatusController`, `OrderPOSService.php:147-196` (writes OM tables), `ViewOrder.php:55` (no transition check, no audit event, reason dropped). |
| 4 | Collapse POS payload builder + resolver + customer formatter into one module; real seam only at HTTP client | Strong | 5 contracts with 1 adapter each; 4× `method_exists` in `OrderPOSPayloadBuilder`; hidden call-order rule on resolver (714 lines). |
| 5 | Delete unused `OrderValidator` / `OrderPricingCalculator` (or make PlaceOrderService use one validate-then-price module) | Strong (small) | Bound in `OrderManagementServiceProvider.php:51-52`, resolved nowhere; 8 unit tests pin them. |
| 6 | POS delivery state owned only by PosIntegration | Worth exploring | `orders.pos_status` + `order_pos_deliveries.status` hand-synced in ~11 sites; `OrderPosStatus::READY` never assigned. |
| 7 | Gateway adapter extracts webhook signature; one order-payment module | Worth exploring | Kashier/Paymob webhook controllers near copies; `OrderPaymentRecorder::record(array)` = `update()`; COD paid via model hook, skips `OrderPaymentCompleted`. |

Not candidates: CartEngine contracts (4 of 5 have one adapter, low pain); plugin kernel looked deep.

Recommended order: 1 → 2 (live pricing bugs) → 3 → 5 → 4 → 6/7.
Next step per skill: Habib picks a candidate, then grilling to shape the module before agy writes code.
