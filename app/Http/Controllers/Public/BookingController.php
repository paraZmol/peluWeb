<?php

namespace App\Http\Controllers\Public;

use App\Actions\Booking\CreateBooking;
use App\Actions\Booking\FindAvailableSlots;
use App\Actions\Booking\QuoteBooking;
use App\Actions\Booking\ResolveSalonClient;
use App\Data\Booking\BookingQuote;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\ClientLevel;
use App\Enums\SalonStatus;
use App\Enums\ScheduleAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\AvailabilityRequest;
use App\Http\Requests\Public\StoreBookingRequest;
use App\Http\Resources\BookableSalonResource;
use App\Models\Salon;
use App\Models\SalonHoliday;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffSchedule;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public booking wizard of a salon. Visitors have no current salon, so
 * every query names the salon from the URL. What someone may book (VIP
 * hours, deposit) is derived from their account, never from the request.
 */
class BookingController extends Controller
{
    public function __construct(
        private readonly FindAvailableSlots $finder,
        private readonly ResolveSalonClient $clients,
    ) {}

    public function create(Request $request, Salon $salon): Response
    {
        abort_if($salon->status === SalonStatus::Suspended, 404);

        $staff = $this->finder->candidates($salon, null);
        $services = Service::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->where('is_active', true)
            ->with('variants')
            ->orderBy('position')
            ->get();

        return Inertia::render('public/booking', [
            'salon' => new BookableSalonResource($salon, $services, $staff, $this->performers($staff)),
            'days' => $this->days($salon, $request->user()),
            'isOpen' => $salon->isWritable() && $salon->accepts_online_bookings,
            'viewer' => $this->viewer($request->user()),
            'preselectedServiceId' => $request->integer('servicio') ?: null,
        ]);
    }

    /**
     * Free starts of one day for the selection, plus what it costs.
     */
    public function availability(AvailabilityRequest $request, Salon $salon): JsonResponse
    {
        abort_if($salon->status === SalonStatus::Suspended, 404);

        $selections = $request->selections();
        $staff = $request->staffIn($salon);
        $level = $this->level($salon, $request->user());

        $slots = $this->finder->handle($salon, $selections, $staff, $request->localDate($salon->timezone), $level, CarbonImmutable::now());
        $quotes = app(QuoteBooking::class)->handle($salon, $selections, $this->finder->candidates($salon, $staff));

        return response()->json([
            'slots' => collect(array_keys($slots))->map(fn (int $start) => [
                'start' => $start,
                'time' => CarbonImmutable::createFromTimestamp($start, $salon->timezone)->format('H:i'),
            ]),
            'quote' => $this->quoteSummary($salon, $quotes, $level),
        ]);
    }

    public function store(StoreBookingRequest $request, Salon $salon, CreateBooking $create): RedirectResponse
    {
        abort_if($salon->status === SalonStatus::Suspended, 404);

        /** @var User $user */
        $user = $request->user();

        $booking = $create->handle(new NewBooking(
            salon: $salon,
            client: $this->clients->forUser($salon, $user),
            selections: $request->selections(),
            staff: $request->staffIn($salon),
            startsAt: $request->startsAt(),
            source: BookingSource::Client,
            actor: $user,
            note: $request->validated('note'),
        ));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $booking->needsDeposit() ? 'Reserva creada. Falta enviar la seña.' : '¡Reserva confirmada!',
        ]);

        return to_route('bookings.show', $booking->code);
    }

    /**
     * Comes back here after signing in, so the wizard can restore what the
     * visitor had chosen (it keeps it in the browser).
     */
    public function resume(Salon $salon): RedirectResponse
    {
        return to_route('salons.booking.create', ['salon' => $salon->slug, 'continuar' => 1]);
    }

    /**
     * VIP only for someone this salon marked as such; everyone else,
     * signed in or not, is a regular client.
     */
    private function level(Salon $salon, ?User $user): ClientLevel
    {
        return $user === null ? ClientLevel::Standard : ($this->clients->find($salon, $user)?->level() ?? ClientLevel::Standard);
    }

    /**
     * @return array{state: 'guest'|'unverified'|'ready', name: string|null}
     */
    private function viewer(?User $user): array
    {
        return [
            'state' => match (true) {
                $user === null => 'guest',
                ! $user->hasVerifiedEmail() => 'unverified',
                default => 'ready',
            },
            'name' => $user?->name,
        ];
    }

    /**
     * Which professionals perform each service.
     *
     * @param  Collection<int, Staff>  $staff
     * @return Collection<int, Collection<int, int>> service id => staff ids
     */
    private function performers(Collection $staff): Collection
    {
        $performers = [];

        foreach (DB::table('service_staff')->whereIn('staff_id', $staff->pluck('id'))->get(['service_id', 'staff_id']) as $row) {
            $performers[(int) $row->service_id][] = (int) $row->staff_id;
        }

        return collect($performers)->map(fn (array $staffIds) => collect($staffIds));
    }

    /**
     * The days a client can pick, each flagged as closed when nobody could
     * attend them (no hours for their level that weekday, or a holiday).
     *
     * @return list<array{date: string, closed: bool}>
     */
    private function days(Salon $salon, ?User $user): array
    {
        $today = $salon->localNow()->startOfDay();
        $allowed = array_map(fn (ScheduleAccess $access) => $access->value, $this->level($salon, $user)->allowedAccess());

        $workingWeekdays = StaffSchedule::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->whereIn('access_level', $allowed)
            ->whereHas('staff', fn ($staff) => $staff->withoutGlobalScope('salon')->where('is_active', true))
            ->distinct()
            ->pluck('weekday')
            ->all();

        $holidays = SalonHoliday::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->whereDate('date', '>=', $today->toDateString())
            ->get()
            ->map(fn (SalonHoliday $holiday): string => $holiday->date->format('Y-m-d'))
            ->all();

        $days = [];

        for ($offset = 0; $offset <= $salon->max_days_ahead; $offset++) {
            $day = $today->addDays($offset);
            $days[] = [
                'date' => $day->toDateString(),
                'closed' => ! in_array($day->dayOfWeekIso, $workingWeekdays, true) || in_array($day->toDateString(), $holidays, true),
            ];
        }

        return $days;
    }

    /**
     * Price, length and deposit of the selection. With "anyone" the price
     * may depend on who attends: the lowest is shown as "from".
     *
     * @param  array<int, BookingQuote>  $quotes
     * @return array<string, mixed>|null
     */
    private function quoteSummary(Salon $salon, array $quotes, ClientLevel $level): ?array
    {
        if ($quotes === []) {
            return null;
        }

        $prices = array_map(fn (BookingQuote $quote): int => $quote->priceCents(), $quotes);
        $minutes = array_map(fn (BookingQuote $quote): int => $quote->shape()->totalMinutes, $quotes);
        $percent = $salon->depositPercentFor($level === ClientLevel::Vip);

        return [
            'price' => Money::toAmount(min($prices)),
            'priceVaries' => min($prices) !== max($prices),
            'minutes' => min($minutes),
            'minutesVary' => min($minutes) !== max($minutes),
            'depositPercent' => $percent,
            'deposit' => Money::toAmount(intdiv(min($prices) * $percent + 50, 100)),
        ];
    }
}
