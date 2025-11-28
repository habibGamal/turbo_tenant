<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('hero_sliders', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('title_ar')->nullable();
            $table->string('subtitle');
            $table->string('subtitle_ar')->nullable();
            $table->string('badge')->nullable();
            $table->string('badge_ar')->nullable();
            $table->string('cta_text');
            $table->string('cta_text_ar')->nullable();
            $table->string('cta_link');
            $table->string('secondary_cta_text')->nullable();
            $table->string('secondary_cta_text_ar')->nullable();
            $table->string('secondary_cta_link')->nullable();
            $table->string('image')->nullable();
            $table->string('gradient')->default('from-orange-500/20 via-red-500/10 to-pink-500/20');
            $table->string('icon')->default('sparkles');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
