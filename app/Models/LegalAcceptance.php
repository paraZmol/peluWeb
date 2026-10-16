<?php

namespace App\Models;

use App\Data\Legal\AcceptanceProof;
use App\Data\Legal\PublishedDocument;
use App\Enums\AcceptanceChannel;
use App\Enums\LegalDocumentType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One account accepting one version of a legal text. Rows are only ever
 * added: they are the proof. Not salon-scoped (no BelongsToSalon): it is
 * the platform's own record, and `salon_id` only says which salon an
 * owner opened when accepting the salon terms.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $account_number That same id, kept after the account is gone.
 * @property int|null $salon_id
 * @property LegalDocumentType $document
 * @property string $version
 * @property string $text_hash SHA-256 of the published words.
 * @property AcceptanceChannel $channel
 * @property string|null $ip
 * @property string|null $user_agent
 * @property CarbonImmutable $created_at
 */
class LegalAcceptance extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document' => LegalDocumentType::class,
            'channel' => AcceptanceChannel::class,
        ];
    }

    protected static function booted(): void
    {
        $refuse = fn () => throw new LogicException('Legal acceptances are only ever added.');

        static::updating($refuse);
        static::deleting($refuse);
    }

    public static function record(User $user, PublishedDocument $document, AcceptanceProof $proof, ?Salon $salon = null): self
    {
        $acceptance = new self;
        $acceptance->user_id = $user->id;
        $acceptance->account_number = $user->id;
        $acceptance->salon_id = $salon?->id;
        $acceptance->document = $document->type;
        $acceptance->version = $document->version;
        $acceptance->text_hash = $document->hash();
        $acceptance->channel = $proof->channel;
        $acceptance->ip = $proof->ip;
        $acceptance->user_agent = $proof->userAgent;
        $acceptance->save();

        return $acceptance;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Salon, $this>
     */
    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }
}
