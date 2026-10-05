<?php

namespace App\Notifications;

use App\Models\SalonInvitation;
use App\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SalonInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly SalonInvitation $invitation,
        public readonly string $plainToken,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $salonName = $this->invitation->salon->name;
        $safeName = Markdown::escape($salonName);

        return (new MailMessage)
            ->subject("Te invitaron al equipo de {$salonName}")
            ->greeting('¡Hola!')
            ->line("{$safeName} te invitó a unirte a su equipo en peluWeb como {$this->invitation->role->label()}.")
            ->action('Aceptar invitación', route('invitations.show', $this->plainToken))
            ->line('La invitación vence en '.SalonInvitation::VALID_DAYS.' días.')
            ->line('Si no esperabas este correo, puedes ignorarlo.');
    }
}
