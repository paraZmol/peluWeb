<?php

namespace App\Notifications;

use App\Models\Complaint;
use App\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The platform's answer to a sheet of its Libro de Reclamaciones, dated.
 */
class ComplaintAnsweredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Complaint $complaint)
    {
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
        $sheet = $this->complaint;
        $kind = mb_strtolower($sheet->kind->label());
        $answeredOn = $sheet->responded_at?->setTimezone((string) config('legal.timezone'))->format('d/m/Y') ?? '';

        return (new MailMessage)
            ->subject("Respuesta a tu {$kind} N.º {$sheet->code()}")
            ->greeting('¡Hola!')
            ->line("El {$answeredOn} respondimos tu {$kind} **N.º {$sheet->code()}**:")
            ->line('> '.Markdown::escape((string) $sheet->response))
            ->line('Si no estás conforme con la respuesta, puedes acudir a Indecopi u otras vías.')
            ->salutation('Te escribimos porque presentaste esta hoja en el Libro de Reclamaciones de '.Markdown::escape((string) config('app.name')).'.');
    }
}
