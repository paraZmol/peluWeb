<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            // What this professional earns of each service, unless the service says otherwise.
            $table->unsignedTinyInteger('commission_percent')->default(0)->after('is_active');
        });

        Schema::table('service_staff', function (Blueprint $table) {
            // Per service, one of the two (or neither): another percent, or a fixed amount.
            $table->unsignedTinyInteger('commission_percent')->nullable()->after('price_cents');
            $table->unsignedInteger('commission_cents')->nullable()->after('commission_percent');
        });

        // A period of one professional's commissions, paid at once.
        Schema::create('commission_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff');
            // Days on the salon's calendar, both included.
            $table->date('period_from');
            $table->date('period_to');
            $table->unsignedInteger('total_cents');
            $table->unsignedSmallInteger('bookings_count');
            // The payment itself lives in expenses, so cash and reports see it.
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('paid_at');
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['salon_id', 'paid_at']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            // Frozen when the visit is completed; null before that and for
            // visits completed before commissions existed.
            $table->unsignedInteger('commission_cents')->nullable()->after('charged_cents');
            $table->foreignId('commission_settlement_id')->nullable()->after('commission_cents')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commission_settlement_id');
            $table->dropColumn('commission_cents');
        });

        Schema::dropIfExists('commission_settlements');

        Schema::table('service_staff', function (Blueprint $table) {
            $table->dropColumn(['commission_percent', 'commission_cents']);
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('commission_percent');
        });
    }
};
