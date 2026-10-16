<?php

namespace App\Actions\Staff;

use App\Actions\Booking\FindAffectedBookings;
use App\Exceptions\StaffException;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

/**
 * Queries name the salon explicitly instead of relying on the current one:
 * this also runs while creating a salon and accepting an invitation, when
 * the salon being changed is not the current salon.
 */
class SaveStaff
{
    public function __construct(private readonly FindAffectedBookings $affected) {}

    /**
     * Create (when $staff is null) or update a professional. A new one
     * starts performing every service of the salon.
     *
     * @param  array{name: string, title?: string|null, is_active?: bool}  $attributes  only the fields to change
     * @param  int|null  $userId  account to link, or null for none
     */
    public function handle(Salon $salon, ?Staff $staff, array $attributes, ?int $userId = null): Staff
    {
        return DB::transaction(function () use ($salon, $staff, $attributes, $userId): Staff {
            // The professional first, then the salon: the order a booking
            // takes them in (its insert touches the salon row), so the two
            // never wait on each other.
            if ($staff !== null) {
                Staff::withoutGlobalScope('salon')->lockForUpdate()->find($staff->id);
            }

            // Serializes concurrent saves so the limits and the link stay true.
            $locked = Salon::query()->with('plan')->lockForUpdate()->findOrFail($salon->id);

            $isNew = $staff === null;
            $staff ??= $this->newStaff($salon);

            $this->ensureLinkable($salon, $staff, $userId);

            $staff->fill($attributes);
            $staff->user_id = $userId;
            $this->ensurePlanAllows($locked, $staff, $isNew);

            // Without an agenda their appointments would drop out of the board.
            if (! $isNew && ! $staff->is_active && $staff->getOriginal('is_active')) {
                $this->affected->ensureNone($staff);
            }

            $staff->save();

            if ($isNew) {
                $staff->services()->sync($this->serviceIds($salon));
            }

            return $staff;
        }, attempts: 3);
    }

    private function newStaff(Salon $salon): Staff
    {
        $existing = Staff::withoutGlobalScope('salon')->where('salon_id', $salon->id);

        if ($existing->count() >= Staff::MAX_PER_SALON) {
            throw StaffException::limitReached(Staff::MAX_PER_SALON);
        }

        $staff = new Staff;
        $staff->salon_id = $salon->id;
        $staff->position = (int) $existing->max('position') + 1;

        return $staff;
    }

    /**
     * Only professionals with an agenda (active) count for the plan, so the
     * check runs when someone gets one: created active or switched back on.
     */
    private function ensurePlanAllows(Salon $salon, Staff $staff, bool $isNew): void
    {
        $getsAgenda = $staff->is_active && ($isNew || ! $staff->getOriginal('is_active'));

        if ($getsAgenda && ! $salon->hasRoomForStaff()) {
            throw StaffException::planLimitReached($salon->staffLimit(), $salon->currentPlan()->name);
        }
    }

    private function ensureLinkable(Salon $salon, Staff $staff, ?int $userId): void
    {
        if ($userId === null || $userId === $staff->user_id) {
            return;
        }

        if (! $salon->members()->where('user_id', $userId)->exists()) {
            throw StaffException::accountNotInTeam();
        }

        $taken = Staff::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->where('user_id', $userId)
            ->exists();

        if ($taken) {
            throw StaffException::accountAlreadyLinked();
        }
    }

    /**
     * @return array<int, int>
     */
    private function serviceIds(Salon $salon): array
    {
        return Service::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->get(['id'])
            ->map(fn (Service $service): int => $service->id)
            ->values()
            ->all();
    }
}
