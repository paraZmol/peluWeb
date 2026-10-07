<?php

namespace App\Actions\Salon;

use App\Models\Salon;

/**
 * The "complete your salon" steps shown on the dashboard. A salon with a
 * logo, photos, location and services converts far more visitors.
 */
class SalonSetupChecklist
{
    private const int MIN_PHOTOS = 3;

    /**
     * @return list<array{key: string, label: string, done: bool, href: string}>
     */
    public function handle(Salon $salon): array
    {
        $edit = route('salon.edit');

        return [
            ['key' => 'logo', 'label' => 'Sube tu logo', 'done' => $salon->logo_path !== null, 'href' => "{$edit}#marca"],
            ['key' => 'cover', 'label' => 'Agrega una foto de portada', 'done' => $salon->cover_path !== null, 'href' => "{$edit}#marca"],
            ['key' => 'description', 'label' => 'Cuenta en dos líneas qué te hace especial', 'done' => filled($salon->description), 'href' => "{$edit}#datos"],
            ['key' => 'location', 'label' => 'Marca tu ubicación en el mapa', 'done' => $salon->hasLocation(), 'href' => "{$edit}#ubicacion"],
            ['key' => 'services', 'label' => 'Revisa tus servicios y precios', 'done' => $salon->services()->where('is_active', true)->whereColumn('created_at', '<', 'updated_at')->exists(), 'href' => route('services.index')],
            ['key' => 'schedule', 'label' => 'Define los horarios de atención', 'done' => ! $salon->openingHours()->isEmpty(), 'href' => route('staff.index')],
            ['key' => 'photos', 'label' => 'Sube al menos '.self::MIN_PHOTOS.' fotos de tus trabajos', 'done' => $salon->photos()->count() >= self::MIN_PHOTOS, 'href' => "{$edit}#galeria"],
        ];
    }
}
