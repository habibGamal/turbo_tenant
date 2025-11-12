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
            $table->string('area');
            $table->string('full_address');
            $table->foreignId('profile_id')->nullable()->constrained('profiles')->cascadeOnDelete();
            $table->timestamps();
        });
    }
};
