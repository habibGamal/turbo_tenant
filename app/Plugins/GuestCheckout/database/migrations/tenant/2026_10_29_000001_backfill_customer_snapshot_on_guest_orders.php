<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasTable('guest_users')) {
            return;
        }

        $orders = DB::table('orders')
            ->join('guest_users', 'orders.guest_user_id', '=', 'guest_users.id')
            ->whereNotNull('orders.guest_user_id')
            ->where(function ($query) {
                $query->whereNull('orders.customer_name')
                    ->orWhereNull('orders.customer_phone')
                    ->orWhereNull('orders.customer_email');
            })
            ->select([
                'orders.id',
                'orders.customer_name',
                'orders.customer_phone',
                'orders.customer_email',
                'guest_users.name as guest_name',
                'guest_users.phone as guest_phone',
                'guest_users.email as guest_email',
            ])
            ->get();

        foreach ($orders as $order) {
            $updates = [];

            if ($order->customer_name === null && $order->guest_name !== null) {
                $updates['customer_name'] = $order->guest_name;
            }

            if ($order->customer_phone === null && $order->guest_phone !== null) {
                $updates['customer_phone'] = $order->guest_phone;
            }

            if ($order->customer_email === null && $order->guest_email !== null) {
                $updates['customer_email'] = $order->guest_email;
            }

            if (! empty($updates)) {
                DB::table('orders')->where('id', $order->id)->update($updates);
            }
        }
    }

    public function down(): void
    {
        // Data backfill migration - no rollback needed.
    }
};
