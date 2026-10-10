<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Accounting\ExportMovements;
use App\Data\Accounting\Period;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The period's movements as a CSV that Excel and Google Sheets open
 * directly. Needs the reports permission.
 */
class ReportExportController extends SalonAreaController
{
    /** Excel reads UTF-8 only when the file starts with this mark. */
    private const string UTF8_BOM = "\xEF\xBB\xBF";

    public function __invoke(Request $request, ExportMovements $export): StreamedResponse
    {
        $salon = $this->salon('viewReports');
        $period = Period::inSalon($salon, $request->query('desde'), $request->query('hasta'));
        $rows = $export->handle($salon, $period);
        $name = "movimientos-{$salon->slug}-{$period->from->format('Y-m-d')}-a-{$period->to->format('Y-m-d')}.csv";

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, self::UTF8_BOM);
            // Semicolons: what Excel expects with a Spanish locale.
            fputcsv($out, ExportMovements::HEADINGS, ';', '"', '');

            foreach ($rows as $row) {
                fputcsv($out, $row, ';', '"', '');
            }

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
    }
}
