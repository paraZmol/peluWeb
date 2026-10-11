<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            // hidden: not asked for nor shown; private: only the salon reads
            // them; public: all of them on the salon's page.
            $table->string('review_mode', 10)->default('private');
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->cascadeOnDelete();
            // One per completed visit: that is what makes it verified.
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('salon_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff');
            $table->unsignedTinyInteger('rating');
            $table->string('comment', 600)->nullable();
            // First name only, copied when written; "Cliente" once the account is gone.
            $table->string('author_name', 40);
            $table->string('reply', 600)->nullable();
            $table->dateTime('replied_at')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            // The salon may ask the platform to look at it; only the platform hides it.
            $table->dateTime('reported_at')->nullable();
            $table->string('report_reason', 300)->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('report_resolved_at')->nullable();
            $table->dateTime('hidden_at')->nullable();
            // Taken back by who wrote it. The row stays, so the visit cannot be
            // reviewed again to dodge a report or drop the salon's answer.
            $table->dateTime('withdrawn_at')->nullable();
            // Written while the salon showed its reviews: the author was told it
            // would be public. Reviews written as private never are.
            $table->boolean('is_publishable')->default(false);
            $table->timestamps();

            $table->index(['salon_id', 'created_at']);
            $table->index(['reported_at', 'report_resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::table('salons', function (Blueprint $table) {
            $table->dropColumn('review_mode');
        });
    }
};
