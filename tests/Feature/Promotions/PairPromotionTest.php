<?php

use App\Actions\Salon\CreateSalon;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\PromotionMechanic;
use App\Enums\PromotionStatus;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/*
| Step 6.7 (d): "Ven con alguien" (2x1). Estudio Luz: Corte S/ 35 (corto)
| or S/ 45 (largo), Barba S/ 20; Ana does both, Wednesday to Friday, 9 to
| 18. "Now" is Wednesday 2026-10-07 08:00 in Lima.
*/

/**
 * @param  array<string, mixed>  $with
 */
function pairSave(array $with = []): TestResponse
{
    return test()->actingAs(test()->owner)->post(route('promotions.store'), [
        'card' => 'pair',
        'mechanic' => 'pair',
        'services' => [test()->cut->id],
        'percent' => 50,
        'starts_on' => '2026-10-07',
        'ends_on' => '2026-10-31',
        ...$with,
    ]);
}

function pairPublish(): Promotion
{
    $promotion = Promotion::withoutGlobalScope('salon')->latest('id')->firstOrFail();
    $promotion->forceFill(['status' => PromotionStatus::Published])->save();

    return $promotion;
}

/**
 * @param  list<array<string, mixed>>  $services
 */
function pairBook(array $services, string $time = '10:00'): TestResponse
{
    return test()->actingAs(test()->maria)->post(route('salons.booking.store', test()->salon), [
        'services' => $services,
        'start' => CarbonImmutable::parse("2026-10-07 {$time}", 'America/Lima')->getTimestamp(),
    ]);
}

function pairLatest(): Booking
{
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
    $this->short = $this->cut->variants()->create(['label' => 'Corto', 'price_cents' => 3500, 'extra_minutes' => 0, 'position' => 0]);
    $this->long = $this->cut->variants()->create(['label' => 'Largo', 'price_cents' => 4500, 'extra_minutes' => 15, 'position' => 1]);
    $this->beard = Service::factory()->for($this->salon)->create(['name' => 'Barba', 'price_cents' => 2000, 'active_minutes' => 20, 'position' => 2]);
    $this->staff->services()->attach([$this->cut->id, $this->beard->id]);
    $this->maria = User::factory()->create();
});

it('saves the second at half price anytime, and the second free only with days and hours', function () {
    pairSave()->assertSessionHasNoErrors();
    expect(Promotion::withoutGlobalScope('salon')->sole())
        ->mechanic->toBe(PromotionMechanic::Pair)
        ->name->toBe('Segundo Corte a mitad de precio');

    pairSave(['percent' => 100])->assertSessionHasErrors(['weekdays', 'from_time']);
    pairSave(['percent' => 100, 'weekdays' => [3], 'from_time' => '10:00', 'to_time' => '16:00'])->assertSessionHasNoErrors();
    expect(Promotion::withoutGlobalScope('salon')->latest('id')->first()->name)->toBe('2x1 en Corte');

    pairSave(['percent' => 30])->assertSessionHasErrors('percent');
});

it('books two people for the same service, the cheaper one at half price, with who comes along named', function () {
    pairSave();
    pairPublish();

    pairBook([
        ['id' => $this->cut->id, 'variant' => $this->long->id],
        ['id' => $this->cut->id, 'variant' => $this->short->id, 'companion' => 'Lucía'],
    ])->assertSessionHasNoErrors();
    $booking = pairLatest();

    expect($booking->price_cents)->toBe(4500 + 1750)
        ->and($booking->promotion_cents)->toBe(1750)
        ->and($booking->services->pluck('price_cents')->all())->toBe([4500, 1750])
        ->and($booking->services[1]->displayName())->toBe('Corte · Corto · para Lucía');
});

it('refuses who comes along with another service, a third line, or a repeat without them', function (Closure $services) {
    pairSave();
    pairPublish();

    pairBook($services())->assertInertiaFlash('toast.type', 'error');

    expect(Booking::withoutGlobalScope('salon')->count())->toBe(0);
})->with([
    'another service' => [fn () => [['id' => test()->cut->id, 'variant' => test()->short->id], ['id' => test()->beard->id, 'companion' => 'Lucía']]],
    'a third line' => [fn () => [['id' => test()->beard->id], ['id' => test()->cut->id, 'variant' => test()->short->id], ['id' => test()->cut->id, 'variant' => test()->short->id, 'companion' => 'Lucía']]],
    'a repeat without them' => [fn () => [['id' => test()->cut->id, 'variant' => test()->short->id], ['id' => test()->cut->id, 'variant' => test()->short->id]]],
    'them alone' => [fn () => [['id' => test()->cut->id, 'variant' => test()->short->id, 'companion' => 'Lucía']]],
]);

