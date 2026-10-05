<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Salon\ManageSalonPhotos;
use App\Http\Requests\Owner\SalonImageRequest;
use App\Models\SalonPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SalonPhotoController extends SalonAreaController
{
    public function __construct(private readonly ManageSalonPhotos $photos) {}

    public function store(SalonImageRequest $request): RedirectResponse
    {
        $this->photos->add($this->salon('update'), $request->file('image'), $request->validated('caption'));
        $this->toast('Foto agregada a tu galería.');

        return to_route('salon.edit');
    }

    /**
     * `{photo}` binding is filtered by the current salon (BelongsToSalon):
     * another salon's photo resolves to 404.
     */
    public function destroy(SalonPhoto $photo): RedirectResponse
    {
        $this->salon('update');
        $this->photos->remove($photo);
        $this->toast('Foto eliminada.');

        return to_route('salon.edit');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate(['ids' => ['required', 'array', 'max:'.SalonPhoto::MAX_PER_SALON], 'ids.*' => ['integer']]);

        $this->photos->reorder($this->salon('update'), $validated['ids']);

        return to_route('salon.edit');
    }
}
