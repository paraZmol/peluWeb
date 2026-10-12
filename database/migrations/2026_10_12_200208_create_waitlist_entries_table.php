<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Someone who found no free time on a day and asked to be told if one
     * opens up.
     */
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salon_client_id')->constrained()->cascadeOnDelete();
            // Null: anyone who performs what they want.
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            // The day on the salon's own calendar.
            $table->date('date');
            $table->json('selections');
            $table->string('status', 20)->default('waiting');
            // How many times they were told and did not book: they go after the rest.
            $table->unsignedSmallInteger('offers_count')->default(0);
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('offer_expires_at')->nullable();
            $table->timestamps();

            $table->index(['salon_id', 'date', 'status']);
            $table->index(['status', 'offer_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
