<?php

namespace App\Enums;

/**
 * Why a client is written about their booking.
 */
enum BookingNotice: string
{
    /** Booked, and the slot is held until the deposit arrives. */
    case Received = 'received';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Moved = 'moved';
    /** The day before the visit. */
    case Reminder = 'reminder';
    /** After the visit: how did it go? */
    case ReviewRequest = 'review';

    public function subject(string $salonName): string
    {
        return match ($this) {
            self::Received => "Tu reserva en {$salonName} espera la seña",
            self::Confirmed => "Reserva confirmada en {$salonName}",
            self::Cancelled => "Tu reserva en {$salonName} fue cancelada",
            self::Moved => "Tu reserva en {$salonName} cambió de horario",
            self::Reminder => "Recordatorio: tu cita en {$salonName}",
            self::ReviewRequest => "¿Cómo te fue en {$salonName}?",
        };
    }

    /**
     * A courtesy the person may switch off; what happened to a booking is always told.
     */
    public function isOptional(): bool
    {
        return $this === self::Reminder || $this === self::ReviewRequest;
    }

    public function action(): string
    {
        return $this === self::ReviewRequest ? 'Dejar mi reseña' : 'Ver mi reserva';
    }

    public function intro(): string
    {
        return match ($this) {
            self::Received => 'Recibimos tu reserva. El horario queda apartado mientras llega la seña.',
            self::Confirmed => 'Tu reserva está confirmada. Te esperamos.',
            self::Cancelled => 'Tu reserva fue cancelada y el horario quedó libre.',
            self::Moved => 'Tu reserva tiene un nuevo horario.',
            self::Reminder => 'Te recordamos tu cita.',
            self::ReviewRequest => 'Gracias por tu visita. Cuéntale al salón cómo te fue: te toma un minuto.',
        };
    }
}
