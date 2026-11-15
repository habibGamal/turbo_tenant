<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_pos_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->uuid('generation');
            $table->string('status')->index();
            $table->string('trigger');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->string('requested_by_name')->nullable();
            $table->boolean('history_complete')->default(true);
            $table->unsignedInteger('attempts_count')->default(0);
            $table->unsignedInteger('failures_count')->default(0);
            $table->unsignedInteger('run_attempts_count')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('order_pos_delivery_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_id')->constrained('order_pos_deliveries')->cascadeOnDelete();
            $table->uuid('generation');
            $table->unsignedInteger('attempt_number');
            $table->unsignedInteger('queue_attempt')->nullable();
            $table->string('trigger');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->string('requested_by_name')->nullable();
            $table->string('outcome');
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['delivery_id', 'generation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_pos_delivery_attempts');
        Schema::dropIfExists('order_pos_deliveries');
    }
};
