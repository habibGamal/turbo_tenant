<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('session_id')->nullable()->unique();
            $table->text('note')->nullable();
            $table->timestamp('last_activity_at')->useCurrent();
            $table->timestamps();

            $table->index('user_id', 'idx_carts_user');
            $table->index('session_id', 'idx_carts_session');
        });
    }
};
