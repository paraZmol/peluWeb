<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Accounting\SalonReport;
use App\Data\Accounting\Period;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Earnings and losses of the current salon. Needs the reports permission.
 */
class ReportController extends SalonAreaController
{
    public function index(Request $request, SalonReport $report): Response
    {
        $salon = $this->salon('viewReports');
        $period = Period::inSalon($salon, $this->text($request, 'desde'), $this->text($request, 'hasta'));
        $previous = $period->previous();

        return Inertia::render('owner/reports/index', [
            'from' => $period->from->format('Y-m-d'),
            'to' => $period->to->format('Y-m-d'),
            'previousFrom' => $previous->from->format('Y-m-d'),
            'previousTo' => $previous->to->format('Y-m-d'),
            'today' => $salon->localNow()->toDateString(),
            'currency' => $salon->currency,
            'report' => $report->handle($salon, $period),
        ]);
    }
}
