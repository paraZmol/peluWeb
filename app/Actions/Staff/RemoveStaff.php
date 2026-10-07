<?php

namespace App\Actions\Staff;

use App\Models\Staff;
use Illuminate\Support\Facades\DB;

class RemoveStaff
{
    /**
     * Soft delete, so past appointments keep the name of who attended.
     * The account is detached so it can be linked to a new profile.
     */
    public function handle(Staff $staff): void
    {
        DB::transaction(function () use ($staff): void {
            $staff->user_id = null;
            $staff->save();

            $staff->schedules()->delete();
            $staff->delete();
        });
    }
}
