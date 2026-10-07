<?php

namespace App\Http\Resources;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Support\Money;
use App\Support\WhatsApp;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A booking in the team's agenda, with the actions the viewer may take.
 * Expects `client`, `staff` and `services` loaded.
 *
 * @mixin Booking
 */
class AgendaBookingResource extends JsonResource
{
    public static $wrap = null;

    public function __construct(Booking $booking, private readonly string $timezone)
    {
        parent::__construct($booking);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $now = CarbonImmutable::now();
        $status = $this->effectiveStatus($now);
        $start = $this->starts_at->setTimezone($this->timezone);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'time' => $start->format('H:i'),
            'endTime' => $this->ends_at->setTimezone($this->timezone)->format('H:i'),
            'status' => $status->value,
            'statusLabel' => $status->label(),
            'isVip' => $this->is_vip,
            'client' => [
                'name' => $this->client->name,
                'phone' => $this->client->phone,
                'whatsappUrl' => WhatsApp::link($this->client->phone, "Hola {$this->client->name}, te escribimos por tu cita de las {$start->format('g:i a')}."),
                'noShows' => $this->client->no_show_count,
            ],
            'staffName' => $this->staff->name,
            'services' => $this->services->pluck('name')->values(),
            'price' => Money::toAmount($this->price_cents),
            'deposit' => $this->deposit_cents === 0 ? null : [
                'amount' => Money::toAmount($this->deposit_cents),
                'isPaid' => $this->deposit_paid_at !== null,
            ],
            'note' => $this->client_note,
            'actions' => $this->actions($request, $status, $now),
        ];
    }

    /**
     * Only what the state machine and the viewer's permissions allow; the
     * server checks both again when the button is pressed.
     *
     * @return list<string>
     */
    private function actions(Request $request, BookingStatus $status, CarbonImmutable $now): array
    {
        $hasStarted = $this->starts_at->lessThanOrEqualTo($now);
        /** @var Booking $booking */
        $booking = $this->resource;

        return array_keys(array_filter([
            'confirm' => $this->status === BookingStatus::Pending && ($request->user()?->can('confirmDeposit', $booking) ?? false),
            'start' => $status === BookingStatus::Confirmed
                && $this->starts_at->setTimezone($this->timezone)->isSameDay($now->setTimezone($this->timezone)),
            'complete' => $status === BookingStatus::InService,
            'noShow' => $status === BookingStatus::Confirmed && $hasStarted,
            'cancel' => in_array($status, [BookingStatus::Pending, BookingStatus::Confirmed], true),
        ]));
    }
}
