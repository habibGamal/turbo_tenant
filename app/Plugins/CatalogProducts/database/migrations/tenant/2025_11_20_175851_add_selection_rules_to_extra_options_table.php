<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('extra_options', function (Blueprint $table) {
            $table->integer('min_selections')->default(0)->after('is_active');
            $table->integer('max_selections')->nullable()->after('min_selections');
            $table->boolean('allow_multiple')->default(true)->after('max_selections');
        });
    }
};
