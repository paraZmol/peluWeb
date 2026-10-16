<?php

namespace Database\Factories;

use App\Enums\ComplaintItemType;
use App\Enums\ComplaintKind;
use App\Enums\IdentityDocument;
use App\Models\Complaint;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Sheets for tests. Real ones are numbered by FileComplaint; these take a
 * number of their own.
 *
 * @extends Factory<Complaint>
 */
class ComplaintFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'year' => (int) CarbonImmutable::now('America/Lima')->format('Y'),
            'number' => fake()->unique()->numberBetween(1, 999999),
            'kind' => ComplaintKind::Claim,
            'provider_name' => 'Empresa de prueba S.A.C.',
            'provider_ruc' => '20000000001',
            'provider_address' => 'Av. de Prueba 123, Lima',
            'consumer_name' => fake()->name(),
            'consumer_address' => fake()->streetAddress(),
            'document_type' => IdentityDocument::Dni,
            'document_number' => fake()->numerify('########'),
            'phone' => fake()->numerify('9########'),
            'email' => fake()->unique()->safeEmail(),
            'item_type' => ComplaintItemType::Service,
            'item_description' => 'Reservas en línea',
            'detail' => 'La reserva no aparecía en mi cuenta después de confirmarla.',
            'request' => 'Que revisen mi cuenta.',
            'consented_at' => CarbonImmutable::now(),
            'due_on' => CarbonImmutable::now('America/Lima')->addWeeks(3)->format('Y-m-d'),
        ];
    }

    public function dueOn(CarbonImmutable $date): static
    {
        return $this->state(['due_on' => $date->format('Y-m-d')]);
    }

    public function answered(?User $by = null): static
    {
        return $this->state(fn () => [
            'response' => 'Revisamos tu cuenta y la reserva ya aparece.',
            'responded_at' => CarbonImmutable::now(),
            'responded_by' => $by->id ?? User::factory(),
        ]);
    }
}
