<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Salon\CreateSalon;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveCurrentSalon;
use App\Http\Requests\Owner\StoreSalonRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SalonController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('owner/salons/create', [
            'businessTypes' => collect(BusinessType::cases())
                ->map(fn (BusinessType $type) => ['value' => $type->value, 'label' => $type->label()]),
            'clienteles' => collect(Clientele::cases())
                ->map(fn (Clientele $clientele) => ['value' => $clientele->value, 'label' => $clientele->label()]),
        ]);
    }

    public function store(StoreSalonRequest $request, CreateSalon $createSalon): RedirectResponse
    {
        $salon = $createSalon->handle(
            owner: $request->user(),
            name: $request->string('name')->trim()->toString(),
            businessType: $request->enum('business_type', BusinessType::class),
            clientele: $request->enum('clientele', Clientele::class),
            phone: $request->validated('phone'),
        );

        $request->session()->put(ResolveCurrentSalon::SESSION_KEY, $salon->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => "¡Listo! {$salon->name} ya está creado."]);

        return to_route('dashboard');
    }
}
