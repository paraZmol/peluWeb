<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Legal\PendingLegalAcceptances;
use App\Actions\Legal\RecordLegalAcceptance;
use App\Data\Legal\AcceptanceProof;
use App\Enums\AcceptanceChannel;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureLegalTermsAccepted;
use App\Http\Requests\Auth\AcceptLegalTermsRequest;
use App\Http\Resources\LegalDocumentResource;
use App\Support\Legal\LegalLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Casilla 3: the screen an account without the current terms cannot skip.
 */
class LegalAcceptanceController extends Controller
{
    public function show(Request $request, PendingLegalAcceptances $pending, LegalLibrary $library): Response|RedirectResponse
    {
        $user = $request->user();
        $documents = $pending->for($user);

        if ($documents === []) {
            return redirect()->intended(route('dashboard'));
        }

        return Inertia::render('auth/accept-terms', [
            'mode' => $pending->acceptedBefore($user) ? 'update' : 'first',
            'account' => ['name' => $user->name, 'email' => $user->email],
            'documents' => LegalDocumentResource::forAccounts($library, $documents),
        ]);
    }

    public function store(AcceptLegalTermsRequest $request, RecordLegalAcceptance $record, PendingLegalAcceptances $pending): RedirectResponse
    {
        $user = $request->user();

        $record->handle($user, array_map(fn ($document) => $document->type, $pending->for($user)), AcceptanceProof::fromRequest($request, AcceptanceChannel::Screen));
        $request->session()->put(EnsureLegalTermsAccepted::SESSION_KEY, $pending->currentSignature($user));

        return redirect()->intended(route('dashboard'));
    }
}
