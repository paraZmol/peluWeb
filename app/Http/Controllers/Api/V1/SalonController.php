<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Booking\FindAvailableSlots;
use App\Actions\Booking\ListBookableDays;
use App\Actions\Booking\ResolveSalonClient;
use App\Enums\ClientLevel;
use App\Enums\SalonStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\AvailabilityRequest;
use App\Http\Resources\AvailabilityPayload;
use App\Http\Resources\BookableSalonResource;
use App\Models\Salon;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What the app needs to book in a salon. Open to anyone, like the public
 * wizard; a token only changes what its owner may book (VIP hours), which
 * is derived from the account and never from the request.
 */
class SalonController extends Controller
{
    public function __construct(
        private readonly FindAvailableSlots $finder,
        private readonly ResolveSalonClient $clients,
    ) {}

    /**
     * Services, professionals and the days that can be picked.
     */
    public function show(Request $request, Salon $salon, ListBookableDays $days): JsonResponse
    {
        abort_if($salon->status === SalonStatus::Suspended, 404);

        $staff = $this->finder->candidates($salon, null);
        $services = Service::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->where('is_active', true)
            ->with('variants')
            ->orderBy('position')
            ->get();

        return response()->json([
            'salon' => (new BookableSalonResource($salon, $services, $staff, $this->finder->performers($staff)))->resolve($request),
            'days' => $days->handle($salon, $this->clients->levelOf($salon, $request->user('sanctum')), $salon->max_days_ahead),
            'isOpen' => $salon->isWritable() && $salon->accepts_online_bookings,
        ]);
    }

    /**
     * Free starts of one day for the selection, plus what it costs.
     */
    public function availability(AvailabilityRequest $request, Salon $salon): JsonResponse
    {
        abort_if($salon->status === SalonStatus::Suspended, 404);

        $level = $this->clients->levelOf($salon, $request->user('sanctum'));

        ['slots' => $slots, 'quotes' => $quotes] = $this->finder->search(
            $salon, $request->selections(), $request->staffIn($salon), $request->localDate($salon->timezone), $level, CarbonImmutable::now(),
        );

        return response()->json([
            'slots' => AvailabilityPayload::slots(array_keys($slots), $salon),
            'quote' => AvailabilityPayload::quote($quotes, $salon->depositPercentFor($level === ClientLevel::Vip)),
        ]);
    }
}
