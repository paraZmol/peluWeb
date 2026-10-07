<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Salons created before professionals existed: every team member gets a
 * professional profile that performs all of the salon's services.
 *
 * Safe to run again after a partial failure: it is one transaction and
 * skips members who already have a profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $positions = [];

            $members = DB::table('salon_user')
                ->join('users', 'users.id', '=', 'salon_user.user_id')
                ->join('salons', 'salons.id', '=', 'salon_user.salon_id')
                ->whereNull('salons.deleted_at')
                ->whereNotExists(fn ($query) => $query->from('staff')
                    ->whereColumn('staff.salon_id', 'salon_user.salon_id')
                    ->whereColumn('staff.user_id', 'salon_user.user_id'))
                ->orderBy('salon_user.id')
                ->get(['salon_user.salon_id', 'salon_user.user_id', 'users.name']);

            foreach ($members as $member) {
                $positions[$member->salon_id] = ($positions[$member->salon_id] ?? 0) + 1;

                $staffId = DB::table('staff')->insertGetId([
                    'salon_id' => $member->salon_id,
                    'user_id' => $member->user_id,
                    'name' => mb_substr($member->name, 0, 80),
                    'position' => $positions[$member->salon_id],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $services = DB::table('services')
                    ->where('salon_id', $member->salon_id)
                    ->whereNull('deleted_at')
                    ->pluck('id')
                    ->map(fn (int $serviceId): array => [
                        'service_id' => $serviceId,
                        'staff_id' => $staffId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                DB::table('service_staff')->insert($services->all());
            }
        });
    }

    public function down(): void
    {
        // Nothing to undo: the tables are dropped by the previous migration.
    }
};
