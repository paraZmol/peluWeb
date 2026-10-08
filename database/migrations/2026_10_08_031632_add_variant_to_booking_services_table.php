<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            // Which option of the service was booked ("Largo", "Corto"), so
            // "repetir cita" starts from the same one. Only a pointer: the
            // name, price and minutes stay frozen in this row.
            $table->unsignedBigInteger('service_variant_id')->nullable()->after('service_id');
        });
    }

    public function down(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->dropColumn('service_variant_id');
        });
    }
};
