<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('cart_item_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_item_id')->constrained('cart_items')->cascadeOnDelete();
            $table->foreignId('extra_option_item_id')->constrained('extra_option_items')->cascadeOnDelete();
            $table->timestamps();

            $table->index('cart_item_id', 'idx_cart_item_extras_cart_item');
        });
    }
};
