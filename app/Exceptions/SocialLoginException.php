<?php

namespace App\Exceptions;

use DomainException;

/**
 * Social sign-in problems. Messages are user facing.
 */
class SocialLoginException extends DomainException
{
    public static function unverifiedEmail(): self
    {
        return new self('Google no confirmó tu correo. Inicia sesión con tu correo y contraseña.');
    }

    public static function linkedToAnotherGoogleAccount(): self
    {
        return new self('Tu cuenta ya está vinculada a otra cuenta de Google. Inicia sesión con tu correo y contraseña.');
    }

    public static function cancelled(): self
    {
        return new self('No pudimos iniciar sesión con Google. Inténtalo de nuevo.');
    }
}
