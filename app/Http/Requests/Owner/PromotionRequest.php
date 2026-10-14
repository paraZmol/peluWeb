<?php

namespace App\Http\Requests\Owner;

use App\Concerns\ComboPromotionRules;
use App\Concerns\SalonValidationRules;
use App\Data\Promotions\PromotionDraft;
use App\Enums\PromotionMechanic;
use App\Models\Promotion;
use App\Models\Service;
use App\Support\CurrentSalon;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PromotionRequest extends FormRequest
{
    use ComboPromotionRules, SalonValidationRules;

    /** The cards a promotion starts from; they only shape the form. */
    public const array CARDS = ['discount', 'gift', 'quiet_hours', 'season', 'first_visit', 'combo', 'pair'];

    /** Cards that can only be a discount. */
    private const array DISCOUNT_CARDS = ['quiet_hours', 'first_visit'];

    public const int MIN_PERCENT = 5;

    public const int MAX_PERCENT = 70;

    /** No line breaks, control or text-direction characters. */
    private const string ONE_LINE = 'not_regex:/[\p{Cc}\p{Cf}]/u';

    private const string NO_LINKS = 'not_regex:/(:\/\/|www\.)/i';

    public function authorize(): bool
    {
        $salon = app(CurrentSalon::class)->get();

        return $salon !== null && ($this->user()?->can('managePromotions', $salon) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Another salon's service is "not found", like its URLs.
        $ownService = fn () => Rule::exists('services', 'id')
            ->where('salon_id', app(CurrentSalon::class)->get()?->id)
            ->where('is_active', true)
            ->whereNull('deleted_at');
        $isPair = $this->input('card') === 'pair';
        // The second one free only fills hours that would stay empty.
        $isQuietHours = $this->input('card') === 'quiet_hours' || ($isPair && (int) $this->input('percent') === self::PAIR_FREE);
        $isGift = $this->input('mechanic') === PromotionMechanic::Gift->value;
        $isCombo = $this->input('card') === 'combo';
        $noDiscount = $isGift || $isCombo;

        return [
            'card' => ['required', Rule::in(self::CARDS)],
            'mechanic' => ['required', Rule::enum(PromotionMechanic::class), Rule::in($this->mechanicsFor($this->string('card')->toString()))],
            'services' => ['required', 'array', 'list', $isCombo ? 'min:'.self::COMBO_MIN_SERVICES : 'min:1', $isCombo ? 'max:'.self::COMBO_MAX_SERVICES : 'max:100'],
            'services.*' => ['integer', 'distinct', $ownService()],
            'percent' => $isPair ? ['required', 'integer', Rule::in([self::PAIR_HALF, self::PAIR_FREE])]
                : ['nullable', 'integer', 'min:'.self::MIN_PERCENT, 'max:'.self::MAX_PERCENT, $noDiscount ? 'prohibited' : 'required_without:amount', 'prohibits:amount'],
            'amount' => ['nullable', 'numeric', 'min:1', 'max:9999', 'decimal:0,2', $noDiscount || $isPair ? 'prohibited' : 'required_without:percent'],
            'combo_price' => [$isCombo ? 'required' : 'prohibited', 'nullable', 'numeric', 'min:1', 'max:99999', 'decimal:0,2'],
            'gift_service_id' => [$isGift ? 'required' : 'prohibited', 'nullable', 'integer', $ownService()],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'weekdays' => [$isQuietHours ? 'required' : 'nullable', 'array', 'list', 'min:1', 'max:7'],
            'weekdays.*' => ['integer', 'distinct', 'between:1,7'],
            'from_time' => [$isQuietHours ? 'required' : 'nullable', 'date_format:H:i', 'required_with:to_time'],
            'to_time' => [$isQuietHours ? 'required' : 'nullable', 'date_format:H:i', 'required_with:from_time', 'different:from_time'],
            'quota' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'once_per_client' => ['boolean'],
            // One line of plain words: it goes into mails, shared texts and bookings.
            'name' => ['nullable', 'string', 'max:120', self::PLAIN_TEXT, self::ONE_LINE, self::NO_LINKS],
            'overline' => ['nullable', 'string', 'max:60', self::PLAIN_TEXT, self::ONE_LINE, self::NO_LINKS],
        ];
    }

    /**
     * What a single field cannot say: how long it runs, and that the
     * amount and the gift fit the services they go with.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $salon = app(CurrentSalon::class)->get();
            $start = CarbonImmutable::parse($this->string('starts_on')->toString());
            $end = CarbonImmutable::parse($this->string('ends_on')->toString());

            if ($start->diffInDays($end) > Promotion::MAX_DAYS) {
                $validator->errors()->add('ends_on', 'Una promoción dura como máximo '.Promotion::MAX_DAYS.' días.');
            }

            if ($salon !== null && $end->toDateString() < $salon->localNow()->toDateString()) {
                $validator->errors()->add('ends_on', 'La fecha de fin ya pasó.');
            }

            $services = Service::query()->whereKey($this->input('services'))->with('variants')->get();

            if ($this->filled('amount') && Money::toCents($this->input('amount')) >= $services->min('price_cents')) {
                $validator->errors()->add('amount', 'El monto debe ser menor que el precio del servicio más barato de la promoción.');
            }

            $this->checkGift($validator);

            if ($this->input('card') === 'pair') {
                $this->checkPair($validator, $services);
            }

            if ($this->filled('combo_price') && ! $this->keepsBookedSaving($this->amountCents())) {
                $this->checkCombo($validator, $services, Money::toCents($this->input('combo_price')));
            }
        }];
    }

    /**
     * What each card can be: most only a discount, a combo only a combo.
     *
     * @return list<string>
     */
    private function mechanicsFor(string $card): array
    {
        return match (true) {
            $card === 'combo' => [PromotionMechanic::Combo->value],
            $card === 'pair' => [PromotionMechanic::Pair->value],
            in_array($card, self::DISCOUNT_CARDS, true) => [PromotionMechanic::Discount->value],
            default => [PromotionMechanic::Discount->value, PromotionMechanic::Gift->value],
        };
    }

    /**
     * The gift has one fixed price: no options, no "needs an evaluation".
     */
    private function checkGift(Validator $validator): void
    {
        if (! $this->filled('gift_service_id')) {
            return;
        }

        $gift = Service::query()->with('variants')->find($this->integer('gift_service_id'));

        if ($gift === null || $gift->variants->isNotEmpty() || $gift->requires_evaluation) {
            $validator->errors()->add('gift_service_id', 'El regalo debe ser un servicio con un solo precio, sin opciones ni evaluación previa.');
        }

        if (in_array($this->integer('gift_service_id'), array_map(intval(...), (array) $this->input('services')), true)) {
            $validator->errors()->add('gift_service_id', 'El regalo no puede ser uno de los servicios que lo dan.');
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'services' => 'servicios', 'percent' => 'porcentaje', 'amount' => 'monto', 'combo_price' => 'precio del combo', 'gift_service_id' => 'servicio de regalo',
            'starts_on' => 'fecha de inicio', 'ends_on' => 'fecha de fin', 'weekdays' => 'días', 'from_time' => 'hora de inicio',
            'to_time' => 'hora de fin', 'quota' => 'cupos', 'name' => 'título', 'overline' => 'ocasión',
        ];
    }

    /**
     * The amount off; for a combo, its saving against the base prices
     * today, which is what it keeps saving whatever is chosen.
     */
    private function amountCents(): ?int
    {
        if ($this->filled('combo_price')) {
            $services = Service::query()->whereKey($this->input('services'))->with('variants')->get();

            return $this->comboNormalCents($services) - Money::toCents($this->input('combo_price'));
        }

        return $this->filled('amount') ? Money::toCents($this->input('amount')) : null;
    }

    public function draft(): PromotionDraft
    {
        $minutes = fn (string $key): ?int => $this->filled($key)
            ? (int) substr($this->string($key)->toString(), 0, 2) * 60 + (int) substr($this->string($key)->toString(), 3, 2)
            : null;

        return new PromotionDraft(
            card: $this->string('card')->toString(),
            mechanic: PromotionMechanic::from($this->string('mechanic')->toString()),
            serviceIds: array_values(array_map(intval(...), (array) $this->validated('services'))),
            startsOn: $this->string('starts_on')->toString(),
            endsOn: $this->string('ends_on')->toString(),
            percent: $this->filled('percent') ? $this->integer('percent') : null,
            amountCents: $this->amountCents(),
            giftServiceId: $this->filled('gift_service_id') ? $this->integer('gift_service_id') : null,
            weekdays: $this->filled('weekdays') ? array_values(collect((array) $this->validated('weekdays'))->map(intval(...))->sort()->all()) : null,
            fromMinute: $minutes('from_time'),
            toMinute: $minutes('to_time'),
            quota: $this->filled('quota') ? $this->integer('quota') : null,
            // A first visit is one per client already: the flag would only count it twice.
            oncePerClient: $this->string('card')->toString() !== 'first_visit' && $this->boolean('once_per_client'),
            name: $this->filled('name') ? $this->string('name')->trim()->toString() : null,
            overline: $this->filled('overline') ? $this->string('overline')->trim()->toString() : null,
            newClientsOnly: $this->string('card')->toString() === 'first_visit',
        );
    }
}
