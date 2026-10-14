<?php

namespace App\Actions\Salon;

use App\Data\Salon\ConsentProof;
use App\Enums\ConsentAction;
use App\Enums\ConsentKind;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\ClientConsent;
use App\Models\ClientPhoto;
use App\Models\Review;
use App\Models\SalonClient;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\Images\ImageStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * What a person decides about the data a salon keeps of them: giving an
 * authorization from their own account, or taking one back, which erases
 * what it allowed. The record of both stays.
 */
class ClientDataRights
{
    private readonly ImageStore $images;

    public function __construct()
    {
        $this->images = new ImageStore(ManageClientPhotos::DISK);
    }

    /**
     * @return bool false when the person had already given it from their account: nothing new to record
     */
    public function authorize(SalonClient $client, ConsentKind $kind, ConsentProof $proof): bool
    {
        return DB::transaction(function () use ($client, $kind, $proof): bool {
            $locked = SalonClient::withoutGlobalScope('salon')->lockForUpdate()->findOrFail($client->id);
            $latest = ClientConsent::latest($locked, $kind);

            if ($latest !== null && $latest->action === ConsentAction::Granted && $latest->method->isByThePerson()) {
                return false;
            }

            // Already on a signed sheet: their own word is added to it, the date stays.
            if ($kind === ConsentKind::Care && $locked->care_consent_at === null) {
                $locked->care_consent_at = CarbonImmutable::now();
                $locked->care_consent_by = null;
                $locked->save();
            }

            ClientConsent::record($locked, $kind, ConsentAction::Granted, $proof);

            return true;
        });
    }

    /**
     * Takes the authorization back and erases what it allowed. Does
     * nothing when there was nothing to take back.
     */
    public function withdraw(SalonClient $client, ConsentKind $kind, ConsentProof $proof): void
    {
        $files = DB::transaction(function () use ($client, $kind, $proof): array {
            $locked = SalonClient::withoutGlobalScope('salon')->lockForUpdate()->findOrFail($client->id);

            return $kind === ConsentKind::Care
                ? $this->eraseCare($locked, $proof)
                : $this->erasePhotos($locked, $proof);
        });

        foreach ($files as $path) {
            $this->images->delete($path);
        }
    }

    /**
     * The account is going away: nothing it authorized stays behind, in
     * any salon.
     */
    public function forgetEverywhere(User $user): void
    {
        $files = SalonClient::withoutGlobalScope('salon')->where('user_id', $user->id)->get();

        // What they wrote stays as an opinion of the salon, without their name.
        Review::withoutGlobalScope('salon')->whereIn('salon_client_id', $files->modelKeys())->update(['author_name' => Review::ANONYMOUS]);

        // The names of who came along with them: nobody is left to ask about them.
        BookingService::query()->whereIn('booking_id', Booking::withoutGlobalScope('salon')->select('id')->whereIn('salon_client_id', $files->modelKeys()))
            ->whereNotNull('companion_name')->update(['companion_name' => null]);

        // Their places on waiting lists go too: nobody is left to tell.
        WaitlistEntry::withoutGlobalScope('salon')->whereIn('salon_client_id', $files->modelKeys())->delete();

        foreach ($files as $client) {
            foreach (ConsentKind::cases() as $kind) {
                $this->withdraw($client, $kind, ConsentProof::bySystem($user));
            }
        }
    }

    /**
     * @return list<string> files to delete once the transaction is done
     */
    private function eraseCare(SalonClient $client, ConsentProof $proof): array
    {
        if ($client->care_consent_at === null && $client->care_notes === null) {
            return [];
        }

        $hadText = $client->care_notes !== null;
        $client->care_notes = null;
        $client->care_consent_at = null;
        $client->care_consent_by = null;
        $client->save();

        ClientConsent::record($client, ConsentKind::Care, ConsentAction::Withdrawn, $proof->with(['had_text' => $hadText]));

        return [];
    }

    /**
     * @return list<string> files to delete once the transaction is done
     */
    private function erasePhotos(SalonClient $client, ConsentProof $proof): array
    {
        $photos = ClientPhoto::withoutGlobalScope('salon')->where('salon_client_id', $client->id)->get();
        $latest = ClientConsent::latest($client, ConsentKind::Photos);

        // Nothing kept and nothing in force: there is no authorization to take back.
        if ($photos->isEmpty() && ($latest === null || $latest->action === ConsentAction::Withdrawn)) {
            return [];
        }

        ClientPhoto::withoutGlobalScope('salon')->whereKey($photos->modelKeys())->delete();
        ClientConsent::record($client, ConsentKind::Photos, ConsentAction::Withdrawn, $proof->with(['photos' => $photos->count()]));

        return array_values($photos->pluck('path')->all());
    }
}
