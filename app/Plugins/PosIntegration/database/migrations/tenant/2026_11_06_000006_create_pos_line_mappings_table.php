<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('pos_line_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('line_type', 32);
            $table->unsignedBigInteger('line_reference_id');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('pos_item_id');
            $table->timestamps();

            $table->unique(['line_type', 'line_reference_id', 'branch_id'], 'unique_pos_line_mapping');
            $table->index(['line_type', 'line_reference_id'], 'idx_pos_line_mappings_type_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_line_mappings');
    }
};
