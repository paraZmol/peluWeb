<?php

namespace App\Http\Controllers\Client;

use App\Actions\Salon\ClientDataRights;
use App\Actions\Salon\ManageClientPhotos;
use App\Actions\Salon\MyClientFiles;
use App\Data\Salon\ConsentProof;
use App\Enums\ConsentKind;
use App\Exceptions\SalonException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\AuthorizeConsentRequest;
use App\Models\ClientPhoto;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\User;
use App\Notifications\ConsentReceiptNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Mis datos": what each salon keeps of the signed-in person under their
 * authorization, and the switches to give it or take it back. They are not
 * members of those salons: ownership replaces the salon scope, so a file
 * that is not theirs is a 404.
 */
class MyDataController extends Controller
{
    public function __construct(private readonly MyClientFiles $files) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        // Health data and pictures: not back with "Back" after signing out.
        Inertia::encryptHistory();

        return Inertia::render('client/my-data', [
            'files' => $this->files->handle($user),
        ]);
    }

    public function grant(AuthorizeConsentRequest $request, int $client, ConsentKind $kind, ClientDataRights $rights): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $file = $this->find($user, $client);
        $salon = Salon::query()->findOrFail($file->salon_id);

        // The receipt goes to this address: it has to be theirs. The route
        // already asks for it; this holds if that ever changes.
        if (! $user->hasVerifiedEmail()) {
            throw SalonException::emailNotVerified();
        }

        $text = $kind->text($salon->name);
        if (! $rights->authorize($file, $kind, ConsentProof::fromAccount($request, $user, $kind->accountText($salon->name)))) {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Ya lo habías autorizado.']);

            return back();
        }

        $user->notify(new ConsentReceiptNotification(
            $salon->name,
            $kind === ConsentKind::Care ? 'guardar tus alergias y reacciones a productos' : 'guardar fotos del antes y el después de tus servicios',
            $text,
            ucfirst($salon->localNow()->isoFormat('D [de] MMMM [de] YYYY, h:mm a')),
        ));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Autorización registrada. Te enviamos una constancia a tu correo.']);

        return back();
    }

    public function withdraw(Request $request, int $client, ConsentKind $kind, ClientDataRights $rights): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $rights->withdraw($this->find($user, $client), $kind, ConsentProof::fromAccount($request, $user));
        Inertia::flash('toast', ['type' => 'success', 'message' => $kind === ConsentKind::Care
            ? 'Autorización retirada. Se borró lo anotado.'
            : 'Autorización retirada. Se borraron las fotos.']);

        return back();
    }

    /**
     * One of their own pictures, never cached outside this browser.
     */
    public function photo(Request $request, int $client, int $photo): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        $file = $this->find($user, $client);
        $picture = ClientPhoto::withoutGlobalScope('salon')->where('salon_client_id', $file->id)->findOrFail($photo);
        $disk = Storage::disk(ManageClientPhotos::DISK);
        abort_unless($disk->exists($picture->path), 404);

        return $disk->response($picture->path, null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function find(User $user, int $client): SalonClient
    {
        return $this->files->query($user)->findOrFail($client);
    }
}
