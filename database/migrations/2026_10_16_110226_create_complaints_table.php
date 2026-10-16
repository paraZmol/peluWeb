<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The platform's Libro de Reclamaciones (docs/legal/libro-de-reclamaciones.md
 * §2, requirement P6). A sheet is never edited nor deleted: only its answer
 * is written, once. The provider is copied into each sheet as it read on
 * the day it was filed.
 */
return new class extends Migration
{
    public function up(): void
    {
        // One row per year: locking it hands out the next number with no gaps.
        Schema::create('complaint_counters', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('number');
            $table->string('kind', 16);

            $table->string('provider_name', 200);
            $table->string('provider_ruc', 11);
            $table->string('provider_address', 255);

            $table->string('consumer_name', 150);
            $table->string('consumer_address', 255);
            $table->string('document_type', 8);
            $table->string('document_number', 20);
            $table->string('phone', 20);
            $table->string('email', 255);
            $table->string('guardian_name', 150)->nullable();

            $table->string('item_type', 16);
            $table->string('item_description', 255);
            $table->unsignedInteger('claimed_amount_cents')->nullable();
            $table->char('currency', 3)->default('PEN');
            $table->text('detail');
            $table->text('request');

            $table->timestamp('consented_at');
            $table->date('due_on');

            $table->text('response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->restrictOnDelete();

            $table->timestamps();

            $table->unique(['year', 'number']);
            $table->index(['responded_at', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('complaint_counters');
    }
};
