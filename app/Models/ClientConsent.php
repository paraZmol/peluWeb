<?php

namespace App\Models;

use App\Concerns\BelongsToSalon;
use App\Enums\ConsentAction;
use App\Enums\ConsentKind;
use App\Enums\ConsentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One authorization a client gave or withdrew. Rows are only ever added:
 * they are the proof, and they outlive the notes they allowed.
 *
 * @property int $id
 * @property int $salon_id
 * @property int $salon_client_id
 * @property ConsentKind $kind
 * @property ConsentAction $action
 * @property ConsentMethod $method
 * @property string $text_version
 * @property int|null $recorded_by
 * @property CarbonImmutable $created_at
 */
class ClientConsent extends Model
{
    use BelongsToSalon;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ConsentKind::class,
            'action' => ConsentAction::class,
            'method' => ConsentMethod::class,
        ];
    }

    protected static function booted(): void
    {
        $refuse = fn () => throw new LogicException('Consent records are only ever added.');

        static::updating($refuse);
        static::deleting($refuse);
    }

    public static function record(SalonClient $client, ConsentKind $kind, ConsentAction $action, ?User $by): self
    {
        $consent = new self;
        $consent->salon_id = $client->salon_id;
        $consent->salon_client_id = $client->id;
        $consent->kind = $kind;
        $consent->action = $action;
        $consent->method = $kind->method();
        $consent->text_version = $kind->textVersion();
        $consent->recorded_by = $by?->id;
        $consent->save();

        return $consent;
    }
}
