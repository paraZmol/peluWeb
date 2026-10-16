<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The last day the owners were reminded that the trial or the paid time is
 * ending, so the daily task never mails twice the same day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->date('billing_notice_on')->nullable()->after('trial_extended_at');
        });
    }

    public function down(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->dropColumn('billing_notice_on');
        });
    }
};
