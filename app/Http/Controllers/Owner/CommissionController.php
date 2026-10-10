<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Accounting\CommissionOverview;
use App\Actions\Accounting\SettleCommissions;
use App\Actions\Accounting\UpdateCommissionRules;
use App\Data\Accounting\Period;
use App\Enums\PaymentMethod;
use App\Http\Requests\Owner\CommissionRulesRequest;
use App\Http\Requests\Staff\PaymentMethodRequest;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What each professional earns. Seeing and paying commissions needs the
 * cash permission; the rules are the owner's. `{staff}` is filtered by the
 * current salon (BelongsToSalon).
 */
class CommissionController extends SalonAreaController
{
    public function index(Request $request, CommissionOverview $overview): Response
    {
        $salon = $this->salon('manageCash');
        $period = Period::inSalon($salon, $this->text($request, 'desde'), $this->text($request, 'hasta'));

        return Inertia::render('owner/commissions/index', [
            'from' => $period->from->format('Y-m-d'),
            'to' => $period->to->format('Y-m-d'),
            'today' => $salon->localNow()->toDateString(),
            ...$overview->handle($salon, $period),
            'canEditRules' => $request->user()?->can('manageTeam', $salon) ?? false,
            'paymentMethods' => array_map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()], PaymentMethod::cases()),
        ]);
    }

    public function settle(PaymentMethodRequest $request, Staff $staff, SettleCommissions $settle): RedirectResponse
    {
        $salon = $this->salon('manageCash');
        $period = Period::inSalon($salon, $this->text($request, 'from'), $this->text($request, 'to'));
        $settle->handle($staff, $period, $request->paymentMethod(), $request->user());
        $this->toast("Comisiones de {$staff->name} liquidadas.");

        return back();
    }

    public function updateRules(CommissionRulesRequest $request, Staff $staff, UpdateCommissionRules $update): RedirectResponse
    {
        $this->salon('manageTeam');
        $update->handle($staff, $request->integer('percent'), $request->byService());
        $this->toast("Comisión de {$staff->name} actualizada.");

        return back();
    }
}
