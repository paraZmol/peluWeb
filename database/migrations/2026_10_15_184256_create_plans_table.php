<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The platform's plans (decided 2026-10-15, docs/PLAN.md §11). They only
 * differ by how many professionals with an agenda a salon may have; the
 * rows are inserted here because production needs them to exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 40);
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('founder_price_cents')->nullable();
            $table->unsignedSmallInteger('max_staff');
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('plans')->insert([
            ['code' => 'solo', 'name' => 'Solo', 'price_cents' => 3900, 'founder_price_cents' => 2900, 'max_staff' => 1, 'is_public' => true, 'position' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'equipo', 'name' => 'Equipo', 'price_cents' => 8900, 'founder_price_cents' => 6900, 'max_staff' => 6, 'is_public' => true, 'position' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'grande', 'name' => 'Más de 6', 'price_cents' => 13900, 'founder_price_cents' => null, 'max_staff' => 15, 'is_public' => false, 'position' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
