<?php

namespace App\Enums;

/**
 * The platform's published legal texts (docs/legal). The value is the
 * folder under resources/legal and the last part of its public URL.
 */
enum LegalDocumentType: string
{
    case ClientTerms = 'terminos-clientes';
    case SalonTerms = 'terminos-salones';
    case Privacy = 'privacidad';
    case Cookies = 'cookies';

    public function label(): string
    {
        return match ($this) {
            self::ClientTerms => 'Términos y condiciones',
            self::SalonTerms => 'Términos para salones',
            self::Privacy => 'Política de privacidad',
            self::Cookies => 'Política de cookies',
        };
    }

    /**
     * What every account accepts (casilla 1): asked at sign up and again
     * whenever one of them gets a new version.
     *
     * @return list<self>
     */
    public static function forAccounts(): array
    {
        return [self::ClientTerms, self::Privacy];
    }
}
