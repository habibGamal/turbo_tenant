<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('merchant_order_id')->unique()->nullable()->after('order_number');
            $table->string('transaction_id')->nullable()->after('merchant_order_id');
            $table->string('paymob_order_id')->nullable()->after('transaction_id');
            $table->enum('payment_status', ['pending', 'processing', 'completed', 'failed', 'refunded'])->default('pending')->after('status');
            $table->string('payment_method')->nullable()->after('payment_status');
            $table->text('payment_data')->nullable()->after('payment_method');

            $table->index('merchant_order_id', 'idx_orders_merchant_order_id');
            $table->index('transaction_id', 'idx_orders_transaction_id');
            $table->index('payment_status', 'idx_orders_payment_status');
            $table->index('payment_method', 'idx_orders_payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_merchant_order_id');
            $table->dropIndex('idx_orders_transaction_id');
            $table->dropIndex('idx_orders_payment_status');
            $table->dropIndex('idx_orders_payment_method');

            $table->dropColumn([
                'merchant_order_id',
                'transaction_id',
                'paymob_order_id',
                'payment_status',
                'payment_method',
                'payment_data',
            ]);
        });
    }
};
