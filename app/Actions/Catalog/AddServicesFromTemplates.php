<?php

namespace App\Actions\Catalog;

use App\Actions\Staff\AssignServicesToTeam;
use App\Models\Salon;
use App\Models\Service;
use App\Models\ServiceTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AddServicesFromTemplates
{
    /** Hair-length variants for services whose price depends on it. */
    private const array LENGTH_VARIANTS = [
        ['label' => 'Cabello corto', 'factor' => 1.0, 'extra_minutes' => 0],
        ['label' => 'Cabello medio', 'factor' => 1.2, 'extra_minutes' => 15],
        ['label' => 'Cabello largo', 'factor' => 1.4, 'extra_minutes' => 30],
    ];

    public function __construct(private readonly AssignServicesToTeam $assignToTeam) {}

    /**
     * Copy catalog entries into the salon's own services, with a suggested
     * price the owner can change. Re-adding a removed service restores it.
     *
     * @param  Collection<int, ServiceTemplate>  $templates
     * @return int number of services added
     */
    public function handle(Salon $salon, Collection $templates): int
    {
        return DB::transaction(function () use ($salon, $templates): int {
            $existing = Service::withoutGlobalScope('salon')->withTrashed()
                ->where('salon_id', $salon->id)
                ->whereIn('service_template_id', $templates->pluck('id'))
                ->get()
                ->keyBy('service_template_id');

            $position = (int) Service::withoutGlobalScope('salon')->where('salon_id', $salon->id)->max('position');
            $createdIds = [];
            $restored = 0;

            foreach ($templates as $template) {
                $service = $existing->get($template->id);

                if ($service !== null) {
                    if ($service->trashed()) {
                        // Its assignments were kept: it comes back to whoever performed it.
                        $service->restore();
                        $restored++;
                    }

                    continue;
                }

                $createdIds[] = $this->createFrom($salon, $template, ++$position)->id;
            }

            $this->assignToTeam->handle($salon, $createdIds);

            return count($createdIds) + $restored;
        });
    }

    private function createFrom(Salon $salon, ServiceTemplate $template, int $position): Service
    {
        $service = new Service([
            'category' => $template->category,
            'name' => $template->name,
            'description' => $template->description,
            'target' => $template->target,
            'price_cents' => $template->suggestedPriceCents(),
            'active_minutes' => $template->active_minutes,
            'idle_minutes' => $template->idle_minutes,
            'finish_minutes' => $template->finish_minutes,
            'is_addon' => $template->is_addon,
            'requires_evaluation' => $template->requires_evaluation,
            'requires_patch_test' => $template->requires_patch_test,
        ]);
        $service->salon_id = $salon->id;
        $service->service_template_id = $template->id;
        $service->position = $position;
        $service->save();

        if ($template->price_varies_by_length) {
            foreach (self::LENGTH_VARIANTS as $index => $variant) {
                $service->variants()->create([
                    'label' => $variant['label'],
                    'price_cents' => (int) (round($service->price_cents * $variant['factor'] / 100) * 100),
                    'extra_minutes' => $variant['extra_minutes'],
                    'position' => $index,
                ]);
            }
        }

        return $service;
    }
}
