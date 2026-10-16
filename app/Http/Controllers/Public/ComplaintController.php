<?php

namespace App\Http\Controllers\Public;

use App\Actions\Complaints\FileComplaint;
use App\Enums\ComplaintItemType;
use App\Enums\ComplaintKind;
use App\Enums\IdentityDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreComplaintRequest;
use App\Models\Complaint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform's Libro de Reclamaciones (requirement P6): open to anyone,
 * no account, no captcha. The receipt is a signed link, so a sheet number
 * alone never shows anyone's data.
 */
class ComplaintController extends Controller
{
    /** How long the receipt page stays open after filing. */
    private const int RECEIPT_HOURS = 24;

    public function create(): Response
    {
        return Inertia::render('public/complaints/create', [
            'provider' => config('legal.provider'),
            'responseDays' => (int) config('legal.complaints.response_business_days'),
            'kinds' => collect(ComplaintKind::cases())->map(fn (ComplaintKind $kind) => ['value' => $kind->value, 'label' => $kind->label(), 'help' => $kind->help()]),
            'documents' => collect(IdentityDocument::cases())->map(fn (IdentityDocument $document) => ['value' => $document->value, 'label' => $document->label()]),
            'itemTypes' => collect(ComplaintItemType::cases())->map(fn (ComplaintItemType $type) => ['value' => $type->value, 'label' => $type->label()]),
        ]);
    }

    public function store(StoreComplaintRequest $request, FileComplaint $file): RedirectResponse
    {
        $complaint = $file->handle($request->sheet());

        return redirect()->to(URL::temporarySignedRoute('complaints.show', now()->addHours(self::RECEIPT_HOURS), ['complaint' => $complaint->id]));
    }

    public function show(Complaint $complaint): Response
    {
        return Inertia::render('public/complaints/show', [
            'receipt' => [
                'code' => $complaint->code(),
                'kind' => mb_strtolower($complaint->kind->label()),
                'email' => $complaint->email,
                'dueOn' => $complaint->due_on->format('d/m/Y'),
                'responseDays' => (int) config('legal.complaints.response_business_days'),
            ],
        ]);
    }
}
