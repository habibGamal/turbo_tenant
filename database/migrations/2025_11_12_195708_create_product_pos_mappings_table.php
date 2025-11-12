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
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('pos_item_id');
            $table->string('pos_category')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'variant_id', 'branch_id'], 'unique_mapping');
            $table->index('product_id', 'idx_pos_product');
            $table->index('branch_id', 'idx_pos_branch');
        });
    }
};
