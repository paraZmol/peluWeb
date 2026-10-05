<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salons', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('business_type', 30);
            $table->string('clientele', 20);
            $table->string('phone', 20)->nullable();
            $table->string('timezone', 64)->default('America/Lima');
            $table->char('currency', 3)->default('PEN');
            $table->string('status', 20)->default('trial')->index();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salons');
    }
};
