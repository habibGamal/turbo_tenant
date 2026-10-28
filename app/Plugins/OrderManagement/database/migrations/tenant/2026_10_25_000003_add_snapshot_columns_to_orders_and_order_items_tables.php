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
            $table->string('customer_name')->nullable()->after('note');
            $table->string('customer_phone')->nullable()->after('customer_name');
            $table->json('delivery_address')->nullable()->after('customer_phone');
            $table->string('coupon_code')->nullable()->after('delivery_address');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('weight_label')->nullable()->after('notes');
            $table->decimal('extras_unit_total', 10, 2)->nullable()->after('weight_label');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['weight_label', 'extras_unit_total']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['customer_name', 'customer_phone', 'delivery_address', 'coupon_code']);
        });
    }
};
