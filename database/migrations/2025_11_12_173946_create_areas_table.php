<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->double('shipping_cost')->default(0);
            $table->foreignId('governorate_id')->constrained('governorates')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('governorate_id', 'idx_areas_governorate');
            $table->index('branch_id', 'idx_areas_branch');
            $table->index('is_active', 'idx_areas_active');
            $table->index(['governorate_id', 'branch_id'], 'idx_areas_gov_branch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
