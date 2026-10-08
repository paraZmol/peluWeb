<?php

namespace App\Http\Resources;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Salon;
use App\Support\Images\ImageStore;
use App\Support\Money;
use App\Support\WhatsApp;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A booking as its client sees it. Dates and times are sent already on the
 * salon's clock, so the browser's timezone never moves an appointment.
 * Expects `salon`, `staff` and `services` loaded.
 *
 * @mixin Booking
 */
class ClientBookingResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Salon $salon */
        $salon = $this->salon;
        $now = CarbonImmutable::now();
        $start = $this->starts_at->setTimezone($salon->timezone);
        $status = $this->effectiveStatus($now);
        $isUpcoming = $this->starts_at->greaterThan($now);
        $isOpen = $isUpcoming && in_array($status, [BookingStatus::Pending, BookingStatus::Confirmed], true);
        $hasNotice = $now->addHours($salon->cancel_window_hours)->lessThanOrEqualTo($this->starts_at);

        return [
            'code' => $this->code,
            'status' => $status->value,
            'statusLabel' => $status->label(),
            'date' => $start->format('Y-m-d'),
            'time' => $start->format('H:i'),
            'endTime' => $this->ends_at->setTimezone($salon->timezone)->format('H:i'),
            'isUpcoming' => $isUpcoming,
            'isVip' => $this->is_vip,
            'staffName' => $this->staff->name,
            'services' => $this->services->map(fn (BookingService $service) => [
                'name' => $service->name,
                'price' => Money::toAmount($service->price_cents),
            ])->values(),
            'price' => Money::toAmount($this->price_cents),
            // A note the team wrote is theirs; the client sees only their own.
            'note' => $this->source === BookingSource::Client ? $this->client_note : null,
            'deposit' => $this->deposit_cents === 0 ? null : [
                'amount' => Money::toAmount($this->deposit_cents),
                'isPaid' => $this->deposit_paid_at !== null,
                'expiresAt' => $status === BookingStatus::Pending ? $this->hold_expires_at?->toIso8601String() : null,
                'instructions' => $status === BookingStatus::Pending ? $salon->payment_instructions : null,
                'whatsappUrl' => $status === BookingStatus::Pending ? WhatsApp::link($salon->phone, $this->depositMessage($start)) : null,
                'isRefundable' => $this->deposit_refundable,
            ],
            'referencePhotoUrl' => app(ImageStore::class)->url($this->reference_photo_path),
            'canAttachPhoto' => $isOpen,
            'canCancel' => $isOpen,
            // Moving needs the same notice the salon asks for a cancellation.
            'canMove' => $isOpen && $hasNotice && $salon->isWritable() && $salon->accepts_online_bookings,
            // Cancelling now would forfeit the deposit already paid.
            'cancelForfeitsDeposit' => $this->deposit_paid_at !== null && ! $hasNotice,
            'cancelWindowHours' => $salon->cancel_window_hours,
            'salon' => [
                'name' => $salon->name,
                'slug' => $salon->slug,
                'brand' => $salon->brandTokens(),
                'address' => collect([$salon->address, $salon->district, $salon->city])->filter()->join(', ') ?: null,
                'directionsUrl' => $salon->hasLocation()
                    ? 'https://www.google.com/maps/dir/?api=1&destination='.$salon->latitude.','.$salon->longitude
                    : null,
                'whatsappUrl' => WhatsApp::link($salon->phone, "Hola, tengo una consulta sobre mi reserva {$this->code}."),
            ],
        ];
    }

    /**
     * The message that goes with the proof of payment. Built here so the
     * amount and the reference are never typed by hand.
     */
    private function depositMessage(CarbonImmutable $start): string
    {
        $services = $this->services->pluck('name')->join(', ');
        $amount = Money::toAmount($this->deposit_cents);

        return "Hola, soy {$this->client->name}. Reservé {$services} para el {$start->format('d/m/Y')} a las {$start->format('g:i a')} "
            ."(reserva {$this->code}). Les envío el comprobante de la seña de S/ {$amount}.";
    }
}
