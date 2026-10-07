<?php

namespace App\Actions\Staff;

use App\Models\Staff;
use App\Support\Images\ImageStore;
use Illuminate\Http\UploadedFile;

class UpdateStaffPhoto
{
    public function __construct(private readonly ImageStore $images) {}

    public function handle(Staff $staff, UploadedFile $file): void
    {
        $this->replace($staff, $this->images->storeSquare($file, "salons/{$staff->salon_id}/staff", Staff::PHOTO_SIZE));
    }

    public function remove(Staff $staff): void
    {
        $this->replace($staff, null);
    }

    private function replace(Staff $staff, ?string $path): void
    {
        $previous = $staff->photo_path;

        $staff->photo_path = $path;
        $staff->save();

        $this->images->delete($previous);
    }
}
