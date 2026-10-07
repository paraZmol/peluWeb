<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Salons created before professionals existed: every team member gets a
 * professional profile that performs all of the salon's services.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $members = DB::table('salon_user')
            ->join('users', 'users.id', '=', 'salon_user.user_id')
            ->orderBy('salon_user.id')
            ->get(['salon_user.salon_id', 'salon_user.user_id', 'users.name']);

        foreach ($members as $position => $member) {
            $staffId = DB::table('staff')->insertGetId([
                'salon_id' => $member->salon_id,
                'user_id' => $member->user_id,
                'name' => mb_substr($member->name, 0, 80),
                'position' => $position,
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
    }

    public function down(): void
    {
        // Nothing to undo: the tables are dropped by the previous migration.
    }
};
