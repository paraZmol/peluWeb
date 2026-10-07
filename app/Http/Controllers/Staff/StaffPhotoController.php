<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Staff\UpdateStaffPhoto;
use App\Http\Controllers\Owner\SalonAreaController;
use App\Http\Requests\Owner\SalonImageRequest;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;

class StaffPhotoController extends SalonAreaController
{
    public function store(SalonImageRequest $request, Staff $staff, UpdateStaffPhoto $photo): RedirectResponse
    {
        $this->authorize('update', $staff);
        $photo->handle($staff, $request->file('image'));
        $this->toast('Foto actualizada.');

        return back();
    }

    public function destroy(Staff $staff, UpdateStaffPhoto $photo): RedirectResponse
    {
        $this->authorize('update', $staff);
        $photo->remove($staff);
        $this->toast('Foto eliminada.');

        return back();
    }
}
