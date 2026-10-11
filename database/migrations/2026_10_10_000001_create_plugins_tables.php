<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plugins', function (Blueprint $table) {
            $table->string('id', 60)->primary();
            $table->string('name', 120);
            $table->string('version', 30)->default('1.0.0');
            $table->string('category', 40)->default('addon');
            $table->text('description')->nullable();
            $table->boolean('is_core')->default(false);
            $table->json('dependencies')->nullable();
            $table->string('author', 120)->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_plugins', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('plugin_id', 60);
            $table->string('status', 30)->default('entitled');
            $table->json('settings')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'plugin_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('plugin_id')->references('id')->on('plugins')->cascadeOnDelete();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_plugins');
        Schema::dropIfExists('plugins');
    }
};
