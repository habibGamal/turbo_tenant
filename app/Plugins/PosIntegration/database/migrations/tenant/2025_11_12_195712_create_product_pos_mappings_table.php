<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('product_pos_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('extra_option_item_id')->nullable()->constrained('extra_option_items')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('pos_item_id');
            $table->timestamps();

            $table->unique(['product_id', 'variant_id', 'extra_option_item_id', 'branch_id'], 'unique_pos_mapping');
            $table->index('product_id', 'idx_pos_product');
            $table->index('variant_id', 'idx_pos_variant');
            $table->index('extra_option_item_id', 'idx_pos_extra');
        });
    }
};
