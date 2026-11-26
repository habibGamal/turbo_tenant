# Brief: retry website orders to the POS over 24 hours

## Problem

When the branch POS is closed or offline, `SendOrderToPosJob` (`app/Plugins/PosIntegration/src/Jobs/SendOrderToPosJob.php`) retries 5 times, 3 seconds apart, then marks the order FAILED. Nobody resends it when the POS opens.

## Wanted

- Keep the quick retries. A "round" is up to `$tries` attempts with a 3 s backoff.
- When a round ends because the branch is UNREACHABLE, do not fail. Schedule a new round with delays 1h, 2h, 4h, 8h, 9h (cumulative 24h).
- Keep the schedule in one place (e.g. a `PosRetrySchedule` class with a constant array of seconds).
- After the last round fails, mark FAILED with the reason «تعذر الوصول إلى الفرع لمدة 24 ساعة. تأكد من تشغيل نقطة البيع ثم أعد الإرسال يدوياً.»

### Unreachable means

- `canAcceptOrder` throws or returns false;
- `getShiftId` fails;
- `sendOrder` connection error or POS 5xx (`PosDeliveryOutcomeUnknown`). Today that goes to NEEDS_VERIFICATION and is never replayed, but POS place-order is now idempotent (`order.idempotencyKey` from `OrderPOSPayloadBuilder::idempotencyKey`; a replay returns `already_received`, handled by `OrderPOSService::alreadyReceived`), so resend with the SAME key.
- Keep NEEDS_VERIFICATION for other errors after submit.

### Never retried (unchanged)

POS rejection (`success=false`, unknown products, 409, 4xx), `PosDeliveryBlocked`, eligibility `blockingReason`. Each round re-checks eligibility, so a cancelled order stops.

## Implementation

- Between rounds: delivery status `RETRY_WAIT`, `next_retry_at = now + delay`, order `pos_status` `RETRY_WAIT` with `pos_failure_reason`.
- Dispatch a NEW `SendOrderToPosJob` for the same generation with `->delay()` (fresh tries). The finishing job must not throw, so `failed()` does not mark FAILED.
- Reuse `OrderDeliveryDispatcher`'s pos-database connection rule via one shared dispatch method.
- New plugin tenant migration (`app/Plugins/PosIntegration/database/migrations/tenant/`, no `down()`) adding `long_retry_round` (tiny int, default 0) and `unreachable_since` (nullable timestamp) to `order_pos_deliveries`. Reset both on a new generation and on SENT.
- `OrderDeliveryTracker::finish` hard-codes `next_retry_at = now + 3s`; let the caller pass the real time.
- Manual admin action «إعادة الإرسال الآن» allowed while waiting for a long retry (new generation; the old delayed job no-ops via the generation check in `OrderDeliveryTracker::start`). Not allowed while SENDING.
- `OrderDeliveryInfolist` shows «إعادة المحاولة المؤجلة 2 من 5» plus the next retry time. Check that the stuck filter at `OrdersTable.php:133` still fits.
- `sync` queue connection: no long retries.
- All UI text in Modern Standard Arabic.

## Tests

Pest, `tests/Feature/PosIntegration`, `Queue::fake` / `Http::fake` like `OrderToPosLifecycleTest` / `OrderDeliveryTrackingTest`:

- unreachable -> RETRY_WAIT + delayed job 1h; next rounds 2h, 4h, 8h, 9h; after the 5th -> FAILED with the 24h reason;
- POS back -> SENT and counters reset;
- 5xx / connection error -> long retry with the same idempotency key, and `already_received` -> SENT;
- 409 / unknown products -> FAILED, no long retry;
- cancelled during the wait -> stops;
- manual resend during the wait -> new generation, old job no-ops.

## Docs

- `docs/dev/features/pos-integration.md` (schedule + statuses);
- Arabic admin guide page on POS delivery / failed orders (MSA);
- `docs/dev/ops/queue-and-workers.md` (delayed retries need the queue worker; the database driver keeps delayed jobs in `jobs.available_at`; no scheduler change);
- `CHANGELOG.md` Unreleased line.
