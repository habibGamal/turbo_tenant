<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Jobs;

use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PosIntegration\src\Exceptions\PosBranchUnreachable;
use App\Plugins\PosIntegration\src\Exceptions\PosDeliveryBlocked;
use App\Plugins\PosIntegration\src\Exceptions\PosDeliveryOutcomeUnknown;
use App\Plugins\PosIntegration\src\Models\OrderPosDeliveryAttempt;
use App\Plugins\PosIntegration\src\Services\OrderDeliveryEligibility;
use App\Plugins\PosIntegration\src\Services\OrderDeliveryTracker;
use App\Plugins\PosIntegration\src\Services\OrderPOSService;
use App\Support\Network\InvalidEndpointException;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class SendOrderToPosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 3;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public ?string $tenantId = null;

    public ?string $generation = null;

    public function __construct(public Order $order, ?string $generation = null)
    {
        $this->tenantId = tenant() ? (string) tenant()->getTenantKey() : null;
        $this->generation = $generation ?? (string) Str::uuid();
    }

    public function handle(OrderPOSService $orderPOSService): void
    {
        if (! $this->inExpectedTenant()) {
            return;
        }

        $tracker = app(OrderDeliveryTracker::class);
        $attempt = $tracker->start($this->order, $this->deliveryGeneration(), $this->attempts());
        if (! $attempt) {
            return;
        }

        try {
            $this->order->refresh();
            $reason = app(OrderDeliveryEligibility::class)->blockingReason($this->order);
            if ($reason !== null) {
                $tracker->finish($attempt, OrderPosStatus::FAILED, 'failed', $reason);

                return;
            }

            try {
                $canAccept = $orderPOSService->canAcceptOrder($this->order->branch);
            } catch (InvalidEndpointException|PosBranchUnreachable $e) {
                throw $e;
            } catch (Throwable $e) {
                throw new PosBranchUnreachable($e->getMessage(), (int) $e->getCode(), $e);
            }

            if (! $canAccept) {
                $this->handleUnreachable($tracker, $attempt, 'deferred', 'الفرع لا يقبل الطلبات حالياً.', releaseInsteadOfThrow: true);

                return;
            }

            $result = $orderPOSService->placeOrder($this->order, fn () => $tracker->submitted($attempt));

            // Doc Scenario B: the POS rejected unknown products, so no order exists there.
            // Retrying cannot fix a missing product, so fail without rethrowing.
            if ($result['success'] === false) {
                $tracker->finish($attempt, OrderPosStatus::FAILED, 'failed', $this->safeReason($result['message'] ?? 'رفض الفرع الطلب.'));

                return;
            }

            $tracker->finish($attempt, OrderPosStatus::SENT, 'sent');
        } catch (PosDeliveryBlocked $e) {
            $tracker->finish($attempt, OrderPosStatus::FAILED, 'failed', $this->safeReason($e->getMessage()));
        } catch (PosDeliveryOutcomeUnknown $e) {
            if ($this->isSyncConnection()) {
                $tracker->finish($attempt, OrderPosStatus::NEEDS_VERIFICATION, 'uncertain', $this->safeReason($e->getMessage()).' تحقق من استلام الفرع قبل إعادة المحاولة.');

                return;
            }

            $this->handleUnreachable($tracker, $attempt, 'failed', $this->safeReason($e->getMessage()), releaseInsteadOfThrow: true);
        } catch (PosBranchUnreachable $e) {
            $reason = $e->getPrevious() === null || get_class($e->getPrevious()) === Exception::class
                ? $this->safeReason($e->getMessage())
                : 'حدث خطأ أثناء معالجة الإرسال. راجع سجل النظام لمعرفة التفاصيل.';

            if ($this->handleUnreachable($tracker, $attempt, 'failed', $reason)) {
                return;
            }

            throw $e->getPrevious() ?? $e;
        } catch (Throwable $e) {
            if ($attempt->fresh()->submitted_at !== null && get_class($e) !== Exception::class && ! $e instanceof InvalidArgumentException) {
                $tracker->finish($attempt, OrderPosStatus::NEEDS_VERIFICATION, 'uncertain', 'حدث خطأ بعد بدء الإرسال. تحقق من استلام الفرع قبل إعادة المحاولة.');

                return;
            }
            $reason = get_class($e) === Exception::class || $e instanceof InvalidArgumentException
                ? $this->safeReason($e->getMessage())
                : 'حدث خطأ أثناء معالجة الإرسال. راجع سجل النظام لمعرفة التفاصيل.';
            $tracker->finish($attempt, $this->canRetry() ? OrderPosStatus::RETRY_WAIT : OrderPosStatus::FAILED, 'failed', $reason);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->inExpectedTenant()) {
            app(OrderDeliveryTracker::class)->failed($this->order, $this->deliveryGeneration());
        }
    }

    private function handleUnreachable(
        OrderDeliveryTracker $tracker,
        OrderPosDeliveryAttempt $attempt,
        string $outcome,
        string $reason,
        bool $releaseInsteadOfThrow = false,
    ): bool {
        if ($this->isSyncConnection()) {
            $tracker->finish($attempt, OrderPosStatus::FAILED, $outcome, $reason);

            return $releaseInsteadOfThrow;
        }

        if ($this->canRetry()) {
            $tracker->finish($attempt, OrderPosStatus::RETRY_WAIT, $outcome, $reason, now()->toImmutable()->addSeconds(3));
            if ($releaseInsteadOfThrow) {
                $this->release(3);

                return true;
            }

            return false;
        }

        $tracker->scheduleNextRound($attempt, $outcome, $reason);

        return true;
    }

    private function isSyncConnection(): bool
    {
        return $this->job !== null && $this->job->getConnectionName() === 'sync';
    }

    private function canRetry(): bool
    {
        return $this->attempts() < $this->tries && ! $this->isSyncConnection();
    }

    private function inExpectedTenant(): bool
    {
        $expected = $this->tenantId ?? $this->job?->payload()['tenant_id'] ?? null;

        return tenancy()->initialized && $expected !== null && (string) tenant()->getTenantKey() === $expected;
    }

    private function deliveryGeneration(): string
    {
        return $this->generation ?? mb_substr(hash('sha256', 'legacy:'.($this->job?->uuid() ?? $this->order->id)), 0, 36);
    }

    private function safeReason(string $reason): string
    {
        $reason = preg_replace('/https?:\/\/\S+|Bearer\s+\S+|(?:token|password|secret|api[_-]?key)\s*[=:]\s*\S+/i', '[محجوب]', strip_tags($reason));

        return Str::limit($reason ?: 'تعذر إتمام الإرسال.', 1000);
    }
}
