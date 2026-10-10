<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One working day of the till: opened with a float, closed by
        // counting the cash and comparing it with what should be there.
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->cascadeOnDelete();
            $table->dateTime('opened_at');
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('opening_cash_cents');
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            // Frozen when it closes: later corrections never rewrite a past count.
            $table->integer('expected_cash_cents')->nullable();
            $table->unsignedInteger('counted_cash_cents')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['salon_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
