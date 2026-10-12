<?php

namespace App\Notifications;

use App\Enums\BookingNotice;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Tells a professional, on their phone, that their agenda changed: a new
 * booking, one confirmed, moved or cancelled. Push only: the agenda is
 * where they look, an email for each visit would be noise.
 */
class StaffBookingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{salon: string, when: string, services: string, client: string, url: string, code: string}  $booking
     */
    public function __construct(
        public readonly BookingNotice $notice,
        public readonly array $booking,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    /**
     * @return array{title: string, body: string, url: string, tag: string}
     */
    public function toWebPush(object $notifiable): array
    {
        $title = match ($this->notice) {
            BookingNotice::Received => 'Nueva reserva: espera la seña',
            BookingNotice::Confirmed => 'Cita confirmada',
            BookingNotice::Cancelled => 'Cita cancelada',
            BookingNotice::Moved => 'Cita reprogramada',
            default => 'Tu agenda cambió',
        };

        return [
            'title' => $title,
            'body' => "{$this->booking['client']} · {$this->booking['services']} · {$this->booking['when']}",
            'url' => $this->booking['url'],
            'tag' => 'staff-'.$this->booking['code'],
        ];
    }
}
