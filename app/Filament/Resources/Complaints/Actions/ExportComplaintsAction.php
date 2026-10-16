<?php

namespace App\Filament\Resources\Complaints\Actions;

use App\Actions\Complaints\ExportComplaints;
use Filament\Actions\Action;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The whole book as a CSV that Excel opens directly, for Indecopi.
 */
class ExportComplaintsAction
{
    /** Excel reads UTF-8 only when the file starts with this mark. */
    private const string UTF8_BOM = "\xEF\xBB\xBF";

    public static function make(): Action
    {
        return Action::make('export')
            ->label('Descargar CSV')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(fn (ExportComplaints $export): StreamedResponse => response()->streamDownload(function () use ($export): void {
                $out = fopen('php://output', 'w');

                if ($out === false) {
                    return;
                }

                fwrite($out, self::UTF8_BOM);
                // Semicolons: what Excel expects with a Spanish locale.
                fputcsv($out, ExportComplaints::HEADINGS, ';', '"', '');

                foreach ($export->handle() as $row) {
                    fputcsv($out, $row, ';', '"', '');
                }

                fclose($out);
            }, 'libro-de-reclamaciones-'.now((string) config('legal.timezone'))->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']));
    }
}
