<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Salon\ManageClientPhotos;
use App\Enums\ClientPhotoKind;
use App\Http\Controllers\Owner\SalonAreaController;
use App\Http\Requests\Staff\ClientPhotoRequest;
use App\Models\ClientPhoto;
use App\Models\SalonClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Before/after pictures in a client's file. `{client}` and `{photo}` are
 * filtered by the current salon (BelongsToSalon); the photo must also be
 * this client's.
 */
class ClientPhotoController extends SalonAreaController
{
    public function store(ClientPhotoRequest $request, SalonClient $client, ManageClientPhotos $photos): RedirectResponse
    {
        $this->authorize('managePhotos', $client);

        $photos->add($client, ClientPhotoKind::from($request->string('kind')->toString()), $request->file('photo'), $request->user());
        $this->toast('Foto guardada.');

        return back();
    }

    /**
     * The picture itself, never cached outside this browser.
     */
    public function show(SalonClient $client, ClientPhoto $photo): StreamedResponse
    {
        $this->authorize('managePhotos', $client);
        $disk = Storage::disk(ManageClientPhotos::DISK);
        abort_if($photo->salon_client_id !== $client->id || ! $disk->exists($photo->path), 404);

        return $disk->response($photo->path, null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroy(SalonClient $client, ClientPhoto $photo, ManageClientPhotos $photos): RedirectResponse
    {
        $this->authorize('managePhotos', $client);
        abort_if($photo->salon_client_id !== $client->id, 404);

        $photos->remove($photo);
        $this->toast('Foto eliminada.');

        return back();
    }
}
