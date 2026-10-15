<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each movement remembers the cash session that was open when it was
     * recorded, instead of being matched to one by its timestamp: seconds
     * are too coarse to tell which side of a closing it fell on.
     */
    public function up(): void
    {
        foreach (['payments', 'expenses'] as $movements) {
            Schema::table($movements, function (Blueprint $table) {
                // Null: recorded with the till closed, or an expense of another day.
                $table->foreignId('cash_session_id')->nullable()->after('salon_id')->constrained()->nullOnDelete();
            });

            // What already exists goes to the session whose window it fell in.
            DB::statement("
                UPDATE {$movements} m
                JOIN cash_sessions s ON s.salon_id = m.salon_id
                    AND m.paid_at >= s.opened_at
                    AND (s.closed_at IS NULL OR m.paid_at < s.closed_at)
                SET m.cash_session_id = s.id
            ");
        }
    }

    public function down(): void
    {
        foreach (['payments', 'expenses'] as $movements) {
            Schema::table($movements, function (Blueprint $table) {
                $table->dropConstrainedForeignId('cash_session_id');
            });
        }
    }
};
