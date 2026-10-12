<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Waitlist\ManageWalkIns;
use App\Enums\WalkInStatus;
use App\Http\Controllers\Owner\SalonAreaController;
use App\Http\Requests\Staff\WalkInRequest;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\WalkIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Fila sin cita": the day's line of people who came without a booking.
 * Front-desk work, so anyone of the team uses it; `{walkIn}` is filtered
 * by the current salon (BelongsToSalon), so another salon's is a 404.
 */
class WalkInController extends SalonAreaController
{
    public function __construct(private readonly ManageWalkIns $line) {}

    public function index(): Response
    {
        $salon = $this->lineSalon();

        return Inertia::render('staff/walk-ins', [
            'line' => $this->line->today($salon)->map(fn (WalkIn $walkIn): array => [
                'id' => $walkIn->id,
                'name' => $walkIn->name,
                'serviceName' => $walkIn->service?->name,
                'staffName' => $walkIn->staff?->name,
                'status' => $walkIn->status->value,
                'statusLabel' => $walkIn->status->label(),
                'arrivedAt' => $walkIn->created_at?->setTimezone($salon->timezone)->format('H:i'),
                'calledAt' => $walkIn->called_at?->setTimezone($salon->timezone)->format('H:i'),
            ])->values(),
            'services' => Service::query()->where('is_active', true)->orderBy('position')->get(['id', 'name'])
                ->map(fn (Service $service): array => ['id' => $service->id, 'name' => $service->name])->values(),
            'staff' => Staff::query()->where('is_active', true)->orderBy('position')->get(['id', 'name'])
                ->map(fn (Staff $person): array => ['id' => $person->id, 'name' => $person->name])->values(),
            'nameMaxLength' => WalkIn::NAME_MAX_LENGTH,
        ]);
    }

    public function store(WalkInRequest $request): RedirectResponse
    {
        /** @var array{name: string, service_id?: int|null, staff_id?: int|null} $data */
        $data = $request->validated();
        $walkIn = $this->line->add($this->lineSalon(), $data, $request->user());
        $this->toast("{$walkIn->name} está en la fila.");

        return back();
    }

    public function update(Request $request, WalkIn $walkIn): RedirectResponse
    {
        $this->lineSalon();
        $next = WalkInStatus::from($request->validate([
            'status' => ['required', Rule::enum(WalkInStatus::class)],
        ])['status']);

        $this->line->move($walkIn, $next);

        return back();
    }

    /**
     * The salon, for a team that uses the line; a 404 where it is off.
     */
    private function lineSalon(): Salon
    {
        $salon = $this->currentSalon();
        abort_unless($salon->walk_ins_enabled, 404);

        return $salon;
    }
}
