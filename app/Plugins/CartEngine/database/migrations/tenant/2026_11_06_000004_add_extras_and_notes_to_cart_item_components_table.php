<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('cart_item_components', function (Blueprint $table) {
            // [{id: extra_option_item_id, quantity}] as the customer picked them; priced by the line handler on read.
            $table->json('extras')->nullable()->after('unit_price_adjustment');
            $table->string('notes', 200)->nullable()->after('extras');
        });
    }

    public function down(): void
    {
        Schema::table('cart_item_components', function (Blueprint $table) {
            $table->dropColumn(['extras', 'notes']);
        });
    }
};
