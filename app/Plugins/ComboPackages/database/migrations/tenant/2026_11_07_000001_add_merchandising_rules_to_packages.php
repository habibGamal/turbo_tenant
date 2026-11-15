<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('package_availabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->index(['package_id', 'day_of_week'], 'idx_pkg_avail_pkg_day');
        });

        Schema::create('package_branch', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['package_id', 'branch_id']);
        });

        Schema::table('packages', function (Blueprint $table): void {
            $table->json('allowed_order_types')->nullable()->after('valid_until');
            $table->unsignedInteger('max_per_order')->nullable()->after('allowed_order_types');
            $table->unsignedInteger('daily_limit')->nullable()->after('max_per_order');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            $table->dropColumn(['daily_limit', 'max_per_order', 'allowed_order_types']);
        });

        Schema::dropIfExists('package_branch');
        Schema::dropIfExists('package_availabilities');
    }
};
