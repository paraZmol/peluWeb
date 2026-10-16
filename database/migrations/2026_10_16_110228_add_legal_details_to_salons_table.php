<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who provides the service at a salon, and where its own Libro de
 * Reclamaciones is (requirement P7). All optional: existing salons keep
 * working without them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->string('legal_name', 200)->nullable()->after('instagram');
            $table->string('ruc', 11)->nullable()->after('legal_name');
            $table->string('contact_email', 255)->nullable()->after('ruc');
            $table->string('complaints_book_url', 500)->nullable()->after('contact_email');
        });
    }

    public function down(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->dropColumn(['legal_name', 'ruc', 'contact_email', 'complaints_book_url']);
        });
    }
};
