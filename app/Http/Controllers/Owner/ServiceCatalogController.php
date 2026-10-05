<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Catalog\AddServicesFromTemplates;
use App\Models\ServiceTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceCatalogController extends SalonAreaController
{
    /**
     * Add one or more catalog entries to the salon's services.
     */
    public function store(Request $request, AddServicesFromTemplates $add): RedirectResponse
    {
        $validated = $request->validate([
            'template_ids' => ['required', 'array', 'min:1', 'max:120'],
            'template_ids.*' => ['integer', 'exists:service_templates,id'],
        ]);

        $count = $add->handle(
            $this->salon('manageServices'),
            ServiceTemplate::query()->whereIn('id', $validated['template_ids'])->orderBy('id')->get(),
        );

        $this->toast($count === 1 ? 'Agregamos 1 servicio.' : "Agregamos {$count} servicios.");

        return to_route('services.index');
    }
}
