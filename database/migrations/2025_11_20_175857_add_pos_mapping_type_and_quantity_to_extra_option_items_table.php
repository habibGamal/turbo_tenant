<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('extra_option_items', function (Blueprint $table) {
            $table->enum('pos_mapping_type', ['pos_item', 'notes'])->default('pos_item')->after('sort_order');
            $table->boolean('allow_quantity')->default(false)->after('pos_mapping_type');
        });
    }
};
