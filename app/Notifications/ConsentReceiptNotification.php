<?php

namespace App\Notifications;

use App\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The person's copy of an authorization they gave from their account:
 * the words they agreed to, when, and where to take it back.
 */
class ConsentReceiptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $what  "guardar tus alergias", "guardar fotos de tus servicios"
     * @param  string  $text  the exact words agreed to
     * @param  string  $when  when, on the salon's calendar
     */
    public function __construct(
        public readonly string $salonName,
        public readonly string $what,
        public readonly string $text,
        public readonly string $when,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $salon = Markdown::escape($this->salonName);

        return (new MailMessage)
            ->subject("Constancia: autorizaste a {$this->salonName}")
            ->greeting('¡Hola!')
            ->line("El {$this->when} autorizaste a **{$salon}** a {$this->what}. Esto fue lo que aceptaste:")
            ->line('> '.Markdown::escape($this->text))
            ->action('Ver o retirar mi autorización', route('my-data.index'))
            ->line('Puedes retirarla cuando quieras desde ese enlace; al hacerlo se borra lo guardado.')
            ->line('Si no fuiste tú, cambia tu contraseña y retira la autorización.');
    }
}
