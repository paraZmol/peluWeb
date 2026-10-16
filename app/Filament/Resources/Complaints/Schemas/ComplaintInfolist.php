<?php

namespace App\Filament\Resources\Complaints\Schemas;

use App\Enums\ComplaintItemType;
use App\Enums\ComplaintKind;
use App\Filament\Resources\Complaints\Tables\ComplaintsTable;
use App\Models\Complaint;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The whole sheet as the person filed it, and its answer.
 */
class ComplaintInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $timezone = (string) config('legal.timezone');

        return $schema
            ->columns(1)
            ->components([
                Section::make('Hoja')->columns(3)->schema([
                    TextEntry::make('code')->label('N.º')->state(fn (Complaint $record): string => $record->code()),
                    TextEntry::make('kind')->label('Tipo')->badge()->formatStateUsing(fn (ComplaintKind $state): string => $state->label()),
                    TextEntry::make('created_at')->label('Presentada')->dateTime('d/m/Y H:i')->timezone($timezone),
                    TextEntry::make('provider')->label('Proveedor')->state(fn (Complaint $record): string => $record->providerLine())->placeholder('-')->columnSpanFull(),
                    TextEntry::make('due_on')->label('Vence')->date('d/m/Y'),
                    TextEntry::make('deadline')
                        ->label('Plazo')
                        ->badge()
                        ->state(fn (Complaint $record): string => ComplaintsTable::deadlineLabel($record))
                        ->color(fn (Complaint $record): string => ComplaintsTable::deadlineColor($record)),
                ]),
                Section::make('Quien reclama')->columns(3)->schema([
                    TextEntry::make('consumer_name')->label('Nombre'),
                    TextEntry::make('document')
                        ->label('Documento')
                        ->state(fn (Complaint $record): string => $record->document_type->label().' '.$record->document_number),
                    TextEntry::make('guardian_name')->label('Madre, padre o tutor')->placeholder('No es menor de edad'),
                    TextEntry::make('consumer_address')->label('Domicilio')->columnSpanFull(),
                    TextEntry::make('phone')->label('Teléfono'),
                    TextEntry::make('email')->label('Correo')->copyable(),
                ]),
                Section::make('Reclamo')->columns(3)->schema([
                    TextEntry::make('item_type')->label('Sobre')->formatStateUsing(fn (ComplaintItemType $state): string => $state->label()),
                    TextEntry::make('item_description')->label('Descripción'),
                    TextEntry::make('claimed_amount_cents')
                        ->label('Monto reclamado')
                        ->formatStateUsing(fn (?int $state): string => $state === null ? '-' : 'S/ '.Money::toAmount($state))
                        ->placeholder('-'),
                    TextEntry::make('detail')->label('Detalle')->columnSpanFull(),
                    TextEntry::make('request')->label('Pedido')->columnSpanFull(),
                ]),
                Section::make('Respuesta')->columns(2)->schema([
                    TextEntry::make('response')->label('Respuesta')->placeholder('Todavía sin respuesta')->columnSpanFull(),
                    TextEntry::make('responded_at')->label('Enviada')->dateTime('d/m/Y H:i')->timezone($timezone)->placeholder('-'),
                    TextEntry::make('responder.name')->label('Por')->placeholder('-'),
                ]),
            ]);
    }
}
