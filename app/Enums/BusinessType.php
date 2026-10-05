<?php

namespace App\Enums;

enum BusinessType: string
{
    case Barbershop = 'barbershop';
    case BeautySalon = 'beauty_salon';
    case Unisex = 'unisex';
    case Nails = 'nails';
    case Spa = 'spa';

    public function label(): string
    {
        return match ($this) {
            self::Barbershop => 'Barbería',
            self::BeautySalon => 'Salón de belleza',
            self::Unisex => 'Peluquería unisex',
            self::Nails => 'Centro de uñas',
            self::Spa => 'Estética y spa',
        };
    }

    /**
     * Catalog entries pre-selected when a salon of this type is created
     * (docs/catalogo-servicios.md, "Plantillas por tipo de salón").
     * Codes match whole prefixes ("CH") or exact codes ("IN01").
     *
     * @return list<string>
     */
    public function starterCodes(): array
    {
        $barbershop = ['CH', 'BA', 'IN01', 'IN03', 'CO03'];
        $beautySalon = ['CM', 'CO', 'TQ', 'PE', 'IN02'];

        return match ($this) {
            self::Barbershop => $barbershop,
            self::BeautySalon => $beautySalon,
            self::Unisex => [...$barbershop, ...$beautySalon],
            self::Nails => ['UN', 'CP'],
            self::Spa => ['ES', 'DE', 'CP', 'UN07'],
        };
    }

    public function startsWith(string $templateCode): bool
    {
        foreach ($this->starterCodes() as $code) {
            if ($templateCode === $code || (strlen($code) === 2 && str_starts_with($templateCode, $code))) {
                return true;
            }
        }

        return false;
    }
}
