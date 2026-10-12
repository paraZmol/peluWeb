<?php

namespace App\Actions\Salon;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\ClientConsent;
use App\Models\ClientPhoto;
use App\Models\Review;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Everything the platform keeps that is the person's own, as a file they
 * can take with them: their account, their bookings, what each salon
 * keeps under their authorization and their reviews. What a salon wrote
 * about them on its own (preferences, formula) is asked for at the salon.
 * The keys are in Spanish: a person reads this file, not a program.
 */
class ExportMyData
{
    public function __construct(private readonly MyClientFiles $files) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user): array
    {
        $clients = $this->files->query($user)->orderBy('id')->get();
        $salons = Salon::withTrashed()->whereKey($clients->pluck('salon_id')->unique())->get()->keyBy('id');

        return [
            'generado' => now()->toIso8601String(),
            'cuenta' => [
                'nombre' => $user->name,
                'correo' => $user->email,
                'telefono' => $user->phone,
                'correo_verificado' => $user->email_verified_at?->toIso8601String(),
                'recordatorios_por_correo' => $user->booking_reminders,
                'creada' => $user->created_at?->toIso8601String(),
            ],
            'salones' => $clients->map(fn (SalonClient $client): array => $this->file($client, $salons->get($client->salon_id)))->values()->all(),
            'nota' => 'Las preferencias y la fórmula que cada salón anota por su cuenta se piden en el salón.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function file(SalonClient $client, ?Salon $salon): array
    {
        $timezone = $salon->timezone ?? 'UTC';

        return [
            'salon' => $salon?->name,
            'salon_cerrado' => $salon?->trashed() ?? true,
            'nombre_en_el_salon' => $client->name,
            'telefono_en_el_salon' => $client->phone,
            'es_vip' => $client->is_vip,
            'alergias_y_cuidado' => $client->care_notes,
            'fotos_guardadas' => ClientPhoto::withoutGlobalScope('salon')->where('salon_client_id', $client->id)->count(),
            'autorizaciones' => ClientConsent::withoutGlobalScope('salon')->where('salon_client_id', $client->id)->orderBy('id')->get()
                ->map(fn (ClientConsent $consent): array => [
                    'fecha' => $consent->created_at->setTimezone($timezone)->toIso8601String(),
                    'sobre' => $consent->kind->value,
                    'accion' => $consent->action->value,
                    'via' => $consent->method->value,
                    'version_del_texto' => $consent->text_version,
                ])->all(),
            'reservas' => $this->bookings($client, $timezone),
            'resenas' => Review::withoutGlobalScope('salon')->where('salon_client_id', $client->id)->whereNull('withdrawn_at')->orderBy('id')->get()
                ->map(fn (Review $review): array => [
                    'fecha' => $review->created_at?->setTimezone($timezone)->toIso8601String(),
                    'estrellas' => $review->rating,
                    'comentario' => $review->comment,
                    'firmada_como' => $review->author_name,
                    'respuesta_del_salon' => $review->hidden_at === null ? $review->reply : null,
                ])->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bookings(SalonClient $client, string $timezone): array
    {
        /** @var Collection<int, Booking> $bookings */
        $bookings = Booking::withoutGlobalScope('salon')->where('salon_client_id', $client->id)
            ->with(['services', 'staff' => fn ($staff) => $staff->withoutGlobalScope('salon')])
            ->orderBy('starts_at')->get();

        return $bookings->map(fn (Booking $booking): array => [
            'codigo' => $booking->code,
            'fecha' => $booking->starts_at->setTimezone($timezone)->toIso8601String(),
            'estado' => $booking->effectiveStatus()->label(),
            'con' => $booking->staff->name,
            'servicios' => $booking->services->map(fn (BookingService $service): string => $service->name)->all(),
            'precio' => Money::toAmount($booking->price_cents),
            'sena' => Money::toAmount($booking->deposit_cents),
            'cobrado' => $booking->charged_cents === null ? null : Money::toAmount($booking->charged_cents),
            'tu_nota' => $booking->client_note,
        ])->all();
    }
}
