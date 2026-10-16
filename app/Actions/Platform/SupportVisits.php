<?php

namespace App\Actions\Platform;

use App\Models\Salon;
use App\Models\SupportVisit;
use App\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * "Entrar como salón" for support (PLAN 7.4): the platform admin sees a
 * salon's screens as its owner would, without changing anything. Each
 * visit is recorded with its reason and ends when left or after two hours.
 */
class SupportVisits
{
    public const string SESSION_KEY = 'support_visit_id';

    public function start(User $admin, Salon $salon, string $reason, ?string $ip, Session $session): SupportVisit
    {
        $this->end($session);

        $visit = new SupportVisit;
        $visit->admin_id = $admin->id;
        $visit->salon_id = $salon->id;
        $visit->reason = $reason;
        $visit->ip = $ip;
        $visit->started_at = now();
        $visit->save();

        $session->put(self::SESSION_KEY, $visit->id);

        return $visit;
    }

    /**
     * The visit this session is in, if it is still open for this person:
     * only a platform admin with two-factor, never past its time.
     */
    public function current(?User $user, Session $session): ?SupportVisit
    {
        $visitId = $session->get(self::SESSION_KEY);

        if (! is_int($visitId) || $user === null) {
            return null;
        }

        $visit = SupportVisit::query()->with('salon')->find($visitId);
        $isAllowed = $user->is_platform_admin && $user->hasTwoFactorEnabled();

        if ($visit === null || $visit->salon === null || ! $isAllowed || ! $visit->isOpenFor($user)) {
            $this->end($session);

            return null;
        }

        return $visit;
    }

    public function end(Session $session): ?SupportVisit
    {
        $visitId = $session->pull(self::SESSION_KEY);
        $visit = is_int($visitId) ? SupportVisit::query()->find($visitId) : null;

        if ($visit !== null && $visit->ended_at === null) {
            // One that ran out ended at its limit, not when it was noticed.
            $visit->ended_at = now()->min($visit->started_at->addMinutes(SupportVisit::MAX_MINUTES));
            $visit->save();
        }

        return $visit;
    }
}
