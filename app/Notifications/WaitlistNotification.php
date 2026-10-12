<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use App\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells someone on a waiting list that a time opened up for what they
 * asked, and until when it is theirs to take. Plain text only: the queue
 * has no current salon to read anything back with.
 */
class WaitlistNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public function __construct(
        public readonly string $salon,
        public readonly string $day,
        public readonly string $until,
        public readonly string $url,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', WebPushChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Se liberó un horario en {$this->salon}")
            ->greeting('¡Hola!')
            ->line('Estabas en la lista de espera y se abrió un horario para lo que pediste.')
            ->line('**'.Markdown::escape($this->salon).'**')
            ->line(Markdown::escape($this->day))
            ->action('Reservar ahora', $this->url)
            ->line("Eres la primera persona a la que avisamos. Si no reservas antes de las {$this->until}, avisaremos a la siguiente de la lista.")
            ->line('El horario no queda apartado: si alguien lo reserva antes, sigues en la lista.');
    }

    /**
     * @return array{title: string, body: string, url: string, tag: string}
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => "Se liberó un horario en {$this->salon}",
            'body' => "{$this->day}. Resérvalo antes de las {$this->until}.",
            'url' => $this->url,
            'tag' => 'waitlist-'.md5($this->url),
        ];
    }
}
