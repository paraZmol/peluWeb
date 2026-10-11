<?php

namespace App\Actions\Salon;

use App\Data\Salon\ConsentProof;
use App\Enums\ClientPhotoKind;
use App\Enums\ConsentAction;
use App\Enums\ConsentKind;
use App\Enums\ConsentMethod;
use App\Exceptions\SalonException;
use App\Models\ClientConsent;
use App\Models\ClientPhoto;
use App\Models\SalonClient;
use App\Models\User;
use App\Support\Images\ImageStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Adds and removes the before/after pictures of a client's file. Pictures
 * of people stay on the private disk and each upload leaves on record that
 * the person authorized it.
 */
class ManageClientPhotos
{
    /** Pictures of people: never on the public disk. */
    public const string DISK = 'local';

    private readonly ImageStore $images;

    public function __construct()
    {
        $this->images = new ImageStore(self::DISK);
    }

    public function add(SalonClient $client, ClientPhotoKind $kind, UploadedFile $file, ?User $by): ClientPhoto
    {
        $path = $this->images->storeFitted($file, "salons/{$client->salon_id}/clients/{$client->id}", ClientPhoto::MAX_SIDE, ClientPhoto::MAX_SIDE);

        try {
            return DB::transaction(function () use ($client, $kind, $path, $by): ClientPhoto {
                // One at a time per client, so the limit holds.
                SalonClient::withoutGlobalScope('salon')->lockForUpdate()->findOrFail($client->id);

                // They took their pictures back themselves: the team's word is no longer enough.
                if (ClientConsent::latest($client, ConsentKind::Photos)?->isWithdrawnByThePerson() === true) {
                    throw SalonException::photosWithdrawn();
                }

                if (ClientPhoto::withoutGlobalScope('salon')->where('salon_client_id', $client->id)->count() >= ClientPhoto::MAX_PER_CLIENT) {
                    throw SalonException::tooManyClientPhotos(ClientPhoto::MAX_PER_CLIENT);
                }

                $photo = new ClientPhoto;
                $photo->salon_id = $client->salon_id;
                $photo->salon_client_id = $client->id;
                $photo->kind = $kind;
                $photo->path = $path;
                $photo->taken_on = $client->salon()->firstOrFail()->localNow()->startOfDay();
                $photo->uploaded_by = $by?->id;
                $photo->save();

                ClientConsent::record($client, ConsentKind::Photos, ConsentAction::Granted, ConsentProof::byStaff(ConsentMethod::StaffDeclared, $by));

                return $photo;
            });
        } catch (Throwable $exception) {
            $this->images->delete($path);

            throw $exception;
        }
    }

    public function remove(ClientPhoto $photo): void
    {
        $photo->delete();
        $this->images->delete($photo->path);
    }
}
