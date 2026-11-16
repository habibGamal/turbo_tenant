<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The POS requires an X-Api-Key header on its website endpoints. Each branch
 * points at its own POS, so each branch keeps that POS's key. Stored
 * encrypted by the Branch model cast, hence text.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branches') || Schema::hasColumn('branches', 'pos_api_key')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table): void {
            $table->text('pos_api_key')->nullable()->after('link');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('branches', 'pos_api_key')) {
            Schema::table('branches', function (Blueprint $table): void {
                $table->dropColumn('pos_api_key');
            });
        }
    }
};
