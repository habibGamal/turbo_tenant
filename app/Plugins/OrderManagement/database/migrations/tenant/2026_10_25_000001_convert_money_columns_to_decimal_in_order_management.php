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
            $table->decimal('sub_total', 10, 2)->default(0)->change();
            $table->decimal('tax', 10, 2)->default(0)->change();
            $table->decimal('service', 10, 2)->default(0)->change();
            $table->decimal('delivery_fee', 10, 2)->default(0)->change();
            $table->decimal('discount', 10, 2)->default(0)->change();
            $table->decimal('total', 10, 2)->default(0)->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->change();
            $table->decimal('total', 10, 2)->change();
        });

        Schema::table('order_item_extras', function (Blueprint $table) {
            $table->decimal('extra_price', 10, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_item_extras', function (Blueprint $table) {
            $table->double('extra_price')->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->double('unit_price')->change();
            $table->double('total')->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->double('total')->default(0)->change();
            $table->double('discount')->default(0)->change();
            $table->double('delivery_fee')->default(0)->change();
            $table->double('service')->default(0)->change();
            $table->double('tax')->default(0)->change();
            $table->double('sub_total')->default(0)->change();
        });
    }
};
