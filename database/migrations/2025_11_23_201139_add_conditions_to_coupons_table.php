<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->json('conditions')->nullable()->after('total_consumed');
            $table->text('description')->nullable()->after('code');
            $table->string('name')->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['conditions', 'description', 'name']);
        });
    }
};
