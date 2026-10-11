<?php

namespace App\Models;

use App\Concerns\BelongsToSalon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a client said about one completed visit. Written only by
 * App\Actions\Reviews\*; the salon answers and reports, never deletes.
 *
 * @property int $id
 * @property int $salon_id
 * @property int $booking_id
 * @property int $salon_client_id
 * @property int $staff_id
 * @property int $rating 1 to 5
 * @property string|null $comment
 * @property string $author_name First name only.
 * @property string|null $reply
 * @property CarbonImmutable|null $replied_at
 * @property int|null $replied_by
 * @property CarbonImmutable|null $reported_at
 * @property string|null $report_reason
 * @property int|null $reported_by
 * @property CarbonImmutable|null $report_resolved_at
 * @property CarbonImmutable|null $hidden_at Set only by the platform, after a report.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Salon $salon
 * @property-read Staff $staff
 */
class Review extends Model
{
    use BelongsToSalon;

    public const int MAX_RATING = 5;

    public const int COMMENT_MAX_LENGTH = 600;

    public const int REPORT_MAX_LENGTH = 300;

    /** A visit can be reviewed for this long after it took place. */
    public const int WINDOW_DAYS = 60;

    /** What stands in for a name once the account is gone. */
    public const string ANONYMOUS = 'Cliente';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'replied_at' => 'datetime',
            'reported_at' => 'datetime',
            'report_resolved_at' => 'datetime',
            'hidden_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Not hidden by the platform.
     *
     * @param  Builder<Review>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->whereNull('hidden_at');
    }

    /**
     * Reported and still waiting for the platform to decide.
     *
     * @param  Builder<Review>  $query
     */
    public function scopeAwaitingModeration(Builder $query): void
    {
        $query->whereNotNull('reported_at')->whereNull('report_resolved_at');
    }
}
