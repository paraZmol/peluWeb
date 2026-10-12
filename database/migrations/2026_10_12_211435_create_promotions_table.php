<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promotions a salon publishes (docs/promociones.md) and what a
     * booking keeps of the one that applied to it.
     */
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->cascadeOnDelete();
            // What it does to the price: a discount, or a service for free.
            $table->string('mechanic', 20);
            // The card the owner started from: it only shapes the form.
            $table->string('card', 20);
            $table->string('name', 120);
            // "Halloween", "Día de la Madre": shown above the name.
            $table->string('overline', 60)->nullable();
            // draft | published | paused. Scheduled, active and ended come from the dates.
            $table->string('status', 20)->default('draft');
            // By the date of the visit, on the salon's own calendar.
            $table->date('starts_on');
            $table->date('ends_on');
            // ISO weekdays (1 = Monday); null = every day.
            $table->json('weekdays')->nullable();
            // Minutes from midnight the visit may start in; null = any time.
            $table->unsignedSmallInteger('from_minute')->nullable();
            $table->unsignedSmallInteger('to_minute')->nullable();
            // A discount is one or the other.
            $table->unsignedTinyInteger('percent')->nullable();
            $table->unsignedInteger('amount_cents')->nullable();
            $table->foreignId('gift_service_id')->nullable()->constrained('services')->nullOnDelete();
            // Null = no limit.
            $table->unsignedInteger('quota')->nullable();
            $table->boolean('once_per_client')->default(false);
            // "everyone" today; kept for "new clients" later.
            $table->string('audience', 20)->default('everyone');
            $table->string('share_code', 16)->unique();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['salon_id', 'status', 'starts_on', 'ends_on']);
        });

        Schema::create('promotion_service', function (Blueprint $table) {
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->primary(['promotion_id', 'service_id']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('promotion_id')->nullable()->after('discount_cents')->constrained()->nullOnDelete();
            // Its name when it was applied: the promotion may change or go.
            $table->string('promotion_name', 120)->nullable()->after('promotion_id');
            // What the client saved by it, apart from any manual discount.
            $table->unsignedInteger('promotion_cents')->default(0)->after('promotion_name');
        });

        Schema::table('booking_services', function (Blueprint $table) {
            // The normal price; price_cents is what is charged.
            $table->unsignedInteger('list_price_cents')->nullable()->after('price_cents');
        });

        DB::table('booking_services')->update(['list_price_cents' => DB::raw('price_cents')]);
    }

    public function down(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->dropColumn('list_price_cents');
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn(['promotion_name', 'promotion_cents']);
        });
        Schema::dropIfExists('promotion_service');
        Schema::dropIfExists('promotions');
    }
};
