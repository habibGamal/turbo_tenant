<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->enum('type', ['percentage', 'fixed']);
            $table->double('value');
            $table->dateTime('expiry_date');
            $table->boolean('is_active')->default(true);
            $table->integer('max_usage')->nullable();
            $table->integer('usage_count')->default(0);
            $table->double('total_consumed')->default(0);
            $table->timestamps();

            $table->index('code', 'idx_coupon_code');
            $table->index(['is_active', 'expiry_date'], 'idx_coupon_active');
        });
    }
};