it('gives the second free only in its hours', function () {
    pairSave(['percent' => 100, 'weekdays' => [3], 'from_time' => '10:00', 'to_time' => '12:00']);
    pairPublish();
    $two = [['id' => $this->cut->id, 'variant' => $this->short->id], ['id' => $this->cut->id, 'variant' => $this->short->id, 'companion' => 'Lucía']];

    pairBook($two, '10:00')->assertSessionHasNoErrors();
    expect(pairLatest()->price_cents)->toBe(3500);

    pairBook($two, '14:00')->assertSessionHasNoErrors();
    expect(pairLatest()->price_cents)->toBe(7000);
});

it('charges the normal price when only one of them came, and says who did it', function () {
    pairSave();
    pairPublish();
    pairBook([['id' => $this->cut->id, 'variant' => $this->short->id], ['id' => $this->cut->id, 'variant' => $this->long->id, 'companion' => 'Lucía']]);
    $booking = pairLatest();

    $this->actingAs($this->owner)->post(route('agenda.bookings.drop-companion', $booking), ['came' => 'booker'])->assertSessionHasNoErrors();
    $booking = pairLatest();

    expect($booking->services)->toHaveCount(1)
        ->and($booking->price_cents)->toBe(3500)
        ->and($booking->promotion_id)->toBeNull()
        ->and($booking->promotion_cents)->toBe(0)
        // The note keeps who did it, not the name of who was coming along.
        ->and(BookingEvent::query()->where('booking_id', $booking->id)->latest('id')->first())
        ->changed_by->toBe($this->owner->id)
        ->note->not->toContain('Lucía');

    // Nobody left to take off.
    $this->actingAs($this->owner)->post(route('agenda.bookings.drop-companion', $booking), ['came' => 'booker'])->assertInertiaFlash('toast.type', 'error');
});

it('charges who came their own service, when it is the one coming along', function () {
    pairSave();
    pairPublish();
    // María booked the long cut; Lucía, coming along, the short one.
    pairBook([['id' => $this->cut->id, 'variant' => $this->long->id], ['id' => $this->cut->id, 'variant' => $this->short->id, 'companion' => 'Lucía']]);

    $this->actingAs($this->owner)->post(route('agenda.bookings.drop-companion', pairLatest()), ['came' => 'companion'])->assertSessionHasNoErrors();
    $booking = pairLatest();

    expect($booking->price_cents)->toBe(3500)
        ->and($booking->services->sole()->companion_name)->toBe('Lucía');

    $this->actingAs($this->owner)->post(route('agenda.bookings.drop-companion', $booking), ['came' => 'nadie'])->assertSessionHasErrors('came');
});

it('keeps the name of who came along only while it is needed', function () {
    pairSave();
    pairPublish();
    pairBook([['id' => $this->cut->id, 'variant' => $this->short->id], ['id' => $this->cut->id, 'variant' => $this->short->id, 'companion' => 'Lucía']]);

    $this->travel(20)->days();
    $this->artisan('clients:purge-expired-data')->assertSuccessful();
    expect(pairLatest()->services[1]->companion_name)->toBe('Lucía');

    $this->travel(15)->days();
    $this->artisan('clients:purge-expired-data')->assertSuccessful();
    expect(pairLatest()->services[1]->companion_name)->toBeNull();
});

it('refuses a link as the name of who comes along', function () {
    pairSave();
    pairPublish();

    pairBook([['id' => $this->cut->id, 'variant' => $this->short->id], ['id' => $this->cut->id, 'variant' => $this->short->id, 'companion' => 'Paga en https://yape.example']])
        ->assertSessionHasErrors('services.1.companion');
});

it('does not let another salon take off who comes along', function () {
    pairSave();
    pairPublish();
    pairBook([['id' => $this->cut->id, 'variant' => $this->short->id], ['id' => $this->cut->id, 'variant' => $this->short->id, 'companion' => 'Lucía']]);
    $stranger = User::factory()->create();
    app(CreateSalon::class)->handle($stranger, 'Otro Salón', BusinessType::Unisex, Clientele::Everyone);

    $this->actingAs($stranger)->post(route('agenda.bookings.drop-companion', pairLatest()), ['came' => 'booker'])->assertNotFound();

    expect(pairLatest()->services)->toHaveCount(2);
});
