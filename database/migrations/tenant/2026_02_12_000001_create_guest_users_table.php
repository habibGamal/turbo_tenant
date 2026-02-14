<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('guest_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone');
            $table->string('phone_country_code')->default('+20');

            // Optional delivery address for guests
            $table->string('street')->nullable();
            $table->string('building')->nullable();
            $table->string('floor')->nullable();
            $table->string('apartment')->nullable();
            $table->string('city')->nullable();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();

            $table->timestamps();

            // Indexes for fast lookup
            $table->index(['phone', 'phone_country_code'], 'idx_guest_phone');
            $table->index('email', 'idx_guest_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_users');
    }
};
