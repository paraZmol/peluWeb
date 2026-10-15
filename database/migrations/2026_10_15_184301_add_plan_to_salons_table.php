<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A salon's plan and how long it is paid for. Salons that already exist
 * keep working as they do today: they get Equipo (what the trial gives).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('status')->constrained()->restrictOnDelete();
            $table->date('paid_until')->nullable()->after('trial_ends_at');
            $table->timestamp('founder_since')->nullable()->after('paid_until');
            $table->timestamp('trial_extended_at')->nullable()->after('founder_since');
        });

        $team = DB::table('plans')->where('code', 'equipo')->value('id');
        DB::table('salons')->whereNull('plan_id')->update(['plan_id' => $team]);
    }

    public function down(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn(['paid_until', 'founder_since', 'trial_extended_at']);
        });
    }
};
