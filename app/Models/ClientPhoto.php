<?php

namespace App\Models;

use App\Concerns\BelongsToSalon;
use App\Enums\ClientPhotoKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A before or after picture of a client's work, kept for the salon's own
 * record with the person's authorization. Written only by
 * App\Actions\Salon\ManageClientPhotos.
 *
 * @property int $id
 * @property int $salon_id
 * @property int $salon_client_id
 * @property ClientPhotoKind $kind
 * @property string $path On the private disk.
 * @property CarbonImmutable $taken_on
 * @property int|null $uploaded_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read SalonClient $client
 */
class ClientPhoto extends Model
{
    use BelongsToSalon;

    public const int MAX_PER_CLIENT = 40;

    public const int MAX_SIDE = 1600;

    /**
     * @var list<string>
     */
    protected $hidden = ['path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => ClientPhotoKind::class, 'taken_on' => 'date:Y-m-d'];
    }

    /**
     * @return BelongsTo<SalonClient, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(SalonClient::class, 'salon_client_id');
    }
}
