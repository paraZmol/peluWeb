<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Ven con alguien": the line of the person who comes along carries only
     * their name. No phone, no client record.
     */
    public function up(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->string('companion_name', 60)->nullable()->after('service_variant_id');
        });
    }

    public function down(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->dropColumn('companion_name');
        });
    }
};
