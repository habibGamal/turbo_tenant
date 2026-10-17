<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reviews')) {
            Schema::create('reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->string('guest_name')->nullable();
                $table->string('guest_email')->nullable();
                $table->integer('rating');
                $table->text('comment');
                $table->json('images')->nullable();
                $table->boolean('is_published')->default(true);
                $table->boolean('is_approved')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
