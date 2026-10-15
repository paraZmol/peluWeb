<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Billing\SubscriptionSummary;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Mi plan": where the salon stands with peluWeb and how to pay. Payments
 * are recorded by the platform admin; here the owner only reads.
 */
class SubscriptionController extends SalonAreaController
{
    public function show(SubscriptionSummary $summary): Response
    {
        $salon = $this->salon('viewSubscription');

        return Inertia::render('owner/subscription', [
            'subscription' => $summary->handle($salon->loadMissing('plan')),
            'currency' => $salon->currency,
        ]);
    }
}
