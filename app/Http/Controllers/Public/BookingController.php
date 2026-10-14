<?php

namespace App\Http\Controllers\Public;

use App\Actions\Booking\CreateBooking;
use App\Actions\Booking\FindAvailableSlots;
use App\Actions\Booking\ListBookableDays;
use App\Actions\Booking\ResolveSalonClient;
use App\Actions\Promotions\ApplyPromotion;
use App\Actions\Promotions\PublicPromotions;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\ClientLevel;
use App\Enums\SalonStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\AvailabilityRequest;
use App\Http\Requests\Public\StoreBookingRequest;
use App\Http\Resources\AvailabilityPayload;
use App\Http\Resources\BookableSalonResource;
use App\Models\Booking;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

    public function create(Request $request, Salon $salon, ListBookableDays $days): Response
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
            'salon' => new BookableSalonResource($salon, $services, $staff, $this->finder->performers($staff)),
            'days' => $days->handle($salon, $this->clients->levelOf($salon, $request->user()), $salon->max_days_ahead),
            'isOpen' => $salon->isWritable() && $salon->accepts_online_bookings,
            'viewer' => $this->viewer($request->user()),
            'preselectedServiceId' => $request->integer('servicio') ?: null,
            'repeat' => $this->repeat($request, $salon, $services, $staff),
            'promotions' => app(PublicPromotions::class)->handle($salon)['items'],
        ])->withViewData(SalonPageController::installData($salon));
    }

    /**
     * Free starts of one day for the selection, plus what it costs.
     */
    public function availability(AvailabilityRequest $request, Salon $salon, ApplyPromotion $promotions): JsonResponse
    {
        abort_if($salon->status === SalonStatus::Suspended, 404);

        $selections = $request->selections();
        $staff = $request->staffIn($salon);
        $level = $this->clients->levelOf($salon, $request->user());

        ['slots' => $slots, 'quotes' => $quotes] = $this->finder->search(
            $salon, $selections, $staff, $request->localDate($salon->timezone), $level, CarbonImmutable::now(),
        );

        $cheapest = AvailabilityPayload::cheapest($quotes);
        $client = $request->user() === null ? null : $this->clients->forUser($salon, $request->user());

        return response()->json([
            'slots' => AvailabilityPayload::slots(
                array_keys($slots),
                $salon,
                $cheapest === null ? [] : $promotions->forStarts($salon, $client, $cheapest, array_keys($slots)),
            ),
            'quote' => AvailabilityPayload::quote($quotes, $salon->depositPercentFor($level === ClientLevel::Vip)),
        ]);
    }

    public function store(StoreBookingRequest $request, Salon $salon, CreateBooking $create): RedirectResponse
    {
        abort_if($salon->status === SalonStatus::Suspended, 404);

        /** @var User $user */
        $user = $request->user();

        // Never wrap this in a transaction: CreateBooking must open its own
        // and lock the professional before it reads anything (a read made
        // earlier in the same transaction would pin a stale view of the
        // agenda and let two people book the same time).
        $booking = $create->handle(new NewBooking(
            salon: $salon,
            client: $this->clients->forUser($salon, $user),
            selections: $request->selections(),
            staff: $request->staffIn($salon),
            startsAt: $request->startsAt(),
            source: BookingSource::Client,
            actor: $user,
            note: $request->validated('note'),
            expectsPromotion: $request->boolean('expects_promotion'),
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
     * "Repetir cita": the services and the professional of one of the
     * viewer's own bookings in this salon, as far as they still exist.
     * Only a starting point for the wizard: booking checks everything again.
     *
     * @param  Collection<int, Service>  $services  the salon's active services
     * @param  Collection<int, Staff>  $staff  the salon's active professionals
     * @return array{selections: list<array{serviceId: int, variantId: int|null}>, staffId: int|null}|null
     */
    private function repeat(Request $request, Salon $salon, Collection $services, Collection $staff): ?array
    {
        $code = $request->string('repetir')->toString();
        $user = $request->user();

        if ($code === '' || $user === null) {
            return null;
        }

        $booking = Booking::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->where('code', $code)
            ->whereHas('client', fn ($client) => $client->withoutGlobalScope('salon')->where('user_id', $user->id))
            ->with('services')
            ->first();

        if ($booking === null) {
            return null;
        }

        $selections = [];

        foreach ($booking->services as $booked) {
            $service = $services->firstWhere('id', $booked->service_id);

            if ($service !== null) {
                // The option booked then, if the service still offers it.
                $selections[] = [
                    'serviceId' => $service->id,
                    'variantId' => $service->variants->firstWhere('id', $booked->service_variant_id)?->id,
                ];
            }
        }

        if ($selections === []) {
            return null;
        }

        return ['selections' => $selections, 'staffId' => $staff->has($booking->staff_id) ? $booking->staff_id : null];
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
}
