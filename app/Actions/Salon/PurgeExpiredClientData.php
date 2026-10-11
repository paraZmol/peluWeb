<?php

namespace App\Actions\Salon;

use App\Data\Salon\ConsentProof;
use App\Enums\ConsentKind;
use App\Models\Booking;
use App\Models\ClientPhoto;
use App\Models\Salon;
use App\Models\SalonClient;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * What a salon may keep under an authorization does not stay forever: the
 * care notes go 24 months after the person's last visit, the pictures
 * after 12, and everything once the salon closed its account. Each
 * erasure is logged as done by the system; the record of it stays.
 */
class PurgeExpiredClientData
{
    public const int CARE_MONTHS = 24;

    public const int PHOTOS_MONTHS = 12;

    /** Time a closed salon has to take its data out before it is erased. */
    public const int CLOSED_SALON_DAYS = 30;

    public function __construct(private readonly ClientDataRights $rights) {}

    /**
     * @return array{care: int, photos: int} files that lost each kind
     */
    public function handle(CarbonImmutable $now): array
    {
        return [
            'care' => $this->erase($this->staleCare($now->subMonths(self::CARE_MONTHS)), ConsentKind::Care)
                + $this->erase($this->ofClosedSalons($now)->where($this->keepsCare(...)), ConsentKind::Care),
            'photos' => $this->erase($this->stalePhotos($now->subMonths(self::PHOTOS_MONTHS)), ConsentKind::Photos)
                + $this->erase($this->ofClosedSalons($now)->whereIn('id', $this->photoOwners()), ConsentKind::Photos),
        ];
    }

    /**
     * @param  Builder<SalonClient>  $clients
     */
    private function erase(Builder $clients, ConsentKind $kind): int
    {
        $erased = 0;

        // Ids first: erasing changes what the query matches.
        foreach ($clients->pluck('id') as $id) {
            $client = SalonClient::withoutGlobalScope('salon')->whereKey($id)->first();

            if ($client !== null) {
                $this->rights->withdraw($client, $kind, ConsentProof::bySystem());
                $erased++;
            }
        }

        return $erased;
    }

    /**
     * Authorized before the cutoff and not seen since.
     *
     * @return Builder<SalonClient>
     */
    private function staleCare(CarbonImmutable $cutoff): Builder
    {
        return $this->notSeenSince($cutoff)
            ->where($this->keepsCare(...))
            ->where(fn (Builder $query) => $query->whereNull('care_consent_at')->orWhere('care_consent_at', '<=', $cutoff));
    }

    /**
     * Every picture older than the cutoff and no visit since.
     *
     * @return Builder<SalonClient>
     */
    private function stalePhotos(CarbonImmutable $cutoff): Builder
    {
        return $this->notSeenSince($cutoff)
            ->whereIn('id', $this->photoOwners())
            ->whereNotIn('id', $this->photoOwners()->where('taken_on', '>', $cutoff->toDateString()));
    }

    /**
     * @return Builder<SalonClient>
     */
    private function ofClosedSalons(CarbonImmutable $now): Builder
    {
        return SalonClient::withoutGlobalScope('salon')->whereIn(
            'salon_id',
            Salon::onlyTrashed()->where('deleted_at', '<=', $now->subDays(self::CLOSED_SALON_DAYS))->select('id'),
        );
    }

    /**
     * A booked visit after the cutoff, past or to come, keeps the file alive.
     *
     * @return Builder<SalonClient>
     */
    private function notSeenSince(CarbonImmutable $cutoff): Builder
    {
        return SalonClient::withoutGlobalScope('salon')->whereNotIn(
            'id',
            Booking::withoutGlobalScope('salon')->whereNotNull('salon_client_id')->where('starts_at', '>', $cutoff)->select('salon_client_id'),
        );
    }

    /**
     * @param  Builder<SalonClient>  $query
     */
    private function keepsCare(Builder $query): void
    {
        $query->whereNotNull('care_consent_at')->orWhereNotNull('care_notes');
    }

    /**
     * @return Builder<ClientPhoto>
     */
    private function photoOwners(): Builder
    {
        return ClientPhoto::withoutGlobalScope('salon')->select('salon_client_id');
    }
}
