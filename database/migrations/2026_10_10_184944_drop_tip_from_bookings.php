<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tips are between the client and whoever attended: the salon only
        // collects what was agreed, so nothing about them is stored.
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('tip_cents');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedInteger('tip_cents')->default(0)->after('discount_cents');
        });
    }
};
