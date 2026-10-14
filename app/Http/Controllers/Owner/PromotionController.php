<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Promotions\ChangePromotionStatus;
use App\Actions\Promotions\SalonPromotions;
use App\Actions\Promotions\SavePromotion;
use App\Enums\PromotionStatus;
use App\Http\Requests\Owner\PromotionRequest;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\Staff;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Promociones": the owner's own. `{promotion}` is filtered by the
 * current salon (BelongsToSalon), so another salon's is a 404.
 */
class PromotionController extends SalonAreaController
{
    private const string ABILITY = 'managePromotions';

    public function index(SalonPromotions $promotions): Response
    {
        $salon = $this->salon(self::ABILITY);

        return Inertia::render('owner/promotions/index', [
            'promotions' => $promotions->handle($salon),
            // What a promotion can be about: the salon's own catalogue.
            'services' => Service::query()->where('is_active', true)->with('variants')->orderBy('position')->get()
                ->map(fn (Service $service): array => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'price' => Money::toAmount($service->price_cents),
                    'minutes' => $service->active_minutes + $service->idle_minutes + $service->finish_minutes,
                    'isAddon' => $service->is_addon,
                    // What a combo is priced from: its cheapest option.
                    'basePrice' => Money::toAmount($service->basePriceCents()),
                    'hasOptions' => $service->variants->isNotEmpty(),
                    'requiresEvaluation' => $service->requires_evaluation,
                    // One fixed price: the only kind that can be given away.
                    'canBeGift' => $service->variants->isEmpty() && ! $service->requires_evaluation,
                ])->values(),
            // Who it may be limited to: the active team.
            'team' => Staff::query()->where('is_active', true)->orderBy('position')->get(['id', 'name'])
                ->map(fn (Staff $person): array => ['id' => $person->id, 'name' => $person->name])->values(),
            'limits' => [
                'minPercent' => PromotionRequest::MIN_PERCENT,
                'maxPercent' => PromotionRequest::MAX_PERCENT,
                'maxDays' => Promotion::MAX_DAYS,
                'maxActive' => Promotion::MAX_ACTIVE,
            ],
            'today' => $salon->localNow()->toDateString(),
            'depositPercent' => $salon->deposit_percent,
            // The image to share is drawn in the salon's own template.
            'theme' => $salon->theme->value,
        ]);
    }

    public function store(PromotionRequest $request, SavePromotion $save, ChangePromotionStatus $status): RedirectResponse
    {
        $salon = $this->salon(self::ABILITY);
        $promotion = $save->handle($salon, $request->draft());

        return $this->saved($request, $status, $promotion);
    }

    public function update(PromotionRequest $request, Promotion $promotion, SavePromotion $save, ChangePromotionStatus $status): RedirectResponse
    {
        $salon = $this->salon(self::ABILITY);

        // One transaction: a later end date on a published one counts against
        // the three at a time, or nothing of it is saved.
        return DB::transaction(function () use ($request, $status, $save, $salon, $promotion): RedirectResponse {
            $saved = $save->handle($salon, $request->draft(), $promotion);

            if ($saved->status === PromotionStatus::Published) {
                $status->publish($salon, $saved);
            }

            return $this->saved($request, $status, $saved);
        });
    }

    public function publish(Promotion $promotion, ChangePromotionStatus $status): RedirectResponse
    {
        $status->publish($this->salon(self::ABILITY), $promotion);
        $this->toast('Promoción publicada.');

        return back();
    }

    public function pause(Promotion $promotion, ChangePromotionStatus $status): RedirectResponse
    {
        $this->salon(self::ABILITY);
        $status->pause($promotion);
        $this->toast('Promoción en pausa. Las reservas ya hechas conservan su precio.');

        return back();
    }

    public function duplicate(Promotion $promotion, ChangePromotionStatus $status): RedirectResponse
    {
        $status->duplicate($this->salon(self::ABILITY), $promotion);
        $this->toast('Copia guardada como borrador.');

        return back();
    }

    public function destroy(Promotion $promotion, ChangePromotionStatus $status): RedirectResponse
    {
        $this->salon(self::ABILITY);
        $status->delete($promotion);
        $this->toast('Borrador eliminado.');

        return back();
    }

    /**
     * "Publicar" and "Guardar borrador" are the same form with one more field.
     */
    private function saved(PromotionRequest $request, ChangePromotionStatus $status, Promotion $promotion): RedirectResponse
    {
        if ($request->boolean('publish')) {
            $status->publish($this->salon(self::ABILITY), $promotion);
            $this->toast('Promoción publicada.');
        } else {
            $this->toast('Promoción guardada.');
        }

        return back();
    }
}
