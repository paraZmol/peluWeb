<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Booking\CreateBooking;
use App\Actions\Booking\FindAvailableSlots;
use App\Actions\Booking\ListBookableDays;
use App\Actions\Booking\ResolveSalonClient;
use App\Actions\Promotions\ApplyPromotion;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\ClientLevel;
use App\Enums\SalonPermission;
use App\Http\Controllers\Owner\SalonAreaController;
use App\Http\Requests\Public\AvailabilityRequest;
use App\Http\Requests\Staff\StoreAgendaBookingRequest;
use App\Http\Resources\AvailabilityPayload;
use App\Http\Resources\BookableSalonResource;
use App\Models\Booking;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\Service;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The team books for a client who called, wrote or walked in: any of the
 * professional's hours, no deposit, and the client needs no account.
 * Whoever runs everyone's agenda books with anyone; a professional only
 * with themselves.
 */
class AgendaBookingController extends SalonAreaController
{
    public const int DAYS_AHEAD = 60;

    private const int CLIENT_SUGGESTIONS = 8;

    private const int MIN_SEARCH_LENGTH = 2;

    public function __construct(
        private readonly FindAvailableSlots $finder,
        private readonly ResolveSalonClient $clients,
    ) {}

    public function create(Request $request, ListBookableDays $days): Response
    {
        $salon = $this->bookingSalon();
        $staff = $this->finder->candidates($salon, $this->staffFor($request, $salon, null));
        $services = Service::query()->where('is_active', true)->with('variants')->orderBy('position')->get();

        return Inertia::render('staff/booking-create', [
            'salon' => new BookableSalonResource($salon, $services, $staff, $this->finder->performers($staff)),
            'days' => $days->handle($salon, ClientLevel::Team, self::DAYS_AHEAD),
        ]);
    }

    /**
     * Free starts of one day for the selection, in any working hours.
     */
    public function availability(AvailabilityRequest $request, ApplyPromotion $promotions): JsonResponse
    {
        $salon = $this->bookingSalon();
        $staff = $this->staffFor($request, $salon, $request->staffIn($salon));

        ['slots' => $slots, 'quotes' => $quotes] = $this->finder->search(
            $salon, $request->selections(), $staff, $request->localDate($salon->timezone), ClientLevel::Team, CarbonImmutable::now(),
        );

        $cheapest = AvailabilityPayload::cheapest($quotes);

        return response()->json([
            'slots' => AvailabilityPayload::slots(
                array_keys($slots),
                $salon,
                $cheapest === null ? [] : $promotions->forStarts($salon, null, $cheapest, array_keys($slots)),
            ),
            'quote' => AvailabilityPayload::quote($quotes, 0),
        ]);
    }

    /**
     * Clients already in the salon's list whose name or phone matches.
     * Whoever runs every agenda searches the whole list; a professional
     * only among the people they have attended, so the search cannot be
     * used to copy the salon's client list.
     */
    public function clients(Request $request): JsonResponse
    {
        $salon = $this->bookingSalon();
        $own = $this->staffFor($request, $salon, null);
        $search = $request->string('buscar')->trim()->limit(60, '')->toString();

        if (mb_strlen($search) < self::MIN_SEARCH_LENGTH) {
            return response()->json(['clients' => []]);
        }

        // "%" and "_" typed by the user are text to find, not wildcards.
        $like = '%'.addcslashes($search, '%_\\').'%';

        $clients = SalonClient::query()
            ->where(fn ($match) => $match->whereLike('name', $like)->orWhereLike('phone', $like))
            ->when($own !== null, fn ($query) => $query->whereHas('bookings', fn ($bookings) => $bookings->where('staff_id', $own?->id)))
            ->orderBy('name')
            ->limit(self::CLIENT_SUGGESTIONS)
            ->get();

        return response()->json(['clients' => $clients->map(fn (SalonClient $client) => [
            'id' => $client->id,
            'name' => $client->name,
            'phone' => $client->phone,
            'isVip' => $client->is_vip,
        ])]);
    }

    public function store(StoreAgendaBookingRequest $request, CreateBooking $create): RedirectResponse
    {
        $salon = $this->bookingSalon();
        $staff = $this->staffFor($request, $salon, $request->staffIn($salon));
        $client = $request->clientIn($salon, $this->clients, $this->staffFor($request, $salon, null));

        // Never wrap this in a transaction: see Public\BookingController::store.
        $booking = $create->handle(new NewBooking(
            salon: $salon,
            client: $client,
            selections: $request->selections(),
            staff: $staff,
            startsAt: $request->startsAt(),
            source: BookingSource::Staff,
            actor: $request->user(),
            note: $request->validated('note'),
            // Marked by default; the team may book at the normal price on purpose.
            withPromotion: $request->boolean('with_promotion', true),
        ));

        $this->toast("Cita agendada para {$client->name}.");

        return to_route('agenda.index', ['fecha' => $booking->starts_at->setTimezone($salon->timezone)->toDateString()]);
    }

    private function bookingSalon(): Salon
    {
        $salon = $this->currentSalon();
        $this->authorize('create', [Booking::class, $salon]);

        return $salon;
    }

    /**
     * Who the booking may be with: the chosen professional (null = anyone)
     * for those who run everyone's agenda; otherwise only the viewer's own
     * profile, whatever was asked for.
     */
    private function staffFor(Request $request, Salon $salon, ?Staff $chosen): ?Staff
    {
        if ($request->user()?->membershipIn($salon)?->hasPermission(SalonPermission::ViewAllSchedules)) {
            return $chosen;
        }

        $own = Staff::query()->where('user_id', $request->user()?->id)->where('is_active', true)->first();
        abort_if($own === null || ($chosen !== null && $chosen->id !== $own->id), 403);

        return $own;
    }
}
