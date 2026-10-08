<?php

use App\Enums\SalonTheme;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            // The design template of the public page; existing salons get the default.
            $table->string('theme', 20)->default(SalonTheme::default()->value)->after('brand_color');
        });
    }

    public function down(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->dropColumn('theme');
        });
    }
};
