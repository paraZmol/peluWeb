<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What each salon paid the platform, recorded by hand by the platform
 * admin (Yape or transfer in v1), and the days it covers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('months');
            $table->unsignedInteger('amount_cents');
            $table->string('method', 20);
            $table->date('covers_from');
            $table->date('covers_until');
            $table->boolean('is_founder')->default(false);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 200)->nullable();
            $table->timestamps();

            $table->index(['salon_id', 'covers_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
