<?php

namespace App\Http\Controllers\Client;

use App\Actions\Booking\ResolveSalonClient;
use App\Actions\Waitlist\JoinWaitlist;
use App\Actions\Waitlist\MyWaitlist;
use App\Enums\SalonStatus;
use App\Enums\WaitlistStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\AvailabilityRequest;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * A person's own places on waiting lists. They are not members of those
 * salons: ownership replaces the salon scope, so a place that is not
 * theirs is a 404.
 */
class WaitlistController extends Controller
{
    /**
     * The same "what, with whom, which day" the wizard asks free times
     * for, on a day that has none.
     */
    public function store(AvailabilityRequest $request, Salon $salon, JoinWaitlist $join, ResolveSalonClient $clients): RedirectResponse
    {
        abort_if($salon->status === SalonStatus::Suspended, 404);
        abort_unless($salon->isWritable() && $salon->accepts_online_bookings, 404);

        /** @var User $user */
        $user = $request->user();

        $join->handle(
            $clients->forUser($salon, $user),
            $request->selections(),
            $request->staffIn($salon),
            $request->localDate($salon->timezone),
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Listo: te avisaremos si se libera un horario ese día.']);

        return back();
    }

    public function destroy(Request $request, int $entry, MyWaitlist $mine): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $place = $mine->query($user)->active()->findOrFail($entry);
        $place->status = WaitlistStatus::Cancelled;
        $place->save();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Saliste de la lista de espera.']);

        return back();
    }
}
