<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Make user_id nullable to support guest orders
            $table->foreignId('user_id')->nullable()->change();

            // Add guest_user_id foreign key
            $table->foreignId('guest_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('guest_users')
                ->nullOnDelete();

            // Add index for guest_user_id
            $table->index('guest_user_id', 'idx_orders_guest_user');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['guest_user_id']);
            $table->dropIndex('idx_orders_guest_user');
            $table->dropColumn('guest_user_id');

            // Restore user_id NOT NULL constraint
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
