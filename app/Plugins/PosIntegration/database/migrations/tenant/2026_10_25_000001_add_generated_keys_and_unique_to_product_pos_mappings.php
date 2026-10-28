<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        // 1. Delete duplicate rows, keeping newest id per group treating NULL as equal
        $tableConnection = Schema::getConnection()->getName();
        $keepIds = DB::connection($tableConnection)->table('product_pos_mappings')
            ->selectRaw('MAX(id) as max_id')
            ->groupByRaw('COALESCE(product_id, 0), COALESCE(variant_id, 0), COALESCE(extra_option_item_id, 0), COALESCE(branch_id, 0)')
            ->pluck('max_id')
            ->filter();

        if ($keepIds->isNotEmpty()) {
            DB::connection($tableConnection)->table('product_pos_mappings')
                ->whereNotIn('id', $keepIds)
                ->delete();
        }

        // 2. Add virtual generated columns (MySQL 8 rejects STORED ones over ON DELETE CASCADE foreign keys), unique index on them, and drop old ineffective unique
        Schema::table('product_pos_mappings', function (Blueprint $table) {
            $table->unsignedBigInteger('product_key')->virtualAs('COALESCE(product_id, 0)');
            $table->unsignedBigInteger('variant_key')->virtualAs('COALESCE(variant_id, 0)');
            $table->unsignedBigInteger('extra_key')->virtualAs('COALESCE(extra_option_item_id, 0)');
            $table->unsignedBigInteger('branch_key')->virtualAs('COALESCE(branch_id, 0)');

            $table->unique(['product_key', 'variant_key', 'extra_key', 'branch_key'], 'unique_pos_mapping_keys');
            $table->dropUnique('unique_pos_mapping');
        });
    }

    public function down(): void
    {
        Schema::table('product_pos_mappings', function (Blueprint $table) {
            $table->dropUnique('unique_pos_mapping_keys');
            $table->dropColumn(['product_key', 'variant_key', 'extra_key', 'branch_key']);
            $table->unique(['product_id', 'variant_id', 'extra_option_item_id', 'branch_id'], 'unique_pos_mapping');
        });
    }
};
