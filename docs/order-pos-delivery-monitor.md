# Order POS delivery monitoring

The tenant Filament order view shows delivery state, recorded attempt/failure counts, current retry progress, queued/latest/next/sent timestamps, latest error and the requesting administrator. A paginated relation table retains execution attempts and manual verification decisions. The orders table includes delivery failures, latest attempts and an overdue filter. The existing failed-orders widget uses the same retry service.

## Delivery lifecycle

`OrderDeliveryDispatcher` locks the tenant order, checks eligibility and reserves a new generation before dispatching after the tenant transaction commits. All automatic listeners and manual controls use this path. A queued/sending/retry-wait/sent/uncertain order cannot be queued again through it. Manual actions require an administrator and an active POS addon. Cancelled/completed orders, POS-origin orders, refunded payments and unconfirmed online payments are blocked. Cash-on-delivery orders do not require online payment completion.

`SendOrderToPosJob` checks tenant identity, claims the generation under the order lock and records the attempt before calling the POS. It checks eligibility again immediately before the submission. A duplicate or stale generation cannot claim another active attempt or update the current result. Attempts include preflight availability/shift checks; a branch deferral is an attempt but not a failed submission. The existing five tries and three-second delay are retained. A sync queue cannot schedule retries and therefore ends with a terminal state instead of claiming another attempt is scheduled.

The queue lifecycle and the delivery outcome are distinct: a missing-product rejection returns normally from the job but is recorded as a delivery failure. Exceptions with retries remaining show `retry_wait`; terminal rejection/exhaustion shows `failed`. Deferred branches do not remain `sending`.

Transport loss during the POST, a POS server error, an unexpected error after submission, or a worker failure after submission produces `needs_verification` without automatic replay. This is deliberately conservative: the [POS contract](https://github.com/habibGamal/ai_rewrite_turbo_restaurant/blob/master/docs/dev/website-integration.md) defines `orderNumber` and `idempotencyKey`, and uncertain outcomes still require confirmation rather than automatic blind replay. A hard-killed worker may leave a sending record; the overdue warning provides a recovery path rather than assuming that the POS rejected it.

After checking the order number with the branch, an administrator records whether it was received, with required evidence. Confirmed receipt marks it sent. Confirmed non-receipt enables a new retry. This rotates the generation and keeps the reviewer and evidence in history. Orders waiting more than five minutes beyond their queued/attempt/next-retry timestamp also allow this review. Elapsed time is a warning, not proof of failure or worker death. Verification does not recall a request already in flight; confirm the result with the branch before recording it.

Historical records are not reconstructed from `failed_jobs` or application logs. Orders without tracking show unavailable historic counts. Lifetime counters refer to recorded executions; verification entries do not increment them, and a manual retry resets only the current cycle's attempt count. Errors remain in attempt history after success. The tracking transaction and queue publication are separate: a publication exception becomes a visible failed state with zero executed attempts; a process crash between commit and publication remains queued and becomes overdue for review.

## Queue storage and deployment

The tracking tables live in the tenant database and are supplied by the POS plugin migration:

```text
app/Plugins/PosIntegration/database/migrations/tenant/2026_11_05_000001_create_order_pos_deliveries.php
```

Run this additive migration for existing active POS tenants before serving the changed UI or starting new workers. Normal plugin activation runs the migration for newly enabled tenants. Do not run unrelated pending migrations merely to install tracking.

With the default database queue and no explicit database connection, POS dispatch uses the `pos-database` connection declared in `config/queue.php`. Its storage connection is explicitly central (`POS_QUEUE_DB_CONNECTION`, then `DB_QUEUE_CONNECTION`, then `DB_CONNECTION`), while Stancl still adds `tenant_id` to each job. The standard central `queue:work database` worker sees the same table/queue; `queue:work pos-database` is also supported. An explicitly configured queue connection is respected. Ensure that an explicitly configured database connection is central and that workers consume the configured table and queue.

The configured job timeout is 60 seconds and Laravel marks supported worker timeouts failed. Signal-based worker timeouts require PCNTL, which is unavailable in the Windows development runtime; the HTTP client timeouts and overdue/manual review path still apply there. The queue visibility/retry-after interval must exceed this timeout (the default database setting is 90 seconds). The existing POS HTTP timeout remains 30 seconds per request; multiple calls can reach the overall job timeout. A central worker must run for delivery to progress. Clear/rebuild configuration caches when deploying `config/queue.php`, and restart long-lived queue workers after the code and tenant schema are deployed. Use the order retry action so generation checks and audit history are maintained; `queue:retry` on an old terminal generation does not create a new delivery cycle.

## Verification scope

Tests cover actual database queue execution starting outside tenancy, tenant restoration, duplicate/stale jobs, retries, branch deferrals, uncertain POST results, worker-failure callbacks, manual reviews, queue publication failures, cancellation before submission, sync-driver behavior, administrator controls, disabled-addon rendering, and rendered Filament actions/history/filters. HTTP calls are faked in these tests. This does not establish remote POS deduplication, deployment worker availability, or real POS delivery.

Alerts, a measured worker heartbeat and bulk retries are follow-up features.
