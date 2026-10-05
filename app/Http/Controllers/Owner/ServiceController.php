<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Catalog\SaveService;
use App\Enums\ServiceCategory;
use App\Enums\ServiceTarget;
use App\Http\Requests\Owner\ServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Models\ServiceTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `{service}` bindings are filtered by the current salon (BelongsToSalon),
 * so another salon's service resolves to 404.
 */
class ServiceController extends SalonAreaController
{
    public function index(): Response
    {
        $salon = $this->salon('manageServices');
        $services = $salon->services()->with('variants')->orderBy('position')->get();

        return Inertia::render('owner/services/index', [
            'services' => ServiceResource::collection($services)->resolve(),
            'catalog' => ServiceTemplate::query()
                ->whereNotIn('id', $services->pluck('service_template_id')->filter())
                ->orderBy('id')
                ->get()
                ->map(fn (ServiceTemplate $template) => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'nameEn' => $template->name_en,
                    'description' => $template->description,
                    'category' => $template->category->value,
                    'target' => $template->target->value,
                    'minutes' => $template->active_minutes + $template->idle_minutes + $template->finish_minutes,
                    'priceMin' => intdiv($template->price_min_cents, 100),
                    'priceMax' => intdiv($template->price_max_cents, 100),
                    'tags' => $template->tags ?? [],
                ]),
            'categories' => collect(ServiceCategory::cases())->map(fn (ServiceCategory $c) => ['value' => $c->value, 'label' => $c->label()]),
            'targets' => collect(ServiceTarget::cases())->map(fn (ServiceTarget $t) => ['value' => $t->value, 'label' => $t->label()]),
        ]);
    }

    public function store(ServiceRequest $request, SaveService $save): RedirectResponse
    {
        $save->handle($this->salon('manageServices'), null, $request->serviceAttributes(), $request->variants());
        $this->toast('Servicio creado.');

        return to_route('services.index');
    }

    public function update(ServiceRequest $request, Service $service, SaveService $save): RedirectResponse
    {
        $save->handle($this->salon('manageServices'), $service, $request->serviceAttributes(), $request->variants());
        $this->toast('Servicio guardado.');

        return to_route('services.index');
    }

    public function toggle(Request $request, Service $service): RedirectResponse
    {
        $this->salon('manageServices');
        $service->is_active = $request->boolean('is_active');
        $service->save();

        return to_route('services.index');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $this->salon('manageServices');
        $service->delete();
        $this->toast('Servicio eliminado. Puedes volver a agregarlo desde el catálogo.');

        return to_route('services.index');
    }
}
