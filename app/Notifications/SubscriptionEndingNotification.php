<?php

namespace App\Notifications;

use App\Enums\SubscriptionState;
use App\Models\Salon;
use App\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the owners the trial or the paid time ends soon (7, 3 and 1 days
 * before, PLAN 7.2), and what happens if nobody pays: nothing is deleted.
 */
class SubscriptionEndingNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Salon $salon,
        public readonly SubscriptionState $state,
        public readonly int $daysLeft,
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
        $when = $this->daysLeft === 1 ? 'mañana' : "en {$this->daysLeft} días";
        $what = $this->state === SubscriptionState::Trial ? 'La prueba gratis' : 'El plan';
        $graceDays = (int) config('billing.grace_days');

        return (new MailMessage)
            ->subject("{$what} de {$this->salon->name} termina {$when}")
            ->greeting('¡Hola!')
            ->line("{$what} de ".Markdown::escape($this->salon->name)." termina {$when}.")
            ->line('Para seguir sin interrupciones, paga por Yape o transferencia. En «Mi plan» tienes los datos y lo que cuesta.')
            ->action('Ver mi plan', route('subscription.show'))
            ->line("Si no se paga, el salón sigue funcionando {$graceDays} días más. Después queda en solo lectura: las reservas en línea se pausan, pero puedes ver y descargar todo y no se borra nada.");
    }
}
