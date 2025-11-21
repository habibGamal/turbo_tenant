<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('area_id')->constrained('areas')->restrictOnDelete();
            $table->string('phone_number');
            $table->string('street')->nullable();
            $table->string('building')->nullable();
            $table->string('floor')->nullable();
            $table->string('apartment')->nullable();
            $table->text('full_address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('user_id', 'idx_addresses_user');
            $table->index('area_id', 'idx_addresses_area');
            $table->index(['user_id', 'is_default'], 'idx_addresses_user_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
