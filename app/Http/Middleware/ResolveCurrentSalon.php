<?php

namespace App\Http\Middleware;

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

    public function __construct(private readonly CurrentSalon $currentSalon) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $user->loadMissing('memberships.salon');

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
}
