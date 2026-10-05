<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform-wide catalog (not per salon). Salons copy what they offer
     * into `services` and adjust prices and durations there.
     */
    public function up(): void
    {
        Schema::create('service_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('category', 30)->index();
            $table->string('name', 120);
            $table->string('name_en', 120)->nullable();
            $table->string('description', 255);
            $table->string('target', 10);
            $table->unsignedSmallInteger('active_minutes');
            $table->unsignedSmallInteger('idle_minutes')->default(0);
            $table->unsignedSmallInteger('finish_minutes')->default(0);
            $table->unsignedInteger('price_min_cents');
            $table->unsignedInteger('price_max_cents');
            $table->boolean('price_varies_by_length')->default(false);
            $table->boolean('requires_evaluation')->default(false);
            $table->boolean('requires_patch_test')->default(false);
            $table->boolean('is_addon')->default(false);
            $table->json('tags')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_templates');
    }
};
