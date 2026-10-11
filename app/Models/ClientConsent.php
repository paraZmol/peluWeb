<?php

namespace App\Models;

use App\Concerns\BelongsToSalon;
use App\Data\Salon\ConsentProof;
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
 * @property int|null $recorded_by Who of the team declared it.
 * @property int|null $account_id The account that acted as the person.
 * @property int|null $account_number That same id, kept after the account is gone.
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $text_hash SHA-256 of the exact words agreed to.
 * @property array<string, int|bool>|null $details What a withdrawal removed.
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
            'details' => 'array',
        ];
    }

    protected static function booted(): void
    {
        $refuse = fn () => throw new LogicException('Consent records are only ever added.');

        static::updating($refuse);
        static::deleting($refuse);
    }

    public static function record(SalonClient $client, ConsentKind $kind, ConsentAction $action, ConsentProof $proof): self
    {
        $consent = new self;
        $consent->salon_id = $client->salon_id;
        $consent->salon_client_id = $client->id;
        $consent->kind = $kind;
        $consent->action = $action;
        $consent->method = $proof->method;
        $consent->text_version = $kind->textVersion();
        $consent->recorded_by = $proof->staff?->id;
        $consent->account_id = $proof->account?->id;
        $consent->account_number = $proof->account?->id;
        $consent->ip = $proof->ip;
        $consent->user_agent = $proof->userAgent;
        $consent->text_hash = $proof->text === null ? null : hash('sha256', $proof->text);
        $consent->details = $proof->details;
        $consent->save();

        return $consent;
    }

    /**
     * The last thing that happened with one kind of authorization.
     */
    public static function latest(SalonClient $client, ConsentKind $kind): ?self
    {
        return self::withoutGlobalScope('salon')
            ->where('salon_client_id', $client->id)
            ->where('kind', $kind)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * The person took it back themselves: only they can give it again.
     */
    public function isWithdrawnByThePerson(): bool
    {
        return $this->action === ConsentAction::Withdrawn && $this->method->isByThePerson();
    }
}
