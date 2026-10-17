<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('extra_option_item_id')->nullable()->constrained('extra_option_items')->nullOnDelete();
            $table->string('extra_name');
            $table->double('extra_price');
            $table->timestamps();

            $table->index('order_item_id', 'idx_order_item_extras_order_item');
        });
    }
};
