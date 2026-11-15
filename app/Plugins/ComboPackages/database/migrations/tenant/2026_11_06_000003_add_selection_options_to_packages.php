<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->boolean('allow_coupons')->default(true)->after('price');
            $table->softDeletes()->after('valid_until');
        });

        Schema::table('package_groups', function (Blueprint $table) {
            $table->boolean('allow_repeats')->default(false)->after('selection_type');
            $table->text('description')->nullable()->after('name_ar');
            $table->text('description_ar')->nullable()->after('description');
        });

        Schema::table('package_items', function (Blueprint $table) {
            $table->string('variant_mode', 16)->default('fixed')->after('variant_id');
        });
    }

    public function down(): void
    {
        Schema::table('package_items', function (Blueprint $table) {
            $table->dropColumn('variant_mode');
        });

        Schema::table('package_groups', function (Blueprint $table) {
            $table->dropColumn(['allow_repeats', 'description', 'description_ar']);
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('allow_coupons');
        });
    }
};
