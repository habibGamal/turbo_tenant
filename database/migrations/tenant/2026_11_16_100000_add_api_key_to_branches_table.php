<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            // Encrypted by the model cast, so it needs text, not string.
            $table->text('api_key')->nullable()->after('link');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('api_key');
        });
    }
};
