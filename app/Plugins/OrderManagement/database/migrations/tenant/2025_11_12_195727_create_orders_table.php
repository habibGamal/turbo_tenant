<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_number')->unique();
                $table->unsignedInteger('shift_id')->nullable();
                $table->string('status');
                $table->string('type');
                $table->double('sub_total')->default(0);
                $table->double('tax')->default(0);
                $table->double('service')->default(0);
                $table->double('delivery_fee')->default(0);
                $table->double('discount')->default(0);
                $table->double('total')->default(0);
                $table->text('note')->nullable();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('coupon_id')->nullable()->index();
                $table->foreignId('branch_id')->constrained('branches');
                $table->foreignId('address_id')->nullable()->constrained('addresses')->nullOnDelete();
                $table->timestamps();

                $table->index('status', 'idx_orders_status');
                $table->index('user_id', 'idx_orders_user');
                $table->index(['branch_id', 'created_at'], 'idx_orders_branch_date');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
