<?php

namespace App\Http\Resources;

use App\Models\Service;
use App\Models\ServiceVariant;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Service
 */
class ServiceResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category->value,
            'target' => $this->target->value,
            'price' => Money::toAmount($this->price_cents),
            'priceCents' => $this->price_cents,
            'activeMinutes' => $this->active_minutes,
            'idleMinutes' => $this->idle_minutes,
            'finishMinutes' => $this->finish_minutes,
            'totalMinutes' => $this->totalMinutes(),
            'isAddon' => $this->is_addon,
            'requiresEvaluation' => $this->requires_evaluation,
            'requiresPatchTest' => $this->requires_patch_test,
            'isActive' => $this->is_active,
            'variants' => $this->variants->map(fn (ServiceVariant $variant) => [
                'id' => $variant->id,
                'label' => $variant->label,
                'price' => Money::toAmount($variant->price_cents),
                'priceCents' => $variant->price_cents,
                'extraMinutes' => $variant->extra_minutes,
            ])->values(),
        ];
    }
}
