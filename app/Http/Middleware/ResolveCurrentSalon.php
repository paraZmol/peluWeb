<?php

namespace App\Http\Middleware;

use App\Actions\Platform\SupportVisits;
use App\Enums\SalonRole;
use App\Models\SalonMember;
use App\Models\SupportVisit;
use App\Models\User;
use App\Support\CurrentSalon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the salon the signed-in user is working on: the one stored in the
 * session if they still belong to it, otherwise their first membership.
 */
class ResolveCurrentSalon
{
    public const string SESSION_KEY = 'current_salon_id';

    public function __construct(
        private readonly CurrentSalon $currentSalon,
        private readonly SupportVisits $support,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $user->loadMissing('memberships.salon');

        $visit = $this->support->current($user, $request->session());

        if ($visit !== null) {
            $this->actAsSupport($user, $visit);

            return $next($request);
        }

        $storedId = $request->session()->get(self::SESSION_KEY);
        $membership = is_int($storedId) ? $user->membershipIn($storedId) : null;

        // A soft-deleted salon loads as null and must never become current.
        if ($membership?->salon === null) {
            $membership = $user->memberships->first(fn ($member) => $member->salon !== null);
        }

        if ($membership === null) {
            $request->session()->forget(self::SESSION_KEY);

            return $next($request);
        }

        $this->currentSalon->set($membership->salon);
        $request->session()->put(self::SESSION_KEY, $membership->salon_id);

        return $next($request);
    }

    /**
     * The platform admin sees the salon as its owner would: an owner
     * membership that only lives in this request (never saved). Changes
     * are refused by EnsureSalonMember while the visit lasts.
     */
    private function actAsSupport(User $user, SupportVisit $visit): void
    {
        $member = new SalonMember;
        $member->salon_id = $visit->salon_id;
        $member->user_id = $user->id;
        $member->role = SalonRole::Owner;
        $member->setRelation('salon', $visit->salon);

        $user->setRelation('memberships', $user->memberships
            ->reject(fn (SalonMember $existing) => $existing->salon_id === $visit->salon_id)
            ->push($member));

        $this->currentSalon->set($visit->salon);
        $this->currentSalon->setSupportVisit($visit);
    }
}
