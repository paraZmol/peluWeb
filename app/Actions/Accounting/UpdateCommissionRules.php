<?php

namespace App\Actions\Accounting;

use App\Models\Staff;
use Illuminate\Support\Facades\DB;

/**
 * Sets what a professional earns: a general percent and, per service they
 * perform, another percent or a fixed amount. Only future completions use
 * the new rules; visits already completed keep what they froze.
 */
class UpdateCommissionRules
{
    /**
     * @param  array<int, array{percent: int|null, cents: int|null}>  $byService  keyed by service id; services the professional does not perform are ignored
     */
    public function handle(Staff $staff, int $percent, array $byService): void
    {
        DB::transaction(function () use ($staff, $percent, $byService): void {
            // Same lock a completion takes: a visit ends under the old rules or the new, never a mix.
            $locked = Staff::withoutGlobalScope('salon')->lockForUpdate()->findOrFail($staff->id);
            $locked->commission_percent = $percent;
            $locked->save();

            foreach ($locked->services()->pluck('services.id') as $serviceId) {
                $rule = $byService[$serviceId] ?? ['percent' => null, 'cents' => null];

                $locked->services()->updateExistingPivot($serviceId, [
                    // A fixed amount wins: keeping both would hide which one applies.
                    'commission_percent' => $rule['cents'] === null ? $rule['percent'] : null,
                    'commission_cents' => $rule['cents'],
                ]);
            }
        });
    }
}
