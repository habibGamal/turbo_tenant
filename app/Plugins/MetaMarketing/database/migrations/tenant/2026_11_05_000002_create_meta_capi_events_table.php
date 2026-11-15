<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('meta_capi_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_name');
            $table->string('event_id')->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('fbtrace_id')->nullable();
            $table->unsignedInteger('events_received')->nullable();
            $table->text('error')->nullable();
            $table->json('payload');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_capi_events');
    }
};
