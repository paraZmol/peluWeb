<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PushSubscriptionRequest;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The devices a person lets notify them. Called by the browser itself
 * right after it grants or drops the permission, so it answers in JSON.
 */
class PushSubscriptionController extends Controller
{
    public function store(PushSubscriptionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $endpoint = $request->string('endpoint')->toString();

        // A device belongs to whoever is signed in on it now.
        $hash = PushSubscription::hashOf($endpoint);
        $subscription = PushSubscription::query()->where('endpoint_hash', $hash)->first() ?? new PushSubscription;
        $subscription->endpoint_hash = $hash;
        $subscription->user_id = $user->id;
        $subscription->endpoint = $endpoint;
        $subscription->public_key = $request->string('keys.p256dh')->toString();
        $subscription->auth_token = $request->string('keys.auth')->toString();
        $subscription->user_agent = mb_substr((string) $request->userAgent(), 0, 255);
        $subscription->save();

        PushSubscription::query()->where('user_id', $user->id)
            ->whereNotIn('id', PushSubscription::query()->where('user_id', $user->id)->latest('id')->limit(PushSubscription::MAX_PER_USER)->pluck('id'))
            ->delete();

        return response()->json(['ok' => true], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $endpoint = $request->string('endpoint')->toString();

        // Only their own: another person's device answers the same and stays.
        PushSubscription::query()
            ->where('user_id', $request->user()?->id)
            ->where('endpoint_hash', PushSubscription::hashOf($endpoint))
            ->delete();

        return response()->json(['ok' => true]);
    }
}
