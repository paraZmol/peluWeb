<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What makes an authorization given from the person's own account
     * stand as proof: who was signed in, from where, and exactly which
     * words they agreed to. All nullable: paper has none of it.
     */
    public function up(): void
    {
        Schema::table('client_consents', function (Blueprint $table) {
            // The account that acted as the person. Kept as a number too,
            // so the row still says something once the account is gone.
            $table->foreignId('account_id')->nullable()->after('recorded_by')->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('account_number')->nullable()->after('account_id');
            $table->string('ip', 45)->nullable()->after('account_number');
            $table->string('user_agent', 255)->nullable()->after('ip');
            $table->char('text_hash', 64)->nullable()->after('user_agent');
            // What a withdrawal removed (how many pictures, whether there was text): never the content.
            $table->json('details')->nullable()->after('text_hash');
        });
    }

    public function down(): void
    {
        Schema::table('client_consents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
            $table->dropColumn(['account_number', 'ip', 'user_agent', 'text_hash', 'details']);
        });
    }
};
