<?php

namespace App\Actions\Staff;

use App\Models\Staff;
use App\Support\Images\ImageStore;
use Illuminate\Support\Facades\DB;

class RemoveStaff
{
    public function __construct(private readonly ImageStore $images) {}

    /**
     * Soft delete, so past appointments keep the name of who attended.
     * Everything else goes: the account link (so it can be linked to a new
     * profile), the hours, the services and the photo of someone who no
     * longer works here.
     */
    public function handle(Staff $staff): void
    {
        $photo = $staff->photo_path;

        DB::transaction(function () use ($staff): void {
            // Same lock as booking: nobody books someone being removed.
            Staff::query()->lockForUpdate()->findOrFail($staff->id);

            $staff->user_id = null;
            $staff->photo_path = null;
            $staff->save();

            $staff->schedules()->delete();
            $staff->services()->detach();
            $staff->delete();
        });

        $this->images->delete($photo);
    }
}
