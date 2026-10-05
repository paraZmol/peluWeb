<?php

use App\Actions\Catalog\SyncServiceTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The catalog is reference data every environment needs (including the
     * test database), so it ships as a data migration. Later catalog edits
     * use `php artisan catalog:sync`.
     */
    public function up(): void
    {
        app(SyncServiceTemplates::class)->handle();
    }

    public function down(): void
    {
        DB::table('service_templates')->delete();
    }
};
