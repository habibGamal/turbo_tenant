<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('min_selections')->nullable()->after('extra_option_id');
            $table->integer('max_selections')->nullable()->after('min_selections');
            $table->boolean('allow_multiple')->nullable()->after('max_selections');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['min_selections', 'max_selections', 'allow_multiple']);
        });
    }
};
