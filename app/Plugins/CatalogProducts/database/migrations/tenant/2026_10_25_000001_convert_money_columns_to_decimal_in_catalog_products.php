<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('base_price', 10, 2)->nullable()->change();
            $table->decimal('price_after_discount', 10, 2)->nullable()->change();
        });

        // ->change() must restate every modifier: a NULL variant price means "use the product price".
        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->change();
        });

        Schema::table('extra_option_items', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('extra_option_items', function (Blueprint $table) {
            $table->double('price')->default(0)->change();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->double('price')->nullable()->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->double('price_after_discount')->nullable()->change();
            $table->double('base_price')->nullable()->change();
        });
    }
};
