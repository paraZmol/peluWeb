<?php

namespace App\Notifications\Channels;

use App\Enums\PushResult;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\Push\PushSender;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Notification;

/**
 * Sends a notification to every device the person allowed, and forgets
 * the ones the push service says are gone.
 */
class WebPushChannel
{
    public function __construct(private readonly PushSender $sender) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! method_exists($notification, 'toWebPush') || ! $this->sender->isConfigured()) {
            return;
        }

        /** @var array{title: string, body: string, url: string, tag: string} $message */
        $message = $notification->toWebPush($notifiable);

        foreach (PushSubscription::query()->where('user_id', $notifiable->id)->get() as $subscription) {
            $result = $this->sender->send($subscription, $message);

            if ($result === PushResult::Gone) {
                $subscription->delete();
            } elseif ($result === PushResult::Sent) {
                $subscription->forceFill(['last_used_at' => CarbonImmutable::now()])->save();
            }
        }
    }
}
