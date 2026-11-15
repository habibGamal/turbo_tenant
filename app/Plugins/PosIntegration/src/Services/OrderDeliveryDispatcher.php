<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Services;

use App\Models\User;
use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PosIntegration\src\Jobs\SendOrderToPosJob;
use App\Plugins\PosIntegration\src\Models\OrderPosDelivery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class OrderDeliveryDispatcher
{
    public function request(Order $order, User $actor): OrderPosDelivery
    {
        if (! $actor->is_admin) {
            throw new AuthorizationException;
        }

        return $this->enqueue($order, $actor);
    }

    public function enqueue(Order $order, ?User $actor = null): OrderPosDelivery
    {
        if (! tenancy()->initialized) {
            throw new AuthorizationException;
        }

        return DB::connection('tenant')->transaction(function () use ($order, $actor): OrderPosDelivery {
            $locked = Order::on('tenant')->lockForUpdate()->findOrFail($order->id);
            $delivery = OrderPosDelivery::where('order_id', $locked->id)->first();
            $reason = app(OrderDeliveryEligibility::class)->blockingReason($locked);
            $allowed = [OrderPosStatus::NOT_READY, OrderPosStatus::PENDING, OrderPosStatus::READY, null];
            if ($actor !== null) {
                $allowed[] = OrderPosStatus::FAILED;
            }

            if ($reason !== null || ! in_array($locked->pos_status, $allowed, true) || ($delivery && ! in_array($delivery->status, $allowed, true))) {
                throw ValidationException::withMessages(['pos_delivery' => $reason ?? 'الطلب مرسل بالفعل أو لديه محاولة نشطة أو يحتاج مراجعة مع الفرع.']);
            }

            $delivery ??= new OrderPosDelivery([
                'order_id' => $locked->id,
                'history_complete' => ! filled($locked->pos_failure_reason) && $locked->pos_status !== OrderPosStatus::FAILED,
                'last_error' => $locked->pos_failure_reason,
            ]);
            $delivery->fill([
                'generation' => (string) Str::uuid(),
                'status' => OrderPosStatus::QUEUED,
                'trigger' => $actor ? 'manual' : 'automatic',
                'requested_by' => $actor?->id,
                'requested_by_name' => $actor?->name,
                'run_attempts_count' => 0,
                'queued_at' => now(),
                'next_retry_at' => null,
                'sent_at' => null,
            ])->save();
            $locked->updateQuietly(['pos_status' => OrderPosStatus::QUEUED]);

            $job = new SendOrderToPosJob($locked, $delivery->generation);
            if (config('queue.default') === 'database' && config('queue.connections.database.connection') === null) {
                $job->onConnection('pos-database');
            }
            DB::connection('tenant')->afterCommit(fn () => $this->dispatch($job, $delivery));

            return $delivery;
        });
    }

    private function dispatch(SendOrderToPosJob $job, OrderPosDelivery $delivery): void
    {
        try {
            Bus::dispatch($job);
        } catch (Throwable) {
            // A sync driver may already have recorded the actual delivery outcome.
            DB::connection('tenant')->transaction(function () use ($delivery): void {
                $current = OrderPosDelivery::lockForUpdate()->find($delivery->id);
                if ($current?->generation !== $delivery->generation || $current->status !== OrderPosStatus::QUEUED) {
                    return;
                }
                $current->update(['status' => OrderPosStatus::FAILED, 'last_error' => 'تعذر إدراج المهمة في قائمة الإرسال. أعد المحاولة بعد مراجعة اتصال قائمة الانتظار.']);
                Order::on('tenant')->whereKey($current->order_id)->update(['pos_status' => OrderPosStatus::FAILED, 'pos_failure_reason' => $current->last_error]);
            });
        }
    }
}
