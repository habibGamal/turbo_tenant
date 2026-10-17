<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('weight_option_value_id')->nullable()->after('variant_id')->constrained('weight_option_values')->nullOnDelete();
            $table->integer('weight_multiplier')->default(1)->after('weight_option_value_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['weight_option_value_id']);
            $table->dropColumn(['weight_option_value_id', 'weight_multiplier']);
        });
    }
};
