<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('avatar_path')->nullable()->after('phone');
            $table->string('google_id')->nullable()->unique()->after('password');
            // Granted only through the `platform:grant-admin` command, never over HTTP.
            $table->boolean('is_platform_admin')->default(false)->after('google_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn(['phone', 'avatar_path', 'google_id', 'is_platform_admin']);
        });
    }
};
