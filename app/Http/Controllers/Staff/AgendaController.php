<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Booking\ListBookableDays;
use App\Enums\BookingStatus;
use App\Enums\ClientLevel;
use App\Enums\SalonPermission;
use App\Http\Controllers\Owner\SalonAreaController;
use App\Http\Resources\AgendaBookingResource;
use App\Models\Booking;
use App\Models\Salon;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The day's appointments. Owners and whoever may see everyone's agenda
 * choose a professional or see all; anyone else sees only their own.
 */
class AgendaController extends SalonAreaController
{
    public function index(Request $request, ListBookableDays $days): Response
    {
        $salon = $this->currentSalon();
        $seesEveryone = $request->user()?->membershipIn($salon)?->hasPermission(SalonPermission::ViewAllSchedules) ?? false;
        $own = Staff::query()->where('user_id', $request->user()?->id)->first();
        $date = $this->date($request, $salon);
        $staffId = $seesEveryone ? ($request->integer('profesional') ?: null) : ($own->id ?? 0);

        $bookings = Booking::query()
            ->with(['client', 'staff', 'services'])
            ->whereBetween('starts_at', [$date->startOfDay()->utc(), $date->endOfDay()->utc()])
            ->where('status', '!=', BookingStatus::Expired)
            ->when($staffId !== null, fn ($query) => $query->where('staff_id', $staffId))
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('staff/agenda', [
            'date' => $date->toDateString(),
            'today' => $salon->localNow()->toDateString(),
            'bookings' => $bookings->map(fn (Booking $booking) => (new AgendaBookingResource($booking, $salon->timezone))->resolve($request)),
            'staffOptions' => $seesEveryone
                ? $salon->staff()->where('is_active', true)->get()->map(fn (Staff $staff) => ['id' => $staff->id, 'name' => $staff->name])
                : [],
            'selectedStaffId' => $seesEveryone ? $staffId : null,
            'hasProfile' => $own !== null,
            'seesEveryone' => $seesEveryone,
            'canBook' => $request->user()?->can('create', [Booking::class, $salon]) ?? false,
            // The days of the picker for moving a booking.
            'days' => $days->handle($salon, ClientLevel::Team, AgendaBookingController::DAYS_AHEAD),
        ]);
    }

    /**
     * The requested day on the salon's clock; today when missing or invalid.
     */
    private function date(Request $request, Salon $salon): CarbonImmutable
    {
        $value = $request->string('fecha')->toString();

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value, $salon->timezone) ?: $salon->localNow();
        }

        return $salon->localNow();
    }
}
