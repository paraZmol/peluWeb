<?php

namespace App\Actions\Salon;

use App\Exceptions\SalonException;
use App\Models\Salon;
use App\Models\SalonPhoto;
use App\Support\Images\ImageStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ManageSalonPhotos
{
    public const int MAX_WIDTH = 1600;

    public const int MAX_HEIGHT = 1600;

    public function __construct(private readonly ImageStore $images) {}

    public function add(Salon $salon, UploadedFile $file, ?string $caption): SalonPhoto
    {
        return DB::transaction(function () use ($salon, $file, $caption): SalonPhoto {
            // Lock the salon so two uploads cannot both pass the limit.
            Salon::query()->lockForUpdate()->findOrFail($salon->id);
            $count = $salon->photos()->count();

            if ($count >= SalonPhoto::MAX_PER_SALON) {
                throw SalonException::galleryFull(SalonPhoto::MAX_PER_SALON);
            }

            $photo = new SalonPhoto;
            $photo->salon_id = $salon->id;
            $photo->path = $this->images->storeFitted($file, "salons/{$salon->id}/gallery", self::MAX_WIDTH, self::MAX_HEIGHT);
            $photo->caption = $caption;
            $photo->position = $count;
            $photo->save();

            return $photo;
        });
    }

    public function remove(SalonPhoto $photo): void
    {
        $photo->delete();
        $this->images->delete($photo->path);
    }

    /**
     * @param  list<int>  $orderedIds  photo ids in the new order
     */
    public function reorder(Salon $salon, array $orderedIds): void
    {
        DB::transaction(function () use ($salon, $orderedIds): void {
            $photos = $salon->photos()->get()->keyBy('id');

            foreach ($orderedIds as $position => $id) {
                // Ids from another salon are simply ignored.
                $photos->get($id)?->forceFill(['position' => $position])->save();
            }
        });
    }
}
