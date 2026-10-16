<?php

namespace App\Console\Commands;

use App\Enums\SalonRole;
use App\Enums\SalonStatus;
use App\Enums\SubscriptionState;
use App\Models\Salon;
use App\Models\SalonMember;
use App\Notifications\SubscriptionEndingNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

#[Signature('subscriptions:send-reminders')]
#[Description('Reminds owners that the trial or the paid time of their salon ends in 7, 3 or 1 days')]
class SendSubscriptionReminders extends Command
{
    public function handle(): int
    {
        /** @var list<int> $noticeDays */
        $noticeDays = config('billing.notice_days');
        $sent = 0;

        Salon::query()
            ->where('status', '!=', SalonStatus::Suspended)
            ->where(fn ($query) => $query->whereNotNull('trial_ends_at')->orWhereNotNull('paid_until'))
            ->chunkById(200, function ($salons) use ($noticeDays, &$sent): void {
                foreach ($salons as $salon) {
                    $sent += $this->remind($salon, $noticeDays) ? 1 : 0;
                }
            });

        $this->info("Avisos de vencimiento enviados: {$sent}");

        return self::SUCCESS;
    }

    /**
     * @param  list<int>  $noticeDays
     */
    private function remind(Salon $salon, array $noticeDays): bool
    {
        $standing = $salon->standing();
        $isEnding = in_array($standing->state, [SubscriptionState::Trial, SubscriptionState::Active], true)
            && in_array($standing->daysLeft, $noticeDays, true);

        if (! $isEnding) {
            return false;
        }

        // Claimed first: a reminder sent twice is worse than one that fails once.
        $today = $salon->localNow()->toDateString();
        $claimed = Salon::query()->whereKey($salon->id)
            ->where(fn ($query) => $query->whereNull('billing_notice_on')->orWhere('billing_notice_on', '<', $today))
            ->update(['billing_notice_on' => $today]);

        if ($claimed !== 1) {
            return false;
        }

        $owners = SalonMember::query()->with('user')->where('salon_id', $salon->id)->where('role', SalonRole::Owner)->get()
            ->map(fn (SalonMember $member) => $member->user)
            ->filter();

        Notification::send($owners, new SubscriptionEndingNotification($salon, $standing->state, (int) $standing->daysLeft));

        return true;
    }
}
