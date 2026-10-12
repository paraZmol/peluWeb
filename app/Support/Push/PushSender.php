<?php

namespace App\Support\Push;

use App\Enums\PushResult;
use App\Models\PushSubscription;

/**
 * Delivers one message to one device. Behind an interface so that tests,
 * and a server without keys, never reach a push service.
 */
interface PushSender
{
    public function isConfigured(): bool;

    /**
     * @param  array{title: string, body: string, url: string, tag: string}  $message
     */
    public function send(PushSubscription $subscription, array $message): PushResult;
}
