<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        // Drop the old package_product pivot table
        Schema::dropIfExists('package_product');

        // Create package_groups table for grouping items with conditional logic
        Schema::create('package_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable(); // e.g., "Choose your main dish"
            $table->string('name_ar')->nullable();
            $table->enum('selection_type', ['all', 'choose_one', 'choose_multiple'])->default('all');
            // 'all' = customer gets all items in this group
            // 'choose_one' = customer must choose exactly 1 item from this group
            // 'choose_multiple' = customer can choose X items from this group
            $table->integer('max_selections')->nullable(); // For 'choose_multiple' type
            $table->integer('min_selections')->nullable(); // Minimum selections required
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Create package_items table for individual products/variants
        Schema::create('package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->integer('quantity')->default(1); // Default quantity for this item
            $table->decimal('price_adjustment', 10, 2)->default(0); // Extra charge for choosing this item
            $table->boolean('is_default')->default(false); // Default selection for conditional groups
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_items');
        Schema::dropIfExists('package_groups');

        // Recreate the old package_product pivot table
        Schema::create('package_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->timestamps();
        });
    }
};
