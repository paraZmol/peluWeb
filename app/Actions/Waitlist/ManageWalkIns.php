<?php

namespace App\Actions\Waitlist;

use App\Enums\WalkInStatus;
use App\Exceptions\WaitlistException;
use App\Models\Salon;
use App\Models\User;
use App\Models\WalkIn;
use App\Support\PersonName;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The day's line of people without a booking: who is in it, adding
 * someone who just came in, and moving each one along. Every query is
 * scoped to the current salon (BelongsToSalon).
 */
class ManageWalkIns
{
    /**
     * Today's line, in the order they came in.
     *
     * @return Collection<int, WalkIn>
     */
    public function today(Salon $salon): Collection
    {
        return WalkIn::query()
            ->whereDate('day', $salon->localNow()->toDateString())
            ->with(['service', 'staff'])
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array{name: string, service_id?: int|null, staff_id?: int|null}  $data  already validated against this salon
     */
    public function add(Salon $salon, array $data, ?User $by): WalkIn
    {
        $day = $salon->localNow()->toDateString();
        $open = WalkIn::query()->whereDate('day', $day)->whereIn('status', [WalkInStatus::Waiting, WalkInStatus::Called])->count();

        if ($open >= WalkIn::MAX_OPEN) {
            throw WaitlistException::lineIsFull(WalkIn::MAX_OPEN);
        }

        $walkIn = new WalkIn;
        $walkIn->salon_id = $salon->id;
        $walkIn->day = CarbonImmutable::parse($day);
        // Typed in a hurry at the counter: one space between words is enough.
        $walkIn->name = PersonName::clean((string) preg_replace('/\s+/u', ' ', $data['name']), WalkIn::NAME_MAX_LENGTH, 'Cliente');
        $walkIn->service_id = $data['service_id'] ?? null;
        $walkIn->staff_id = $data['staff_id'] ?? null;
        $walkIn->created_by = $by?->id;
        $walkIn->save();

        return $walkIn;
    }

    public function move(WalkIn $walkIn, WalkInStatus $next): WalkIn
    {
        if (! $walkIn->status->canBecome($next)) {
            throw WaitlistException::placeIsClosed();
        }

        $now = CarbonImmutable::now();
        $walkIn->status = $next;
        $walkIn->called_at = $next === WalkInStatus::Called ? $now : ($next === WalkInStatus::Waiting ? null : $walkIn->called_at);
        $walkIn->closed_at = $next->isOpen() ? null : $now;
        $walkIn->save();

        return $walkIn;
    }
}
