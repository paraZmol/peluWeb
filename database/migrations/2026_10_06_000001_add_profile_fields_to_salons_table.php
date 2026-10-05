<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->text('description')->nullable()->after('clientele');
            $table->string('logo_path')->nullable()->after('description');
            $table->string('cover_path')->nullable()->after('logo_path');
            $table->char('brand_color', 7)->nullable()->after('cover_path');
            $table->string('instagram', 60)->nullable()->after('phone');
            $table->string('address', 160)->nullable()->after('instagram');
            $table->string('district', 80)->nullable()->after('address');
            $table->string('city', 80)->nullable()->after('district');
            $table->decimal('latitude', 10, 7)->nullable()->after('city');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->dropColumn([
                'description', 'logo_path', 'cover_path', 'brand_color', 'instagram',
                'address', 'district', 'city', 'latitude', 'longitude',
            ]);
        });
    }
};
