<?php

namespace App\Actions\Accounting;

use App\Data\Accounting\Period;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Salon;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Every movement of money in a period as rows for a spreadsheet: payments
 * received, deposits returned and expenses, oldest first.
 */
class ExportMovements
{
    public const array HEADINGS = ['Fecha', 'Hora', 'Tipo', 'Detalle', 'Método', 'Entrada', 'Salida'];

    /**
     * @return list<list<string>>
     */
    public function handle(Salon $salon, Period $period): array
    {
        $rows = [];

        $payments = Payment::withoutGlobalScope('salon')
            ->with(['booking' => fn ($query) => $query->withoutGlobalScope('salon')->with(['client' => fn ($client) => $client->withoutGlobalScope('salon')])])
            ->where('salon_id', $salon->id)
            ->where('paid_at', '>=', $period->startsAtUtc())
            ->where('paid_at', '<', $period->endsBeforeUtc())
            ->get();

        foreach ($payments as $payment) {
            $amount = Money::toAmount($payment->amount_cents);
            $detail = $payment->booking === null ? '' : "Reserva {$payment->booking->code} · {$payment->booking->client->name}";

            $rows[] = [$payment->paid_at->getTimestamp(), [
                ...$this->when($salon, $payment->paid_at),
                $payment->kind->label(),
                $detail,
                $payment->method->label(),
                $payment->kind->isOutgoing() ? '' : $amount,
                $payment->kind->isOutgoing() ? $amount : '',
            ]];
        }

        $expenses = Expense::withoutGlobalScope('salon')
            ->with(['category' => fn ($query) => $query->withoutGlobalScope('salon')])
            ->where('salon_id', $salon->id)
            ->where('paid_at', '>=', $period->startsAtUtc())
            ->where('paid_at', '<', $period->endsBeforeUtc())
            ->get();

        foreach ($expenses as $expense) {
            $rows[] = [$expense->paid_at->getTimestamp(), [
                ...$this->when($salon, $expense->paid_at),
                'Gasto',
                trim(($expense->category->name ?? 'Sin categoría').' · '.($expense->description ?? ''), ' ·'),
                $expense->method->label(),
                '',
                Money::toAmount($expense->amount_cents),
            ]];
        }

        usort($rows, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return array_map(fn (array $row): array => array_map(self::safeCell(...), $row[1]), $rows);
    }

    /**
     * A cell a spreadsheet would run as a formula is stored as plain text
     * instead: names and descriptions are typed by people.
     */
    public static function safeCell(string $value): string
    {
        return preg_match('/\A[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }

    /**
     * @return array{string, string}
     */
    private function when(Salon $salon, \DateTimeInterface $instant): array
    {
        $local = CarbonImmutable::instance($instant)->setTimezone($salon->timezone);

        return [$local->format('Y-m-d'), $local->format('H:i')];
    }
}
