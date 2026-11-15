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
            if (! Schema::hasColumn('order_items', 'line_type')) {
                $table->string('line_type', 32)->default('product')->after('order_id');
            }
            if (! Schema::hasColumn('order_items', 'line_reference_id')) {
                $table->unsignedBigInteger('line_reference_id')->nullable()->after('line_type');
            }
            $table->unsignedBigInteger('product_id')->nullable()->change();
        });

        if (! Schema::hasTable('order_item_components')) {
            Schema::create('order_item_components', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('group_name')->nullable();
                $table->string('group_name_ar')->nullable();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
                $table->string('product_name');
                $table->string('product_name_ar')->nullable();
                $table->string('variant_name')->nullable();
                $table->string('variant_name_ar')->nullable();
                $table->decimal('quantity', 8, 3)->default(1);
                $table->decimal('unit_price_adjustment', 10, 2)->default(0);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index('order_item_id', 'idx_order_item_components_item');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_components');

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['line_type', 'line_reference_id']);
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
        });
    }
};
