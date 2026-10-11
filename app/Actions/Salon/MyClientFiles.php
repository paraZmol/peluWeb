<?php

namespace App\Actions\Salon;

use App\Enums\ConsentAction;
use App\Enums\ConsentKind;
use App\Models\ClientConsent;
use App\Models\ClientPhoto;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * What each salon keeps of a person that needed their authorization: the
 * care notes and the pictures, and where each authorization stands. A file
 * is theirs only if their own account made a booking there: files the
 * salon created by name and phone are never matched to an account.
 */
class MyClientFiles
{
    /**
     * @return Builder<SalonClient>
     */
    public function query(User $user): Builder
    {
        return SalonClient::withoutGlobalScope('salon')->where('user_id', $user->id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(User $user): array
    {
        $files = [];

        foreach ($this->query($user)->orderBy('id')->get() as $client) {
            // Also a salon that closed its account: what it kept is still there to take back.
            $salon = Salon::withTrashed()->find($client->salon_id);

            if ($salon !== null) {
                $files[] = $this->file($client, $salon);
            }
        }

        return $files;
    }

    /**
     * @return array<string, mixed>
     */
    private function file(SalonClient $client, Salon $salon): array
    {
        $day = fn (ClientConsent $consent): string => $consent->created_at->setTimezone($salon->timezone)->format('Y-m-d');
        $care = ClientConsent::latest($client, ConsentKind::Care);
        $photosConsent = ClientConsent::latest($client, ConsentKind::Photos);
        $photos = ClientPhoto::withoutGlobalScope('salon')->where('salon_client_id', $client->id)
            ->orderByDesc('taken_on')->orderByDesc('id')->get();

        return [
            'clientId' => $client->id,
            'salon' => ['name' => $salon->name, 'slug' => $salon->slug, 'isClosed' => $salon->trashed()],
            'care' => [
                'isAuthorized' => $client->care_consent_at !== null,
                'since' => $client->care_consent_at?->setTimezone($salon->timezone)->format('Y-m-d'),
                // "account": they did it here; anything else, a sheet signed at the salon.
                'method' => $client->care_consent_at === null ? null : $care?->method->value,
                'notes' => $client->care_notes,
                'withdrawnOn' => $care !== null && $care->action === ConsentAction::Withdrawn ? $day($care) : null,
                'text' => ConsentKind::Care->text($salon->name),
            ],
            'photos' => [
                'items' => $photos->map(fn (ClientPhoto $photo): array => [
                    'id' => $photo->id,
                    'kindLabel' => $photo->kind->label(),
                    'date' => $photo->taken_on->format('Y-m-d'),
                    'url' => route('my-data.photos.show', [$client->id, $photo->id]),
                ])->values()->all(),
                // In force even with no picture yet: there is something to take back.
                'isAuthorized' => $photosConsent?->action === ConsentAction::Granted,
                'since' => $photosConsent !== null && $photosConsent->action === ConsentAction::Granted ? $day($photosConsent) : null,
                // They took them back: the salon cannot add more until they say so here.
                'isBlocked' => $photosConsent?->isWithdrawnByThePerson() ?? false,
                'withdrawnOn' => $photosConsent !== null && $photosConsent->action === ConsentAction::Withdrawn ? $day($photosConsent) : null,
                'text' => ConsentKind::Photos->text($salon->name),
            ],
        ];
    }
}
