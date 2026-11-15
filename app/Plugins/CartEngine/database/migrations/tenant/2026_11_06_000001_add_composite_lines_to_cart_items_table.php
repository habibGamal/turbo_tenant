<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            // 'product' for plain product lines; other values belong to a CartLineTypeHandler (e.g. 'package').
            $table->string('line_type', 32)->default('product')->after('cart_id');
            // Id in the handler's own domain (e.g. packages.id). No FK: CartEngine must not know that table.
            $table->unsignedBigInteger('line_reference_id')->nullable()->after('line_type');
            $table->string('selection_signature', 64)->nullable()->after('line_reference_id');
            $table->text('notes')->nullable()->after('quantity');
            $table->unsignedBigInteger('product_id')->nullable()->change();

            $table->index(['cart_id', 'line_type', 'selection_signature'], 'idx_cart_items_signature');
        });

        Schema::create('cart_item_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_item_id')->constrained('cart_items')->cascadeOnDelete();
            // Id of the handler's option row (e.g. package_items.id). No FK, same reason as above.
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('quantity', 8, 3)->default(1);
            $table->decimal('unit_price_adjustment', 10, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('cart_item_id', 'idx_cart_item_components_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_item_components');

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex('idx_cart_items_signature');
            $table->dropColumn(['line_type', 'line_reference_id', 'selection_signature', 'notes']);
        });
    }
};
