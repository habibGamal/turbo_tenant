<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('order_item_components', function (Blueprint $table) {
            if (! Schema::hasColumn('order_item_components', 'extras')) {
                $table->json('extras')->nullable()->after('sort_order');
            }
            if (! Schema::hasColumn('order_item_components', 'notes')) {
                $table->string('notes', 200)->nullable()->after('extras');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_item_components', function (Blueprint $table) {
            $drop = array_filter(['extras', 'notes'], fn ($col) => Schema::hasColumn('order_item_components', $col));
            if (! empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
