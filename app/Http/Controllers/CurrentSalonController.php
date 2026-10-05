<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ResolveCurrentSalon;
use App\Models\Salon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CurrentSalonController extends Controller
{
    /**
     * Switch the salon the user is working on.
     */
    public function update(Request $request, Salon $salon): RedirectResponse
    {
        abort_if($request->user()?->membershipIn($salon) === null, 403);

        $request->session()->put(ResolveCurrentSalon::SESSION_KEY, $salon->id);

        return to_route('dashboard');
    }
}
