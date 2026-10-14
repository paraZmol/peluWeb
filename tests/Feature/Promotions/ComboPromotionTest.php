<?php

use App\Actions\Salon\CreateSalon;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\PromotionMechanic;
use App\Enums\PromotionStatus;
use App\Models\Booking;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Step 6.7 (d): "Combo a precio cerrado". Estudio Luz: Corte S/ 35, Barba
| S/ 20, Rasurado S/ 12; Ana does all of them, Wednesday to Friday, 9 to 18.
| "Now" is Wednesday 2026-10-07 08:00 in Lima.
*/

/**
 * @param  array<string, mixed>  $with
 */
function comboSave(array $with = []): TestResponse
{
    return test()->actingAs(test()->owner)->post(route('promotions.store'), [
        'card' => 'combo',
        'mechanic' => 'combo',
        'services' => [test()->cut->id, test()->beard->id],
        'combo_price' => 45,
        'starts_on' => '2026-10-07',
        'ends_on' => '2026-10-31',
        ...$with,
    ]);
}

/**
 * @param  list<array<string, int>>  $services
 */
function comboBook(array $services, string $time = '10:00'): Booking
{
    test()->actingAs(test()->maria)->post(route('salons.booking.store', test()->salon), [
        'services' => $services,
        'start' => CarbonImmutable::parse("2026-10-07 {$time}", 'America/Lima')->getTimestamp(),
    ])->assertSessionHasNoErrors();

    return Booking::withoutGlobalScope('salon')->with('services')->latest('id')->firstOrFail();
}

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Estudio Luz', BusinessType::Unisex, Clientele::Everyone);
    $this->salon->forceFill(['timezone' => 'America/Lima', 'deposit_percent' => 0, 'min_lead_minutes' => 0])->save();
    $this->staff = Staff::factory()->for($this->salon)->working([3, 4, 5], '09:00', '18:00')->create(['name' => 'Ana']);
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'price_cents' => 3500, 'active_minutes' => 30, 'position' => 1]);
    $this->beard = Service::factory()->for($this->salon)->create(['name' => 'Barba', 'price_cents' => 2000, 'active_minutes' => 20, 'position' => 2]);
    $this->shave = Service::factory()->for($this->salon)->create(['name' => 'Rasurado', 'price_cents' => 1200, 'active_minutes' => 15, 'position' => 3]);
    $this->staff->services()->attach([$this->cut->id, $this->beard->id, $this->shave->id]);
    $this->maria = User::factory()->create();
});

it('saves a combo as its saving, titled with its services and price', function () {
    comboSave()->assertSessionHasNoErrors();
    $promotion = Promotion::withoutGlobalScope('salon')->sole();

    expect($promotion->mechanic)->toBe(PromotionMechanic::Combo)
        ->and($promotion->amount_cents)->toBe(1000)
        ->and($promotion->name)->toBe('Combo Corte + Barba')
        ->and($promotion->writtenTitle())->toBe('Corte + Barba por S/ 45');
});

it('refuses a combo outside 5 % to 50 % off, of one or four services, or on another card', function (array $with, string $field) {
    comboSave($with)->assertSessionHasErrors($field);
})->with([
    'barely cheaper' => [['combo_price' => 54], 'combo_price'],
    'half price or less' => [['combo_price' => 27], 'combo_price'],
    'one service' => [fn () => ['services' => [test()->cut->id]], 'services'],
    'four services' => [fn () => ['services' => [test()->cut->id, test()->beard->id, test()->shave->id, Service::factory()->for(test()->salon)->create()->id]], 'services'],
    'a discount with a combo price' => [['card' => 'discount', 'mechanic' => 'discount', 'percent' => 20], 'combo_price'],
    'a combo from the discount card' => [['card' => 'discount', 'combo_price' => null, 'percent' => 20], 'mechanic'],
]);

it('takes the saving off the booking with every service of the combo, shared by price', function () {
    comboSave(['services' => [$this->cut->id, $this->beard->id]]);
    Promotion::withoutGlobalScope('salon')->sole()->forceFill(['status' => PromotionStatus::Published])->save();

    $booking = comboBook([['id' => $this->cut->id], ['id' => $this->beard->id], ['id' => $this->shave->id]]);

    // S/ 10 over S/ 55: Corte takes 636, Barba the remaining 364.
    expect($booking->price_cents)->toBe(3500 + 2000 + 1200 - 1000)
        ->and($booking->services->pluck('price_cents')->all())->toBe([3500 - 636, 2000 - 364, 1200])
        ->and($booking->services->pluck('list_price_cents')->all())->toBe([3500, 2000, 1200]);
});

it('announces a combo at the cheapest price someone can book it for, and not one nobody does whole', function () {
    $this->staff->services()->updateExistingPivot($this->cut->id, ['price_cents' => 4000]);
    $leo = Staff::factory()->for($this->salon)->working([3, 4, 5], '09:00', '18:00')->create(['name' => 'Leo']);
    $leo->services()->attach([$this->cut->id => ['price_cents' => 3000]]);

    // Leo's cheaper Corte does not count: he does no Barba. Ana's S/ 40 + S/ 20 does.
    comboSave(['combo_price' => 50])->assertSessionHasNoErrors();
    expect(Promotion::withoutGlobalScope('salon')->sole()->writtenTitle())->toBe('Corte + Barba por S/ 50');

    $this->staff->services()->detach($this->beard->id);
    comboSave(['combo_price' => 50])->assertSessionHasErrors('services');
});

it('charges the normal price without every service of the combo', function () {
    comboSave();
    Promotion::withoutGlobalScope('salon')->sole()->forceFill(['status' => PromotionStatus::Published])->save();

    expect(comboBook([['id' => $this->cut->id]])->price_cents)->toBe(3500);
});

it('keeps the same saving with a dearer option, and says "desde"', function () {
    $this->cut->variants()->create(['label' => 'Corto', 'price_cents' => 3500, 'extra_minutes' => 0, 'position' => 0]);
    $long = $this->cut->variants()->create(['label' => 'Largo', 'price_cents' => 4500, 'extra_minutes' => 0, 'position' => 1]);
    comboSave()->assertSessionHasNoErrors();
    $promotion = Promotion::withoutGlobalScope('salon')->sole();
    $promotion->forceFill(['status' => PromotionStatus::Published])->save();

    expect($promotion->writtenTitle())->toBe('Corte + Barba desde S/ 45');
    $this->get(route('salons.show', $this->salon))->assertInertia(fn (Assert $page) => $page
        ->where('promotions.items.0.combo', ['price' => '45.00', 'normal' => '55.00', 'saves' => '10.00', 'isFrom' => true]));

    $booking = comboBook([['id' => $this->cut->id, 'variant' => $long->id], ['id' => $this->beard->id]]);

    expect($booking->price_cents)->toBe(4500 + 2000 - 1000);
});
