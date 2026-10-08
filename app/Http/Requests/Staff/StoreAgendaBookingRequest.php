<?php

namespace App\Http\Requests\Staff;

use App\Actions\Booking\ResolveSalonClient;
use App\Concerns\ParsesBookingSelection;
use App\Concerns\ParsesBookingTime;
use App\Concerns\SalonValidationRules;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\Staff;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * A booking the team makes for a client: someone already in the salon's
 * list, or a new person who needs no account.
 */
class StoreAgendaBookingRequest extends FormRequest
{
    use ParsesBookingSelection, ParsesBookingTime, SalonValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->selectionRules(),
            ...$this->startRules(),
            'client_id' => ['nullable', 'integer'],
            'client_name' => ['nullable', 'required_without:client_id', 'string', 'min:2', 'max:'.SalonClient::NAME_MAX_LENGTH, self::PLAIN_TEXT, 'not_regex:/[\p{Cc}\p{Cf}]/u'],
            'client_phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
            'note' => ['nullable', 'string', 'max:255', self::PLAIN_TEXT],
        ];
    }

    /**
     * The chosen client of this salon, or the record for the name and
     * phone typed in. `client_id` is looked up inside the salon only and,
     * with $onlyAttendedBy, among that professional's own clients: the same
     * people their search shows.
     */
    public function clientIn(Salon $salon, ResolveSalonClient $clients, ?Staff $onlyAttendedBy = null): SalonClient
    {
        if ($this->filled('client_id')) {
            return SalonClient::withoutGlobalScope('salon')
                ->where('salon_id', $salon->id)
                ->when($onlyAttendedBy !== null, fn ($query) => $query->whereHas('bookings', fn ($bookings) => $bookings->withoutGlobalScope('salon')->where('staff_id', $onlyAttendedBy?->id)))
                ->find($this->integer('client_id'))
                ?? throw ValidationException::withMessages(['client_id' => 'Ese cliente ya no está en la lista. Búscalo de nuevo.']);
        }

        return $clients->walkIn($salon, $this->string('client_name')->trim()->toString(), $this->validated('client_phone'), $onlyAttendedBy);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'services' => 'servicios',
            'start' => 'horario',
            'note' => 'nota',
            'client_name' => 'nombre',
            'client_phone' => 'teléfono',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_name.required_without' => 'Escribe el nombre de tu cliente.',
            'client_phone.regex' => 'Escribe el teléfono solo con números, por ejemplo 987654321.',
        ];
    }
}
