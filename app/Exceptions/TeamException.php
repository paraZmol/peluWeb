<?php

namespace App\Exceptions;

use DomainException;

/**
 * Business rule violations in team management. Messages are user facing.
 */
class TeamException extends DomainException
{
    public static function invitationNotValid(): self
    {
        return new self('Esta invitación ya no es válida. Pide a quien te invitó que te envíe una nueva.');
    }

    public static function invitationForAnotherEmail(): self
    {
        return new self('Esta invitación fue enviada a otro correo. Inicia sesión con ese correo para aceptarla.');
    }

    public static function alreadyMember(): self
    {
        return new self('Esta persona ya forma parte del equipo.');
    }

    public static function lastOwner(): self
    {
        return new self('El salón necesita al menos un dueño. Asigna otro dueño antes de hacer este cambio.');
    }
}
