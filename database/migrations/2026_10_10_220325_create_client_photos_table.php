<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Before/after pictures of a client's work, for the salon's own
        // record. The files live on the private disk; nothing here is public.
        Schema::create('client_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salon_client_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);
            $table->string('path');
            // The day it was taken, on the salon's calendar.
            $table->date('taken_on');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['salon_client_id', 'taken_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_photos');
    }
};
