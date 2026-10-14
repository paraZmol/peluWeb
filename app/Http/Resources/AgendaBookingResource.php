<?php

namespace App\Http\Resources;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingService;
use App\Support\Images\ImageStore;
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
        /** @var Booking $booking */
        $booking = $this->resource;
        $start = $this->starts_at->setTimezone($this->timezone);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'date' => $start->format('Y-m-d'),
            'time' => $start->format('H:i'),
            'endTime' => $this->ends_at->setTimezone($this->timezone)->format('H:i'),
            'startsAt' => $this->starts_at->toIso8601String(),
            'endsAt' => $this->ends_at->toIso8601String(),
            'status' => $status->value,
            'statusLabel' => $status->label(),
            'isVip' => $this->is_vip,
            'client' => [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'phone' => $this->client->phone,
                'whatsappUrl' => WhatsApp::link($this->client->phone, "Hola {$this->client->name}, te escribimos por tu cita de las {$start->format('g:i a')}."),
                'noShows' => $this->client->no_show_count,
            ],
            'staffId' => $this->staff_id,
            'staffName' => $this->staff->name,
            'services' => $this->services->map(fn (BookingService $service): string => $service->displayName())->values(),
            // "Ven con alguien": who comes along; the team may take them off if only one comes.
            'companion' => $this->services->count() === 2 ? $this->services->firstWhere('companion_name', '!==', null)?->companion_name : null,
            'price' => Money::toAmount($this->price_cents),
            // The promotion that set its price, as it was called then.
            'promotion' => $this->promotion_cents === 0 ? null : [
                'name' => $this->promotion_name,
                'saved' => Money::toAmount($this->promotion_cents),
            ],
            'deposit' => $this->deposit_cents === 0 ? null : [
                'amount' => Money::toAmount($this->deposit_cents),
                'isPaid' => $this->deposit_paid_at !== null,
                'isRefunded' => $this->deposit_refunded_at !== null,
                // Null until it is cancelled: false means the client lost it.
                'isRefundable' => $this->deposit_refundable,
            ],
            // What is left to collect when the visit ends, before any discount.
            'balance' => Money::toAmount($this->price_cents - $booking->depositPaidCents()),
            'depositPaid' => Money::toAmount($booking->depositPaidCents()),
            'canDiscount' => $request->user()?->can('confirmDeposit', $booking) ?? false,
            'charged' => $this->charged_cents === null ? null : [
                'total' => Money::toAmount($this->charged_cents),
                'discount' => Money::toAmount($this->discount_cents),
            ],
            'note' => $this->client_note,
            'referencePhotoUrl' => app(ImageStore::class)->url($this->reference_photo_path),
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
        $isOpen = in_array($status, [BookingStatus::Pending, BookingStatus::Confirmed], true);

        return array_keys(array_filter([
            'confirm' => $this->status === BookingStatus::Pending && ($request->user()?->can('confirmDeposit', $booking) ?? false),
            'start' => $status === BookingStatus::Confirmed
                && $this->starts_at->setTimezone($this->timezone)->isSameDay($now->setTimezone($this->timezone)),
            'complete' => $status === BookingStatus::InService,
            'noShow' => $status === BookingStatus::Confirmed && $hasStarted,
            'move' => $isOpen,
            'cancel' => $isOpen,
            'refundDeposit' => $booking->canRefundDeposit() && ($request->user()?->can('confirmDeposit', $booking) ?? false),
        ]));
    }
}
