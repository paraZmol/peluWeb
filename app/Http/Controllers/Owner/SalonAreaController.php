<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Salon;
use App\Support\CurrentSalon;
use Illuminate\Http\Request;
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

    /**
     * A query or body value only when it is plain text: `?desde[]=x` is
     * treated as missing instead of breaking a typed parameter.
     */
    protected function text(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_string($value) ? $value : null;
    }

    protected function toast(string $message, string $type = 'success'): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);
    }
}
