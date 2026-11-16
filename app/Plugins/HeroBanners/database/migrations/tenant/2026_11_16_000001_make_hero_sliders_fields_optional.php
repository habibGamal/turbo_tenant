<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hero_sliders')) {
            Schema::table('hero_sliders', function (Blueprint $table) {
                $table->string('title')->nullable()->change();
                $table->string('subtitle')->nullable()->change();
                $table->string('cta_text')->nullable()->change();
                $table->string('cta_link')->nullable()->change();
                $table->string('gradient')->nullable()->default('from-orange-500/20 via-red-500/10 to-pink-500/20')->change();
                $table->string('icon')->nullable()->default('sparkles')->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hero_sliders')) {
            Schema::table('hero_sliders', function (Blueprint $table) {
                $table->string('title')->nullable(false)->change();
                $table->string('subtitle')->nullable(false)->change();
                $table->string('cta_text')->nullable(false)->change();
                $table->string('cta_link')->nullable(false)->change();
            });
        }
    }
};
