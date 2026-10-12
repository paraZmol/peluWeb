<?php

namespace App\Notifications;

use App\Enums\BookingNotice;
use App\Notifications\Channels\WebPushChannel;
use App\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a client what happened with their booking. Carries the text it
 * needs, not the booking: the queue has no current salon to read it back
 * with, and the mail must say what was true when it was sent for.
 */
class BookingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Ten devices at ten seconds each at the very worst. */
    public int $timeout = 120;

    /**
     * @param  array{salon: string, when: string, services: string, staff: string, address: string|null, url: string}  $booking
     * @param  list<string>  $notes  extra lines for this occasion (deposit, refund), already plain text
     */
    public function __construct(
        public readonly BookingNotice $notice,
        public readonly array $booking,
        public readonly array $notes = [],
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        // The same news on the devices the person allowed, besides the email.
        return ['mail', WebPushChannel::class];
    }

    /**
     * @return array{title: string, body: string, url: string, tag: string}
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->notice->subject($this->booking['salon']),
            'body' => "{$this->booking['when']} · {$this->booking['services']}",
            'url' => $this->booking['url'],
            // One notification per booking: the latest news replaces the one before.
            'tag' => 'booking-'.basename($this->booking['url']),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->notice->subject($this->booking['salon']))
            ->greeting('¡Hola!')
            ->line($this->notice->intro())
            ->line('**'.Markdown::escape($this->booking['salon']).'**')
            ->line(Markdown::escape($this->booking['when']))
            ->line(Markdown::escape($this->booking['services']).' · con '.Markdown::escape($this->booking['staff']));

        if ($this->booking['address'] !== null) {
            $mail->line(Markdown::escape($this->booking['address']));
        }

        foreach ($this->notes as $note) {
            $mail->line(Markdown::escape($note));
        }

        $mail->action($this->notice->action(), $this->booking['url']);

        if ($this->notice !== BookingNotice::ReviewRequest) {
            $mail->line('Si necesitas cambiarla o cancelarla, hazlo desde ese enlace o escribe al salón.');
        }

        // A courtesy, not news about the booking: say how to stop it.
        return $this->notice->isOptional()
            ? $mail->line('¿No quieres estos correos? Desactívalos en tu perfil.')
            : $mail;
    }
}
