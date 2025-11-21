<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image');
            $table->double('base_price')->nullable();
            $table->double('price_after_discount')->nullable();
            $table->foreignId('extra_option_id')->nullable()->constrained('extra_options')->nullOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('sell_by_weight')->default(false);
            $table->foreignId('weight_options_id')->nullable()->constrained('weight_options')->nullOnDelete();
            $table->timestamps();

            $table->index('category_id', 'idx_products_category');
            $table->index('is_active', 'idx_products_active');
        });
    }
};
