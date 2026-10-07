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
use App\Models\StaffSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The team's appointments: one day as a list, one day on a clock with a
 * column per professional ("tablero"), or one professional's week.
 * Owners and whoever may see everyone's agenda choose a professional or
 * see all; anyone else sees only their own.
 */
class AgendaController extends SalonAreaController
{
    private const string LIST = 'lista';

    private const string BOARD = 'tablero';

    private const string WEEK = 'semana';

    private const int DAYS_PER_WEEK = 7;

    /** Hours the clock shows when nobody has a schedule yet. */
    private const array DEFAULT_HOURS = ['from' => 8, 'to' => 20];

    public function index(Request $request, ListBookableDays $days): Response
    {
        $salon = $this->currentSalon();
        $seesEveryone = $request->user()?->membershipIn($salon)?->hasPermission(SalonPermission::ViewAllSchedules) ?? false;
        $own = Staff::query()->where('user_id', $request->user()?->id)->first();
        $team = $seesEveryone ? $salon->staff()->where('is_active', true)->get() : collect();
        $view = $this->view($request, $team);
        $date = $this->date($request, $salon);
        $from = $view === self::WEEK ? $date->startOfWeek(CarbonImmutable::MONDAY) : $date->startOfDay();
        $until = $view === self::WEEK ? $from->addDays(self::DAYS_PER_WEEK) : $from->addDay();
        $staffId = $this->staffId($request, $view, $seesEveryone ? $team : null, $own);

        $bookings = Booking::query()
            ->with(['client', 'staff', 'services'])
            ->where('starts_at', '>=', $from->utc())
            ->where('starts_at', '<', $until->utc())
            ->where('status', '!=', BookingStatus::Expired)
            ->when($staffId !== null, fn ($query) => $query->where('staff_id', $staffId))
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('staff/agenda', [
            'view' => $view,
            'date' => $date->toDateString(),
            'today' => $salon->localNow()->toDateString(),
            'weekDays' => $view === self::WEEK
                ? collect(range(0, self::DAYS_PER_WEEK - 1))->map(fn (int $offset) => $from->addDays($offset)->toDateString())
                : [],
            'bookings' => $bookings->map(fn (Booking $booking) => (new AgendaBookingResource($booking, $salon->timezone))->resolve($request)),
            'staffOptions' => $team->map(fn (Staff $staff) => ['id' => $staff->id, 'name' => $staff->name])->values(),
            'selectedStaffId' => $seesEveryone ? $staffId : null,
            'hasProfile' => $own !== null,
            'seesEveryone' => $seesEveryone,
            'canBook' => $request->user()?->can('create', [Booking::class, $salon]) ?? false,
            // The days of the picker for moving a booking.
            'days' => $days->handle($salon, ClientLevel::Team, AgendaBookingController::DAYS_AHEAD),
            'hours' => $view === self::LIST ? self::DEFAULT_HOURS : $this->hours($salon, $bookings),
            'slotStep' => $salon->slot_step_minutes,
        ]);
    }

    /**
     * The board has a column per professional, so it needs a team to show.
     *
     * @param  Collection<int, Staff>  $team
     */
    private function view(Request $request, Collection $team): string
    {
        return match ($request->string('vista')->toString()) {
            self::WEEK => self::WEEK,
            self::BOARD => $team->count() > 1 ? self::BOARD : self::LIST,
            default => self::LIST,
        };
    }

    /**
     * Whose appointments to show; null = the whole team. A week is always
     * one professional's; without the permission it is the viewer's own
     * profile (0 = nobody, when they have none).
     *
     * @param  Collection<int, Staff>|null  $team  null when the viewer only sees themselves
     */
    private function staffId(Request $request, string $view, ?Collection $team, ?Staff $own): ?int
    {
        if ($team === null) {
            return $own->id ?? 0;
        }

        $asked = $team->firstWhere('id', $request->integer('profesional'));

        return match ($view) {
            self::BOARD => null,
            self::WEEK => ($asked ?? $team->firstWhere('id', $own?->id) ?? $team->first())?->id,
            default => $asked?->id,
        };
    }

    /**
     * First and last hour of the clock: the salon's working hours, widened
     * to fit any appointment outside them.
     *
     * @param  Collection<int, Booking>  $bookings
     * @return array{from: int, to: int}
     */
    private function hours(Salon $salon, Collection $bookings): array
    {
        $schedules = StaffSchedule::query()->get();
        $from = $schedules->isEmpty() ? self::DEFAULT_HOURS['from'] : (int) substr((string) $schedules->min('start_time'), 0, 2);
        $to = $schedules->isEmpty() ? self::DEFAULT_HOURS['to'] : (int) ceil($this->minutes((string) $schedules->max('end_time')) / 60);

        foreach ($bookings as $booking) {
            $start = $booking->starts_at->setTimezone($salon->timezone);
            $end = $booking->ends_at->setTimezone($salon->timezone);
            $from = min($from, $start->hour);
            // One that runs past midnight is drawn up to the end of its day.
            $to = max($to, $end->isSameDay($start) ? (int) ceil(($end->hour * 60 + $end->minute) / 60) : 24);
        }

        return ['from' => max(0, $from), 'to' => min(24, max($to, $from + 1))];
    }

    private function minutes(string $time): int
    {
        return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
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
