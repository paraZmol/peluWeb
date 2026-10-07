<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->cascadeOnDelete();
            // Optional: many professionals never use the app themselves.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 80);
            $table->string('title', 60)->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['salon_id', 'user_id']);
            $table->index(['salon_id', 'is_active', 'position']);
        });

        // Which services each professional performs. Null overrides mean
        // "use the service's own working time / price".
        Schema::create('service_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->unsignedSmallInteger('active_minutes')->nullable();
            $table->unsignedInteger('price_cents')->nullable();
            $table->timestamps();

            $table->unique(['staff_id', 'service_id']);
        });

        Schema::create('staff_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            // ISO weekday: 1 = Monday … 7 = Sunday. Times are salon local time.
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('access_level', 12)->default('public');
            $table->timestamps();

            $table->index(['staff_id', 'weekday', 'start_time']);
        });

        Schema::create('staff_time_off', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            // UTC, like every instant in the agenda.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason', 20);
            $table->string('note', 120)->nullable();
            $table->timestamps();

            $table->index(['staff_id', 'starts_at', 'ends_at']);
        });

        Schema::create('salon_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->cascadeOnDelete();
            // Calendar date in the salon's timezone.
            $table->date('date');
            $table->string('name', 80);
            $table->timestamps();

            $table->unique(['salon_id', 'date']);
        });

        Schema::table('salon_invitations', function (Blueprint $table) {
            // The professional profile this invitation links to when accepted.
            $table->foreignId('staff_id')->nullable()->after('invited_by')->constrained('staff')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('salon_invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_id');
        });

        Schema::dropIfExists('salon_holidays');
        Schema::dropIfExists('staff_time_off');
        Schema::dropIfExists('staff_schedules');
        Schema::dropIfExists('service_staff');
        Schema::dropIfExists('staff');
    }
};
