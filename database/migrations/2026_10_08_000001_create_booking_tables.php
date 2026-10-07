<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            // Booking rules; every one has a default so existing salons keep working.
            $table->unsignedSmallInteger('min_lead_minutes')->default(60);
            $table->unsignedSmallInteger('max_days_ahead')->default(60);
            $table->unsignedTinyInteger('slot_step_minutes')->default(15);
            $table->unsignedSmallInteger('hold_minutes')->default(30);
            $table->unsignedSmallInteger('cancel_window_hours')->default(24);
            $table->unsignedTinyInteger('deposit_percent')->default(0);
            $table->unsignedTinyInteger('vip_deposit_percent')->nullable();
            // Free text shown with the deposit request ("Yape 987 654 321 a nombre de …").
            $table->string('payment_instructions', 255)->nullable();
            $table->boolean('accepts_online_bookings')->default(true);
        });

        // A person as one salon knows them: VIP, notes and no-shows are per salon.
        Schema::create('salon_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->cascadeOnDelete();
            // Null for people the team books by phone or in person.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 80);
            $table->string('phone', 20)->nullable();
            $table->boolean('is_vip')->default(false);
            $table->unsignedSmallInteger('no_show_count')->default(0);
            $table->timestamps();

            $table->unique(['salon_id', 'user_id']);
            $table->index(['salon_id', 'phone']);
            $table->index(['salon_id', 'name']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->cascadeOnDelete();
            // Short public reference used in URLs and WhatsApp messages.
            $table->char('code', 8)->unique();
            $table->foreignId('salon_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff');
            $table->string('status', 12);
            $table->string('source', 10);
            // UTC. The whole visit, idle (processing) time included.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('is_vip')->default(false);
            // Money in céntimos. Prices are frozen at booking time.
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('deposit_cents')->default(0);
            $table->dateTime('deposit_paid_at')->nullable();
            $table->boolean('deposit_refundable')->nullable();
            // While pending: after this instant the booking stops holding its
            // slot by itself, even if no scheduled task has run.
            $table->dateTime('hold_expires_at')->nullable();
            $table->string('client_note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['salon_id', 'starts_at']);
            $table->index(['staff_id', 'starts_at']);
            $table->index(['salon_client_id', 'starts_at']);
            $table->index(['status', 'hold_expires_at']);
        });

        // What was booked, with name, price and durations frozen.
        Schema::create('booking_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 170);
            $table->unsignedInteger('price_cents');
            $table->unsignedSmallInteger('active_minutes');
            $table->unsignedSmallInteger('idle_minutes')->default(0);
            $table->unsignedSmallInteger('finish_minutes')->default(0);
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();
        });

        // The stretches in which the professional is actually busy. During a
        // color's processing time there is no segment: the slot can be sold.
        Schema::create('booking_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->index(['staff_id', 'starts_at', 'ends_at']);
        });

        // Audit trail of every status change.
        Schema::create('booking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 12)->nullable();
            $table->string('to_status', 12);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->dateTime('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_events');
        Schema::dropIfExists('booking_segments');
        Schema::dropIfExists('booking_services');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('salon_clients');

        Schema::table('salons', function (Blueprint $table) {
            $table->dropColumn([
                'min_lead_minutes', 'max_days_ahead', 'slot_step_minutes', 'hold_minutes', 'cancel_window_hours',
                'deposit_percent', 'vip_deposit_percent', 'payment_instructions', 'accepts_online_bookings',
            ]);
        });
    }
};
