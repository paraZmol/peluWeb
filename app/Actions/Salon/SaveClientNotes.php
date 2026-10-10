<?php

namespace App\Actions\Salon;

use App\Data\Salon\ClientNotes;
use App\Enums\ConsentAction;
use App\Enums\ConsentKind;
use App\Models\ClientConsent;
use App\Models\SalonClient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Writes the notes of a client's file. The care notes (health data) only
 * stay while the person's signed authorization is on record: without it
 * they are erased, whatever was sent. Giving and withdrawing it is logged.
 */
class SaveClientNotes
{
    /**
     * @param  bool  $withCare  false for who may not read the care notes: theirs stay as they are
     */
    public function handle(SalonClient $client, ClientNotes $notes, ?User $by, bool $withCare): SalonClient
    {
        return DB::transaction(function () use ($client, $notes, $by, $withCare): SalonClient {
            // The row as it is now: two people saving at once must not leave
            // care notes behind an authorization that was just withdrawn.
            $client = SalonClient::withoutGlobalScope('salon')->lockForUpdate()->findOrFail($client->id);
            $client->notes = $notes->notes;
            $client->formula_notes = $notes->formulaNotes;

            if ($withCare) {
                $this->writeCare($client, $notes, $by);
            }

            $client->save();

            return $client;
        });
    }

    private function writeCare(SalonClient $client, ClientNotes $notes, ?User $by): void
    {
        $hadConsent = $client->care_consent_at !== null;

        if (! $notes->hasCareConsent) {
            $client->care_notes = null;
            $client->care_consent_at = null;
            $client->care_consent_by = null;

            if ($hadConsent) {
                ClientConsent::record($client, ConsentKind::Care, ConsentAction::Withdrawn, $by);
            }

            return;
        }

        $client->care_notes = $notes->careNotes;

        if (! $hadConsent) {
            $client->care_consent_at = CarbonImmutable::now();
            $client->care_consent_by = $by?->id;
            ClientConsent::record($client, ConsentKind::Care, ConsentAction::Granted, $by);
        }
    }
}
