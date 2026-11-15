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
            if (! Schema::hasColumn('orders', 'pos_status')) {
                $table->string('pos_status')->nullable()->default('not_ready');
            }
            if (! Schema::hasColumn('orders', 'pos_failure_reason')) {
                $table->text('pos_failure_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $drop = array_filter(['pos_status', 'pos_failure_reason'], fn ($col) => Schema::hasColumn('orders', $col));
            if (! empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
