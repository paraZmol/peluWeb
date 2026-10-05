<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 30);
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->string('target', 10);
            // Money in céntimos, never floats.
            $table->unsignedInteger('price_cents');
            // Segments: the professional works `active`, is free during
            // `idle` (e.g. color processing) and works again for `finish`.
            $table->unsignedSmallInteger('active_minutes');
            $table->unsignedSmallInteger('idle_minutes')->default(0);
            $table->unsignedSmallInteger('finish_minutes')->default(0);
            $table->boolean('is_addon')->default(false);
            $table->boolean('requires_evaluation')->default(false);
            $table->boolean('requires_patch_test')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['salon_id', 'is_active', 'category', 'position']);
            $table->unique(['salon_id', 'service_template_id']);
        });

        Schema::create('service_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('label', 40);
            $table->unsignedInteger('price_cents');
            $table->smallInteger('extra_minutes')->default(0);
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_variants');
        Schema::dropIfExists('services');
    }
};
