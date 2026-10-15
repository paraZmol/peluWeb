<?php

/*
| The platform's subscription rules (docs/PLAN.md, Fase 7 and §11 of
| 2026-10-15). Plan prices live in the `plans` table; these are the rules
| around them.
*/

return [

    // Days a salon keeps working after its last covered day, before read only.
    'grace_days' => 7,

    // Days before the last covered day when the owners are reminded.
    'notice_days' => [7, 3, 1],

    // The one extension of a trial the platform admin may give by hand.
    'trial_extension_days' => 15,

    // Founder price for the first salons that pay: both limits are written
    // in the offer (Indecopi), so they live in the environment, not in code.
    'founder' => [
        'slots' => (int) env('BILLING_FOUNDER_SLOTS', 10),
        'ends_on' => env('BILLING_FOUNDER_ENDS_ON'),
    ],

    // Where salons pay the subscription (shown on "Mi plan").
    'pay_to' => [
        'yape' => env('BILLING_YAPE_NUMBER'),
        'name' => env('BILLING_PAYEE_NAME'),
    ],

];
