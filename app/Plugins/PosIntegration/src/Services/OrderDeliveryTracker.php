<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Services;

use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PosIntegration\src\Exceptions\PosDeliveryBlocked;
use App\Plugins\PosIntegration\src\Models\OrderPosDelivery;
use App\Plugins\PosIntegration\src\Models\OrderPosDeliveryAttempt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class OrderDeliveryTracker
{
    public function start(Order $order, string $generation, int $queueAttempt): ?OrderPosDeliveryAttempt
    {
        return DB::connection('tenant')->transaction(function () use ($order, $generation, $queueAttempt): ?OrderPosDeliveryAttempt {
            $locked = Order::on('tenant')->lockForUpdate()->find($order->id);
            if (! $locked || $locked->pos_status === OrderPosStatus::SENT) {
                return null;
            }

            $delivery = OrderPosDelivery::where('order_id', $locked->id)->lockForUpdate()->first();
            // Adopt a job queued before delivery tracking was deployed, once only.
            if (! $delivery) {
                $delivery = OrderPosDelivery::create([
                    'order_id' => $locked->id,
                    'generation' => $generation,
                    'status' => OrderPosStatus::QUEUED,
                    'trigger' => 'automatic',
                    'queued_at' => now(),
                    'history_complete' => false,
                ])->refresh();
            }

            if ($delivery->generation !== $generation || ! in_array($delivery->status, [OrderPosStatus::QUEUED, OrderPosStatus::RETRY_WAIT], true)) {
                return null;
            }

            $delivery->update([
                'status' => OrderPosStatus::SENDING,
                'attempts_count' => $delivery->attempts_count + 1,
                'run_attempts_count' => $delivery->run_attempts_count + 1,
                'last_attempt_at' => now(),
                'next_retry_at' => null,
            ]);
            $locked->updateQuietly(['pos_status' => OrderPosStatus::SENDING]);

            return $delivery->attempts()->create([
                'generation' => $generation,
                'attempt_number' => $delivery->attempts_count,
                'queue_attempt' => $queueAttempt,
                'trigger' => $delivery->trigger,
                'requested_by' => $delivery->requested_by,
                'requested_by_name' => $delivery->requested_by_name,
                'outcome' => 'sending',
                'started_at' => now(),
            ]);
        });
    }

    public function submitted(OrderPosDeliveryAttempt $attempt): void
    {
        $this->withCurrent($attempt->delivery_id, $attempt->generation, function (OrderPosDelivery $delivery) use ($attempt): void {
            if ($delivery->status !== OrderPosStatus::SENDING || $attempt->fresh()->finished_at !== null) {
                throw new RuntimeException('Delivery attempt is no longer active.');
            }
            $reason = app(OrderDeliveryEligibility::class)->blockingReason($delivery->order()->firstOrFail());
            if ($reason !== null) {
                throw new PosDeliveryBlocked($reason);
            }
            $attempt->update(['submitted_at' => now()]);
        }, requireCurrent: true);
    }

    public function finish(OrderPosDeliveryAttempt $attempt, OrderPosStatus $status, string $outcome, ?string $error = null): void
    {
        $this->withCurrent($attempt->delivery_id, $attempt->generation, function (OrderPosDelivery $delivery) use ($attempt, $status, $outcome, $error): void {
            $current = $attempt->fresh();
            if ($current->finished_at !== null || $delivery->status !== OrderPosStatus::SENDING) {
                return;
            }

            $current->update([
                'outcome' => $outcome,
                'error' => $error,
                'finished_at' => now(),
                'duration_ms' => max(0, (int) $current->started_at->diffInMilliseconds(now())),
            ]);
            $delivery->update([
                'status' => $status,
                'failures_count' => $delivery->failures_count + (in_array($outcome, ['failed', 'uncertain'], true) ? 1 : 0),
                'last_error' => $error ?? $delivery->last_error,
                'next_retry_at' => $status === OrderPosStatus::RETRY_WAIT ? now()->addSeconds(3) : null,
                'sent_at' => $status === OrderPosStatus::SENT ? now() : null,
            ]);
            Order::on('tenant')->whereKey($delivery->order_id)->update([
                'pos_status' => $status,
                'pos_failure_reason' => $status === OrderPosStatus::SENT ? null : $error,
            ]);
        });
    }

    public function failed(Order $order, string $generation): void
    {
        $delivery = OrderPosDelivery::where('order_id', $order->id)->first();
        if (! $delivery) {
            return;
        }

        $this->withCurrent($delivery->id, $generation, function (OrderPosDelivery $current): void {
            if (! in_array($current->status, [OrderPosStatus::QUEUED, OrderPosStatus::SENDING, OrderPosStatus::RETRY_WAIT], true)) {
                return;
            }
            $attempt = $current->attempts()->where('generation', $current->generation)->whereNull('finished_at')->latest('id')->first();
            $uncertain = $attempt?->submitted_at !== null;
            $status = $uncertain ? OrderPosStatus::NEEDS_VERIFICATION : OrderPosStatus::FAILED;
            $reason = $uncertain
                ? 'توقفت المهمة بعد بدء الإرسال. تحقق من استلام الفرع قبل إعادة المحاولة.'
                : ($current->last_error ?? 'توقفت المهمة أو انتهت المحاولات المسموح بها قبل تأكيد الإرسال.');

            if ($attempt) {
                $attempt->update([
                    'outcome' => $uncertain ? 'uncertain' : 'failed',
                    'finished_at' => now(),
                    'duration_ms' => max(0, (int) $attempt->started_at->diffInMilliseconds(now())),
                    'error' => $reason,
                ]);
            }
            $current->update([
                'status' => $status,
                'next_retry_at' => null,
                'last_error' => $reason,
                'failures_count' => $current->failures_count + ($attempt ? 1 : 0),
            ]);
            Order::on('tenant')->whereKey($current->order_id)->update(['pos_status' => $status, 'pos_failure_reason' => $reason]);
        });
    }

    private function withCurrent(int $id, string $generation, callable $callback, bool $requireCurrent = false): void
    {
        DB::connection('tenant')->transaction(function () use ($id, $generation, $callback, $requireCurrent): void {
            $delivery = OrderPosDelivery::find($id);
            if ($delivery) {
                Order::on('tenant')->whereKey($delivery->order_id)->lockForUpdate()->first();
                $delivery = OrderPosDelivery::lockForUpdate()->find($id);
            }
            if (! $delivery || $delivery->generation !== $generation) {
                if ($requireCurrent) {
                    throw new RuntimeException('Delivery generation has changed.');
                }

                return;
            }
            $callback($delivery);
        });
    }
}
