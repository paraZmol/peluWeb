<?php

namespace App\Actions\Staff;

use App\Exceptions\StaffException;
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

            // Serializes concurrent saves so the limit and the link stay true.
            Salon::query()->lockForUpdate()->findOrFail($salon->id);

            $isNew = $staff === null;
            $staff ??= $this->newStaff($salon);

            $this->ensureLinkable($salon, $staff, $userId);

            $staff->fill($attributes);
            $staff->user_id = $userId;
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
