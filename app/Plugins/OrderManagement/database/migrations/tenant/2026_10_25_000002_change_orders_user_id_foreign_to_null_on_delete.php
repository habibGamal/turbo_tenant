<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });

            return;
        }

        $foreignKeys = collect(Schema::getForeignKeys('orders'));
        $userIdFk = $foreignKeys->first(function (array $fk) {
            return in_array('user_id', $fk['columns'] ?? [], true)
                || ($fk['name'] ?? '') === 'orders_user_id_foreign';
        });

        if ($userIdFk && mb_strtolower($userIdFk['on_delete'] ?? '') === 'set null') {
            return;
        }

        if ($userIdFk) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });

            return;
        }

        $foreignKeys = collect(Schema::getForeignKeys('orders'));
        $userIdFk = $foreignKeys->first(function (array $fk) {
            return in_array('user_id', $fk['columns'] ?? [], true)
                || ($fk['name'] ?? '') === 'orders_user_id_foreign';
        });

        if ($userIdFk) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
