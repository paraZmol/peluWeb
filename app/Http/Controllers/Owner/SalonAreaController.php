<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Salon;
use App\Support\CurrentSalon;
use Inertia\Inertia;

/**
 * Base for controllers that act on the current salon (routes behind the
 * `salon` middleware, so a current salon always exists).
 */
abstract class SalonAreaController extends Controller
{
    /**
     * The current salon, after checking the given SalonPolicy ability.
     */
    protected function salon(string $ability): Salon
    {
        $salon = $this->currentSalon();

        $this->authorize($ability, $salon);

        return $salon;
    }

    /**
     * The current salon without a salon-level check, for actions that are
     * authorized against a record instead (e.g. StaffPolicy).
     */
    protected function currentSalon(): Salon
    {
        $salon = app(CurrentSalon::class)->get();
        abort_if($salon === null, 403);

        return $salon;
    }

    protected function toast(string $message, string $type = 'success'): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);
    }
}
