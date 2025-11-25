<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('weight_option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weight_option_id')->constrained('weight_options')->cascadeOnDelete();
            $table->decimal('value', 8, 3);
            $table->string('label')->nullable(); // Optional label like "Quarter kg", "Half kg", "1 kg"
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['weight_option_id', 'sort_order']);
        });
    }
};
