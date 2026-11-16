<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hero_sliders')) {
            Schema::create('hero_sliders', function (Blueprint $table) {
                $table->id();
                $table->string('title')->nullable();
                $table->string('title_ar')->nullable();
                $table->string('subtitle')->nullable();
                $table->string('subtitle_ar')->nullable();
                $table->string('badge')->nullable();
                $table->string('badge_ar')->nullable();
                $table->string('cta_text')->nullable();
                $table->string('cta_text_ar')->nullable();
                $table->string('cta_link')->nullable();
                $table->string('secondary_cta_text')->nullable();
                $table->string('secondary_cta_text_ar')->nullable();
                $table->string('secondary_cta_link')->nullable();
                $table->string('image')->nullable();
                $table->string('gradient')->nullable()->default('from-orange-500/20 via-red-500/10 to-pink-500/20');
                $table->string('icon')->nullable()->default('sparkles');
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_sliders');
    }
};
