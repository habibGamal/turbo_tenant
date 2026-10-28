<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway');
            $table->string('transaction_id');
            $table->string('merchant_order_id');
            $table->string('event_type')->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->string('result');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['gateway', 'transaction_id']);
            $table->index('merchant_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
