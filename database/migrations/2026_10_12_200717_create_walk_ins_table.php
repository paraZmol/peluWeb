<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The day's line of people who walked in without a booking, kept by
     * the salon. On by default where that is the usual way: barbershops.
     */
    public function up(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->boolean('walk_ins_enabled')->default(false)->after('accepts_online_bookings');
        });

        DB::table('salons')->where('business_type', 'barbershop')->update(['walk_ins_enabled' => true]);

        Schema::create('walk_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->cascadeOnDelete();
            // The day on the salon's own calendar: the line starts over each morning.
            $table->date('day');
            $table->string('name', 80);
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            // Null: whoever is free first.
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('status', 20)->default('waiting');
            $table->timestamp('called_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['salon_id', 'day', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('walk_ins');
        Schema::table('salons', function (Blueprint $table) {
            $table->dropColumn('walk_ins_enabled');
        });
    }
};
