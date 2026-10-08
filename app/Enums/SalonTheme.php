<?php

namespace App\Enums;

/**
 * The design template of a salon's public page (its booking wizard and
 * "Mis reservas" inherit it). Tokens and fonts live in the frontend
 * (skill `ui-system`); the server only stores the choice.
 */
enum SalonTheme: string
{
    case Editorial = 'editorial';
    case Nocturna = 'nocturna';
    case Luminosa = 'luminosa';
    case Vibrante = 'vibrante';

    /**
     * Stored for salons that exist from before templates; new salons get
     * the one that suits their kind of business (defaultFor).
     */
    public static function default(): self
    {
        return self::Nocturna;
    }

    /**
     * A barbershop starts dark and bold; a beauty or nail salon starts
     * bright. Any salon can change it afterwards.
     */
    public static function defaultFor(BusinessType $type): self
    {
        return match ($type) {
            BusinessType::Barbershop, BusinessType::Unisex => self::Nocturna,
            BusinessType::BeautySalon, BusinessType::Nails, BusinessType::Spa => self::Luminosa,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Editorial => 'Editorial',
            self::Nocturna => 'Nocturna',
            self::Luminosa => 'Luminosa',
            self::Vibrante => 'Vibrante',
        };
    }

    /**
     * What the owner reads under each option when choosing.
     */
    public function tagline(): string
    {
        return match ($this) {
            self::Editorial => 'Sobria, con aire de revista: fondo cálido de día y carbón de noche, tus fotos y tu color.',
            self::Nocturna => 'Oscura y con carácter, como una barbería de noche. Tus fotos a pantalla completa.',
            self::Luminosa => 'Blanca y fresca, como un salón con luz natural. Tarjetas con foto y precio.',
            self::Vibrante => 'Tu color a toda pantalla, con energía de app moderna.',
        };
    }
}
