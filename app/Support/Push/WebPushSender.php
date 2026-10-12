<?php

namespace App\Support\Push;

use App\Enums\PushResult;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Web Push with VAPID keys (config/services.php, "webpush"). Without keys
 * nothing is sent and nothing fails: push is an extra over the email.
 */
class WebPushSender implements PushSender
{
    /** A booking's news is stale after a day: the push service drops it then. */
    private const int TTL_SECONDS = 86400;

    public function isConfigured(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    public function send(PushSubscription $subscription, array $message): PushResult
    {
        if (! $this->isConfigured()) {
            return PushResult::Failed;
        }

        try {
            $report = $this->client()->sendOneNotification(
                new Subscription($subscription->endpoint, $subscription->public_key, $subscription->auth_token, 'aes128gcm'),
                (string) json_encode($message, JSON_UNESCAPED_UNICODE),
                ['TTL' => self::TTL_SECONDS, 'topic' => substr(hash('sha256', $message['tag']), 0, 32)],
            );
        } catch (Throwable $exception) {
            Log::warning('Web push failed', ['subscription' => $subscription->id, 'error' => $exception->getMessage()]);

            return PushResult::Failed;
        }

        return match (true) {
            $report->isSuccess() => PushResult::Sent,
            $report->isSubscriptionExpired() => PushResult::Gone,
            default => PushResult::Failed,
        };
    }

    private function client(): WebPush
    {
        return new WebPush(['VAPID' => [
            'subject' => (string) config('services.webpush.subject'),
            'publicKey' => (string) config('services.webpush.public_key'),
            'privateKey' => (string) config('services.webpush.private_key'),
        ]]);
    }
}
