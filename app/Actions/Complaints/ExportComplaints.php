<?php

namespace App\Actions\Complaints;

use App\Actions\Accounting\ExportMovements;
use App\Models\Complaint;
use App\Support\Money;

/**
 * Every sheet of the book as spreadsheet rows, oldest first: what Indecopi
 * may ask for, kept at least two years.
 */
class ExportComplaints
{
    public const array HEADINGS = [
        'Número', 'Fecha', 'Tipo', 'Proveedor', 'RUC', 'Domicilio del proveedor',
        'Nombre', 'Domicilio', 'Documento', 'N.º de documento', 'Teléfono', 'Correo', 'Madre, padre o tutor',
        'Bien', 'Descripción del bien', 'Monto reclamado', 'Detalle', 'Pedido',
        'Vence', 'Respuesta', 'Respondida',
    ];

    /**
     * @return list<list<string>>
     */
    public function handle(): array
    {
        $timezone = (string) config('legal.timezone');
        $rows = [];

        foreach (Complaint::query()->orderBy('year')->orderBy('number')->lazy() as $complaint) {
            $rows[] = array_map(ExportMovements::safeCell(...), [
                $complaint->code(),
                $complaint->created_at?->setTimezone($timezone)->format('Y-m-d H:i') ?? '',
                $complaint->kind->label(),
                $complaint->provider_name,
                $complaint->provider_ruc,
                $complaint->provider_address,
                $complaint->consumer_name,
                $complaint->consumer_address,
                $complaint->document_type->label(),
                $complaint->document_number,
                $complaint->phone,
                $complaint->email,
                $complaint->guardian_name ?? '',
                $complaint->item_type->label(),
                $complaint->item_description,
                $complaint->claimed_amount_cents === null ? '' : Money::toAmount($complaint->claimed_amount_cents),
                $complaint->detail,
                $complaint->request,
                $complaint->due_on->format('Y-m-d'),
                $complaint->response ?? '',
                $complaint->responded_at?->setTimezone($timezone)->format('Y-m-d H:i') ?? '',
            ]);
        }

        return $rows;
    }
}
