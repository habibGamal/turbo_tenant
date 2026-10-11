<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('extra_option_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('extra_option_id')->constrained('extra_options')->cascadeOnDelete();
            $table->string('name');
            $table->double('price')->default(0);
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('extra_option_id', 'idx_extra_items');
        });
    }
};
