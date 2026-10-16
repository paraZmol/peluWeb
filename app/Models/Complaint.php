<?php

namespace App\Models;

use App\Domain\Complaints\BusinessDays;
use App\Enums\ComplaintItemType;
use App\Enums\ComplaintKind;
use App\Enums\IdentityDocument;
use App\Exceptions\ComplaintException;
use Carbon\CarbonImmutable;
use Database\Factories\ComplaintFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sheet of the platform's Libro de Reclamaciones (requirement P6). A
 * platform record: it belongs to no salon. Once filed nothing changes but
 * its answer, written a single time; it is never deleted.
 *
 * @property int $id
 * @property int $year
 * @property int $number Correlative within the year, from 1, without gaps.
 * @property ComplaintKind $kind
 * @property string $provider_name
 * @property string $provider_ruc
 * @property string $provider_address
 * @property string $consumer_name
 * @property string $consumer_address
 * @property IdentityDocument $document_type
 * @property string $document_number
 * @property string $phone
 * @property string $email
 * @property string|null $guardian_name Filled when the person is a minor.
 * @property ComplaintItemType $item_type
 * @property string $item_description
 * @property int|null $claimed_amount_cents
 * @property string $currency
 * @property string $detail
 * @property string $request
 * @property CarbonImmutable $consented_at
 * @property CarbonImmutable $due_on Last business day to answer, in Peru.
 * @property string|null $response
 * @property CarbonImmutable|null $responded_at
 * @property int|null $responded_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'year', 'number', 'kind', 'provider_name', 'provider_ruc', 'provider_address',
    'consumer_name', 'consumer_address', 'document_type', 'document_number', 'phone', 'email', 'guardian_name',
    'item_type', 'item_description', 'claimed_amount_cents', 'currency', 'detail', 'request',
    'consented_at', 'due_on',
])]
class Complaint extends Model
{
    /** @use HasFactory<ComplaintFactory> */
    use HasFactory;

    /** The only columns written after filing, and only while unanswered. */
    private const array ANSWER_COLUMNS = ['response', 'responded_at', 'responded_by', 'updated_at'];

    protected $attributes = [
        'guardian_name' => null,
        'claimed_amount_cents' => null,
        'currency' => 'PEN',
        'response' => null,
        'responded_at' => null,
        'responded_by' => null,
    ];

    protected static function booted(): void
    {
        static::updating(function (Complaint $complaint): void {
            $changesSheet = array_diff(array_keys($complaint->getDirty()), self::ANSWER_COLUMNS) !== [];

            if ($changesSheet || $complaint->getOriginal('responded_at') !== null) {
                throw ComplaintException::immutable();
            }
        });

        static::deleting(fn () => throw ComplaintException::immutable());
    }

    protected function casts(): array
    {
        return [
            'kind' => ComplaintKind::class,
            'document_type' => IdentityDocument::class,
            'item_type' => ComplaintItemType::class,
            'claimed_amount_cents' => 'integer',
            'consented_at' => 'datetime',
            'due_on' => 'date',
            'responded_at' => 'datetime',
        ];
    }

    /**
     * The calendar the deadline is counted on.
     */
    public static function businessDays(): BusinessDays
    {
        /** @var list<string> $holidays */
        $holidays = config('legal.complaints.holidays', []);

        return new BusinessDays($holidays);
    }

    /**
     * Today's date in Peru.
     */
    public static function localToday(): CarbonImmutable
    {
        return CarbonImmutable::now((string) config('legal.timezone'))->startOfDay();
    }

    /**
     * "000123-2026", as printed on the sheet and in the emails.
     */
    public function code(): string
    {
        return sprintf('%06d-%d', $this->number, $this->year);
    }

    /**
     * "Razón social · RUC 20… · Domicilio", skipping what is not filled in.
     */
    public function providerLine(): string
    {
        return implode(' · ', array_filter([
            $this->provider_name,
            $this->provider_ruc === '' ? '' : "RUC {$this->provider_ruc}",
            $this->provider_address,
        ]));
    }

    public function isAnswered(): bool
    {
        return $this->responded_at !== null;
    }

    public function businessDaysLeft(): int
    {
        $dueOn = CarbonImmutable::parse($this->due_on->format('Y-m-d'), (string) config('legal.timezone'));

        return self::businessDays()->leftUntil(self::localToday(), $dueOn);
    }

    /**
     * @param  Builder<Complaint>  $query
     */
    public function scopeUnanswered(Builder $query): void
    {
        $query->whereNull('responded_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
