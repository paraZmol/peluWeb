<?php

namespace App\Notifications;

use App\Models\SupportVisit;
use App\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the owners that peluWeb support is looking at their salon (PLAN
 * 7.4, L27): when, why, and that it can only look. A service notice, never
 * an offer.
 */
class SupportVisitStartedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly SupportVisit $visit) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $salon = $this->visit->salon;
        $startedAt = $this->visit->started_at->setTimezone($salon->timezone)->format('d/m/Y H:i');

        return (new MailMessage)
            ->subject("Soporte de peluWeb entró a {$salon->name}")
            ->greeting('¡Hola!')
            ->line('El equipo de soporte de peluWeb entró a '.Markdown::escape($salon->name)." el {$startedAt}.")
            ->line('Motivo: '.Markdown::escape($this->visit->reason))
            ->line('Solo puede mirar: no cambia ni descarga nada, no ve alergias ni fotos de tus clientes y la visita se cierra sola en '.intdiv(SupportVisit::MAX_MINUTES, 60).' horas.')
            ->action('Ver las visitas en Mi plan', route('subscription.show'))
            ->line('Si no pediste ayuda y no reconoces esta visita, responde a este correo.');
    }
}
