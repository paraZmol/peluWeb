<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every time money changes hands: a deposit, the balance of a visit
        // (one row per method when it is split) or a deposit given back.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            // Who attended, for commissions; kept when the booking is gone.
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('kind', 10);
            $table->string('method', 10);
            // Céntimos, always positive: `kind` says whether it came in or went out.
            $table->unsignedInteger('amount_cents');
            $table->dateTime('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['salon_id', 'paid_at']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedInteger('discount_cents')->default(0)->after('price_cents');
            // The tip belongs to whoever attended: never part of the salon's income.
            $table->unsignedInteger('tip_cents')->default(0)->after('discount_cents');
            // What the visit finally cost (price − discount), set when it is completed.
            $table->unsignedInteger('charged_cents')->nullable()->after('tip_cents');
            $table->dateTime('deposit_refunded_at')->nullable()->after('deposit_refundable');
        });

        // Visits completed before payments existed were charged in full.
        DB::table('bookings')->where('status', 'completed')->update(['charged_cents' => DB::raw('price_cents')]);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['discount_cents', 'tip_cents', 'charged_cents', 'deposit_refunded_at']);
        });

        Schema::dropIfExists('payments');
    }
};
