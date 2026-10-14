<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A promotion only with some of the team; none listed = all of them.
     */
    public function up(): void
    {
        Schema::create('promotion_staff', function (Blueprint $table) {
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->primary(['promotion_id', 'staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_staff');
    }
};
