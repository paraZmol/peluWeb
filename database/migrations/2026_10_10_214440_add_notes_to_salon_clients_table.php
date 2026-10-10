<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salon_clients', function (Blueprint $table) {
            // How the person likes to be attended: free text, nothing about health.
            $table->text('notes')->nullable()->after('no_show_count');
            // Products, tones and times: about the work, not about the person's health.
            $table->text('formula_notes')->nullable()->after('notes');
            // Allergies and reactions: health data, kept only while the
            // person's written authorization is on record.
            $table->text('care_notes')->nullable()->after('formula_notes');
            $table->dateTime('care_consent_at')->nullable()->after('care_notes');
            $table->foreignId('care_consent_by')->nullable()->after('care_consent_at')->constrained('users')->nullOnDelete();
        });

        // Every authorization given or withdrawn, never edited nor deleted:
        // it is the proof, and it outlives the notes it allowed.
        Schema::create('client_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salon_client_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('action', 20);
            // How it was given: today only on paper, signed and kept by the salon.
            $table->string('method', 20);
            $table->string('text_version', 10);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('created_at');

            $table->index(['salon_client_id', 'kind', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('salon_clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('care_consent_by');
            $table->dropColumn(['notes', 'formula_notes', 'care_notes', 'care_consent_at']);
        });
        Schema::dropIfExists('client_consents');
    }
};
