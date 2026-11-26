<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_pos_deliveries')) {
            return;
        }

        Schema::table('order_pos_deliveries', function (Blueprint $table): void {
            if (! Schema::hasColumn('order_pos_deliveries', 'long_retry_round')) {
                $table->unsignedTinyInteger('long_retry_round')->default(0)->after('run_attempts_count');
            }

            if (! Schema::hasColumn('order_pos_deliveries', 'unreachable_since')) {
                $table->timestamp('unreachable_since')->nullable()->after('next_retry_at');
            }
        });
    }
};
