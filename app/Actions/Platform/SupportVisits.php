<?php

namespace App\Actions\Platform;

use App\Enums\SalonRole;
use App\Models\Salon;
use App\Models\SalonMember;
use App\Models\SupportVisit;
use App\Models\User;
use App\Notifications\SupportVisitStartedNotification;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

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

        // The owners always learn who came in and why (L27).
        Notification::send($this->owners($salon), new SupportVisitStartedNotification($visit->setRelation('salon', $salon)));

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

    /**
     * The salon's last visits, for "Mi plan": the owner sees each look.
     *
     * @return list<array{id: int, startedAt: string, minutes: int|null, reason: string}>
     */
    public function forOwner(Salon $salon, int $limit = 10): array
    {
        return SupportVisit::query()->where('salon_id', $salon->id)->latest('started_at')->limit($limit)->get()
            ->map(fn (SupportVisit $visit): array => [
                'id' => $visit->id,
                'startedAt' => $visit->started_at->setTimezone($salon->timezone)->format('Y-m-d H:i'),
                'minutes' => $visit->ended_at === null ? null : (int) $visit->started_at->diffInMinutes($visit->ended_at),
                'reason' => $visit->reason,
            ])
            ->values()
            ->all();
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

    /**
     * @return Collection<int, User>
     */
    private function owners(Salon $salon): Collection
    {
        return SalonMember::query()->with('user')->where('salon_id', $salon->id)->where('role', SalonRole::Owner)->get()
            ->map(fn (SalonMember $member): ?User => $member->user)
            ->filter()
            ->values();
    }
}
