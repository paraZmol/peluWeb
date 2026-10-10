<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Accounting\CloseCashSession;
use App\Actions\Accounting\CountCashSession;
use App\Actions\Accounting\OpenCashSession;
use App\Http\Requests\Owner\CashAmountRequest;
use App\Http\Resources\CashSessionResource;
use App\Models\CashSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The salon's till: open it, see what moved, close it against the count.
 * Everything needs the cash permission; `{session}` is filtered by the
 * current salon (BelongsToSalon).
 */
class CashController extends SalonAreaController
{
    private const int HISTORY = 30;

    public function index(Request $request, CountCashSession $count): Response
    {
        $salon = $this->salon('manageCash');
        $open = CashSession::query()->whereNull('closed_at')->latest('opened_at')->first();
        $closed = CashSession::query()->whereNotNull('closed_at')->latest('closed_at')->limit(self::HISTORY)->get();

        return Inertia::render('owner/cash/index', [
            'session' => $open === null ? null : (new CashSessionResource($open, $salon->timezone, $count->handle($open)))->resolve($request),
            'history' => $closed->map(fn (CashSession $session) => (new CashSessionResource($session, $salon->timezone))->resolve($request)),
        ]);
    }

    public function open(CashAmountRequest $request, OpenCashSession $open): RedirectResponse
    {
        $open->handle($this->salon('manageCash'), $request->cents(), $request->user());
        $this->toast('Caja abierta.');

        return back();
    }

    public function close(CashAmountRequest $request, CashSession $session, CloseCashSession $close): RedirectResponse
    {
        $this->salon('manageCash');
        $close->handle($session, $request->cents(), $request->validated('note'), $request->user());
        $this->toast('Caja cerrada.');

        return back();
    }
}
