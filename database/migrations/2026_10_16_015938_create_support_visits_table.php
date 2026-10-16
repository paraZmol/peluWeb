<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Entrar como salón" (PLAN 7.4): every time the platform admin looks at a
 * salon from the inside, who, which salon, why, from where and for how long.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('salon_id')->constrained()->restrictOnDelete();
            $table->string('reason', 200);
            $table->string('ip', 45)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['salon_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_visits');
    }
};
