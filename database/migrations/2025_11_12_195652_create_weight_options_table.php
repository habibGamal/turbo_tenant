<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('weight_options', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('min_weight', 8, 3);
            $table->decimal('max_weight', 8, 3);
            $table->decimal('step', 8, 3)->default(0.5);
            $table->string('unit')->default('kg');
            $table->timestamps();
        });
    }
};
