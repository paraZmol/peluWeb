<?php

namespace App\Http\Controllers;

use App\Actions\Team\AcceptInvitation;
use App\Http\Middleware\ResolveCurrentSalon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvitationAcceptanceController extends Controller
{
    /**
     * Public page: shows who is inviting and asks the visitor to sign in or
     * create an account with the invited email before accepting.
     */
    public function show(Request $request, string $token, AcceptInvitation $acceptInvitation): Response
    {
        if ($request->user() === null) {
            // Bring the visitor back here after logging in or registering.
            $request->session()->put('url.intended', $request->fullUrl());
        }

        $invitation = $acceptInvitation->find($token);

        if ($invitation === null || ! $invitation->isPending()) {
            return Inertia::render('invitations/show', ['token' => $token, 'invitation' => null]);
        }

        return Inertia::render('invitations/show', [
            'token' => $token,
            'invitation' => [
                'salonName' => $invitation->salon->name,
                'role' => $invitation->role->label(),
                'email' => $invitation->email,
            ],
        ]);
    }

    public function store(Request $request, string $token, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $membership = $acceptInvitation->handle($request->user(), $token);

        $request->session()->put(ResolveCurrentSalon::SESSION_KEY, $membership->salon_id);
        Inertia::flash('toast', ['type' => 'success', 'message' => '¡Ya formas parte del equipo!']);

        return to_route('dashboard');
    }
}
