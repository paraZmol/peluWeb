<?php

/*
| What the platform's legal pages and its Libro de Reclamaciones need
| (docs/legal/). Provider details live in the environment: the repository
| is public and they are filled in when the business is registered.
*/

return [

    // The provider named on every complaint sheet (docs/legal, §4).
    'provider' => [
        'name' => env('LEGAL_PROVIDER_NAME', ''),
        'ruc' => env('LEGAL_PROVIDER_RUC', ''),
        'address' => env('LEGAL_PROVIDER_ADDRESS', ''),
    ],

    // The rest of the gaps in the published texts (resources/legal). An empty
    // one reads «por completar» outside production and a dash in production.
    'contact' => [
        'email' => env('LEGAL_CONTACT_EMAIL', ''),
        'privacy_email' => env('LEGAL_PRIVACY_EMAIL', ''),
        'support_hours' => env('LEGAL_SUPPORT_HOURS', ''),
    ],
    'jurisdiction_city' => env('LEGAL_JURISDICTION_CITY', ''),
    'service_providers' => [
        'hosting' => env('LEGAL_HOSTING_PROVIDER', ''),
        'mail' => env('LEGAL_MAIL_PROVIDER', ''),
    ],
    'payment_registration_term' => env('LEGAL_PAYMENT_REGISTRATION_TERM', ''),

    // One folder per text, one file per version: `{document}/{version}.md`.
    'documents_path' => resource_path('legal'),

    // Peru's clock: the yearly numbering and the deadline follow it.
    'timezone' => 'America/Lima',

    'complaints' => [
        // Art. 24 of the Código (Ley 31435): 15 business days, no extension.
        'response_business_days' => 15,

        // The inbox warns when this many business days are left, or fewer.
        'warning_days' => 5,
        'urgent_days' => 2,

        // Days that do not count, besides Saturdays and Sundays. "MM-DD"
        // repeats every year; "YYYY-MM-DD" is one date (Holy Week moves).
        // Review it every December against the official calendar.
        'holidays' => [
            '01-01', '05-01', '06-07', '06-29', '07-23', '07-28', '07-29',
            '08-06', '08-30', '10-08', '11-01', '12-08', '12-09', '12-25',
            '2026-04-02', '2026-04-03',
            '2027-03-25', '2027-03-26',
            '2028-04-13', '2028-04-14',
        ],
    ],

];
