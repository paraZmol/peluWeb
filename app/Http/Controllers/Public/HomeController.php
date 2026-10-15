<?php

namespace App\Http\Controllers\Public;

use App\Actions\Billing\PublicPricing;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The landing. Its prices come from the plans table, so changing a price
 * in the platform panel changes what visitors read.
 */
class HomeController extends Controller
{
    public function __invoke(PublicPricing $pricing): Response
    {
        return Inertia::render('welcome', ['pricing' => $pricing->handle()]);
    }
}
