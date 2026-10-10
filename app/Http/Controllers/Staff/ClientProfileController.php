<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Salon\ClientProfile;
use App\Actions\Salon\SaveClientNotes;
use App\Http\Controllers\Owner\SalonAreaController;
use App\Http\Requests\Staff\ClientNotesRequest;
use App\Models\ClientPhoto;
use App\Models\SalonClient;
use App\Support\WhatsApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A client's file for the team. `{client}` is filtered by the current
 * salon (BelongsToSalon); who may open it is SalonClientPolicy's call.
 */
class ClientProfileController extends SalonAreaController
{
    public function show(Request $request, SalonClient $client, ClientProfile $profile): Response
    {
        $this->authorize('view', $client);
        $salon = $this->currentSalon();
        $user = $request->user();
        $showsMoney = ($user?->can('manageCash', $salon) ?? false) || ($user?->can('viewReports', $salon) ?? false);
        $seesCare = $user?->can('viewCare', $client) ?? false;
        $client->load('careConsentBy:id,name');
        // Health data must not come back with "Back" once the session is over.
        Inertia::encryptHistory();

        return Inertia::render('staff/client', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'phone' => $client->phone,
                'whatsappUrl' => WhatsApp::link($client->phone, "Hola {$client->name}, te escribimos de {$salon->name}."),
                'isVip' => $client->is_vip,
                'hasAccount' => $client->user_id !== null,
                'since' => $client->created_at?->setTimezone($salon->timezone)->format('Y-m-d'),
                'notes' => $client->notes,
                'formulaNotes' => $client->formula_notes,
                // Health data: null for whoever does not attend this person.
                'careNotes' => $seesCare ? $client->care_notes : null,
                'careConsent' => ! $seesCare || $client->care_consent_at === null ? null : [
                    'date' => $client->care_consent_at->setTimezone($salon->timezone)->format('Y-m-d'),
                    'by' => $client->careConsentBy?->name,
                ],
            ],
            ...$profile->handle($salon, $client, $showsMoney),
            'canSeeCare' => $seesCare,
            // Pictures of the person follow the same rule as their health notes.
            'photos' => $seesCare ? $this->photos($client) : [],
            'photosMax' => ClientPhoto::MAX_PER_CLIENT,
            'careConsentText' => $this->careConsentText($salon->name),
            'canManageVip' => $user?->can('manageVipClients', $salon) ?? false,
            'notesMaxLength' => SalonClient::NOTES_MAX_LENGTH,
        ]);
    }

    public function updateNotes(ClientNotesRequest $request, SalonClient $client, SaveClientNotes $save): RedirectResponse
    {
        $this->authorize('updateNotes', $client);

        // The health part is only touched by who may see it and sent it on
        // purpose: a partial or stale form must not read as a withdrawal.
        $withCare = $request->has('care_consent') && ($request->user()?->can('viewCare', $client) ?? false);

        $save->handle($client, $request->notes(), $request->user(), $withCare);
        $this->toast('Ficha actualizada.');

        return back();
    }

    /**
     * @return list<array{id: int, kind: string, kindLabel: string, date: string, url: string}>
     */
    private function photos(SalonClient $client): array
    {
        return array_values($client->photos()->orderByDesc('taken_on')->orderByDesc('id')->get()
            ->map(fn (ClientPhoto $photo): array => [
                'id' => $photo->id,
                'kind' => $photo->kind->value,
                'kindLabel' => $photo->kind->label(),
                'date' => $photo->taken_on->format('Y-m-d'),
                'url' => route('clients.photos.show', [$client, $photo]),
            ])->all());
    }

    /**
     * What the person signs before the salon may keep their care notes
     * (draft v0.2 in docs/legal/consentimientos.md, row 17).
     */
    private function careConsentText(string $salonName): string
    {
        return "Autorizo a {$salonName} a guardar mis alergias, sensibilidades y reacciones a productos para atenderme con seguridad. "
            .'Solo lo verá el equipo que me atiende. No se usará para publicidad ni se compartirá. '
            .'Puedo ver lo anotado y retirar esta autorización cuando quiera, pidiéndolo en el salón; al retirarla, se borra.';
    }
}
