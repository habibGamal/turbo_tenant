<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reviews')) {
            return;
        }

        Schema::table('reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('reviews', 'guest_name')) {
                $table->string('guest_name')->nullable()->after('product_id');
            }

            if (! Schema::hasColumn('reviews', 'guest_email')) {
                $table->string('guest_email')->nullable()->after('guest_name');
            }

            if (! Schema::hasColumn('reviews', 'is_published')) {
                $table->boolean('is_published')->default(true)->after('images');
            }

            if (! Schema::hasColumn('reviews', 'is_approved')) {
                $table->boolean('is_approved')->default(true)->after('is_published');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reviews')) {
            return;
        }

        Schema::table('reviews', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('reviews', 'is_approved')) {
                $columns[] = 'is_approved';
            }

            if (Schema::hasColumn('reviews', 'is_published')) {
                $columns[] = 'is_published';
            }

            if (Schema::hasColumn('reviews', 'guest_email')) {
                $columns[] = 'guest_email';
            }

            if (Schema::hasColumn('reviews', 'guest_name')) {
                $columns[] = 'guest_name';
            }

            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
