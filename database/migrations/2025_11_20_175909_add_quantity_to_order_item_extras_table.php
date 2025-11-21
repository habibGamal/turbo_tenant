<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('order_item_extras', function (Blueprint $table) {
            $table->integer('quantity')->default(1)->after('extra_price');
        });
    }
};
