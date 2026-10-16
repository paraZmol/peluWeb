<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proof that an account accepted a published legal text (docs/legal,
 * requisitos P2 to P4): which document, which version and the exact words
 * (SHA-256), when, from where and how. Rows are only ever added; they
 * outlive the account, which keeps its number in `account_number`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('account_number');
            $table->foreignId('salon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document', 40);
            $table->string('version', 20);
            $table->char('text_hash', 64);
            $table->string('channel', 20);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at');

            $table->index(['user_id', 'document', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_acceptances');
    }
};
