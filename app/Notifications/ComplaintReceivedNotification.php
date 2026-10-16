<?php

namespace App\Notifications;

use App\Models\Complaint;
use App\Support\Markdown;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The person's full copy of the sheet they just filed in the platform's
 * Libro de Reclamaciones, with its number and the deadline to answer.
 */
class ComplaintReceivedNotification extends Notification implements ShouldQueue
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
        $filedOn = $sheet->created_at?->setTimezone((string) config('legal.timezone'))->format('d/m/Y H:i') ?? '';
        $days = (int) config('legal.complaints.response_business_days');
        $amount = $sheet->claimed_amount_cents === null ? 'No indicado' : 'S/ '.Money::toAmount($sheet->claimed_amount_cents);

        $mail = (new MailMessage)
            ->subject("Recibimos tu {$kind} N.º {$sheet->code()}")
            ->greeting('¡Hola!')
            ->line("Recibimos tu {$kind} **N.º {$sheet->code()}** del {$filedOn}. Te responderemos en un máximo de {$days} días hábiles, hasta el {$sheet->due_on->format('d/m/Y')}.")
            ->line('Esta es la copia completa de tu hoja:')
            ->line('**Proveedor:** '.Markdown::escape($sheet->providerLine()))
            ->line('**Nombre:** '.Markdown::escape($sheet->consumer_name))
            ->line('**Domicilio:** '.Markdown::escape($sheet->consumer_address))
            ->line("**{$sheet->document_type->label()}:** ".Markdown::escape($sheet->document_number))
            ->line('**Teléfono:** '.Markdown::escape($sheet->phone).' · **Correo:** '.Markdown::escape($sheet->email));

        if ($sheet->guardian_name !== null) {
            $mail->line('**Madre, padre o tutor:** '.Markdown::escape($sheet->guardian_name));
        }

        return $mail
            ->line("**Tipo:** {$sheet->kind->label()} · **{$sheet->item_type->label()}:** ".Markdown::escape($sheet->item_description))
            ->line("**Monto reclamado:** {$amount}")
            ->line('**Detalle:** '.Markdown::escape($sheet->detail))
            ->line('**Pedido:** '.Markdown::escape($sheet->request))
            ->line('Presentar un reclamo no impide acudir a otras vías ni es requisito para denunciar ante Indecopi.')
            ->salutation('Usamos estos datos solo para atender tu reclamo y los guardamos el tiempo que exige la ley.');
    }
}
