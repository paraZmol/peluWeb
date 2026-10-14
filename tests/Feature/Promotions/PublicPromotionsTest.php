<?php

use App\Actions\Salon\CreateSalon;
use App\Enums\BookingNotice;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\PromotionMechanic;
use App\Enums\PromotionStatus;
use App\Models\Booking;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\BookingNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Step 6.7 (c): what a visitor and a client see of a salon's promotions.
| Estudio Luz: Corte S/ 35, Rasurado S/ 12; Ana works Wednesday to
| Friday, 9 to 18. "Now" is Wednesday 2026-10-07 08:00 in Lima.
*/

/**
 * @param  array<string, mixed>  $with
 */
function publicPromo(array $with = []): Promotion
{
    $promotion = new Promotion;
    $promotion->forceFill([
        'salon_id' => test()->salon->id,
        'mechanic' => PromotionMechanic::Discount,
        'card' => 'discount',
        'name' => '20 % menos en Corte',
        'status' => PromotionStatus::Published,
        'starts_on' => '2026-10-05',
        'ends_on' => '2026-10-18',
        'percent' => 20,
        'share_code' => Str::lower(Str::random(10)),
        ...$with,
    ])->save();
    $promotion->services()->sync([test()->cut->id]);

    return $promotion;
}

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Estudio Luz', BusinessType::Unisex, Clientele::Everyone);
    $this->salon->forceFill(['timezone' => 'America/Lima', 'deposit_percent' => 0, 'min_lead_minutes' => 0])->save();
    $this->staff = Staff::factory()->for($this->salon)->working([3, 4, 5], '09:00', '18:00')->create(['name' => 'Ana']);
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'price_cents' => 3500, 'active_minutes' => 30]);
    $this->shave = Service::factory()->for($this->salon)->create(['name' => 'Rasurado', 'price_cents' => 1200, 'active_minutes' => 15]);
    $this->staff->services()->attach([$this->cut->id, $this->shave->id]);
    $this->maria = User::factory()->create();
});

it('shows on the salon\'s page only what is on offer today, with what each service costs', function () {
    $active = publicPromo();
    publicPromo(['name' => 'Borrador', 'status' => PromotionStatus::Draft]);
    publicPromo(['name' => 'Pausada', 'status' => PromotionStatus::Paused]);
    publicPromo(['name' => 'Programada', 'starts_on' => '2026-10-10']);
    publicPromo(['name' => 'Terminada', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-06']);

    $this->get(route('salons.show', $this->salon))->assertInertia(fn (Assert $page) => $page
        ->has('promotions.items', 1)
        ->where('promotions.items.0.id', $active->id)
        ->where('promotions.items.0.name', '20 % menos en Corte')
        ->where('promotions.items.0.prices.0.normal', '35.00')
        ->where('promotions.items.0.prices.0.promo', '28.00')
        ->where('promotions.items.0.finePrint', fn (string $text) => str_contains($text, 'Aplica a: Corte.'))
        ->where('promotions.shared', null));

    $this->get(route('salons.booking.create', $this->salon))
        ->assertInertia(fn (Assert $page) => $page->has('promotions', 1)->where('promotions.0.id', $active->id));
});

it('points at the shared promotion, and says so when it is over or never was', function () {
    $active = publicPromo();
    $ended = publicPromo(['name' => 'Terminada', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-06']);

    $this->get(route('salons.show', ['salon' => $this->salon->slug, 'promo' => $active->share_code]))
        ->assertInertia(fn (Assert $page) => $page->where('promotions.shared.id', $active->id)->where('promotions.shared.hasEnded', false));

    foreach ([$ended->share_code, 'noexiste99'] as $code) {
        $this->get(route('salons.show', ['salon' => $this->salon->slug, 'promo' => $code]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('promotions.shared.id', null)->where('promotions.shared.hasEnded', true));
    }
});

it('offers the gift with what it is worth and how long it adds', function () {
    publicPromo(['mechanic' => PromotionMechanic::Gift, 'card' => 'gift', 'name' => 'Rasurado gratis con tu Corte', 'percent' => null, 'gift_service_id' => $this->shave->id]);

    $this->get(route('salons.show', $this->salon))->assertInertia(fn (Assert $page) => $page
        ->where('promotions.items.0.gift.serviceId', $this->shave->id)
        ->where('promotions.items.0.gift.price', '12.00')
        ->where('promotions.items.0.gift.minutes', 15)
        // The services that earn it keep their price.
        ->where('promotions.items.0.prices.0.promo', '35.00'));
});

it('marks in the time picker the starts where the promotion applies, and only those', function () {
    publicPromo(['weekdays' => [3], 'from_minute' => 600, 'to_minute' => 720, 'name' => 'Horas flojas']);
    $slots = fn (string $date) => collect($this->getJson(route('salons.booking.availability', [
        'salon' => $this->salon->slug, 'services' => [['id' => $this->cut->id]], 'date' => $date,
    ]))->assertOk()->json('slots'))->keyBy('time');

    $wednesday = $slots('2026-10-07');
    expect($wednesday['10:00']['promotion'])->toBe(['name' => 'Horas flojas', 'saves' => '7.00'])
        ->and($wednesday['11:45']['promotion'])->toBe(['name' => 'Horas flojas', 'saves' => '7.00'])
        ->and($wednesday['12:00']['promotion'])->toBeNull()
        ->and($wednesday['09:30']['promotion'])->toBeNull()
        ->and($slots('2026-10-08')['10:00']['promotion'])->toBeNull();
});

it('stops marking the starts once the promotion has no places left', function () {
    $promotion = publicPromo(['quota' => 1]);
    $marked = fn () => collect($this->getJson(route('salons.booking.availability', [
        'salon' => $this->salon->slug, 'services' => [['id' => $this->cut->id]], 'date' => '2026-10-07',
    ]))->json('slots'))->whereNotNull('promotion')->count();

    expect($marked())->toBeGreaterThan(0);

    $this->actingAs($this->maria)->post(route('salons.booking.store', $this->salon), [
        'services' => [['id' => $this->cut->id]],
        'start' => CarbonImmutable::parse('2026-10-07 10:00', 'America/Lima')->getTimestamp(),
    ])->assertSessionHasNoErrors();
    expect(Booking::withoutGlobalScope('salon')->sole()->promotion_id)->toBe($promotion->id);
    auth()->logout();

    expect($marked())->toBe(0);
});

it('stops advertising a promotion once nobody could get it', function () {
    $promotion = publicPromo(['quota' => 1, 'name' => 'Martes de Corte']);
    $shown = fn () => $this->get(route('salons.show', ['salon' => $this->salon->slug, 'promo' => $promotion->share_code]));

    $shown()->assertInertia(fn (Assert $page) => $page
        ->has('promotions.items', 1)
        ->where('promotions.items.0.quota', 1)
        ->where('promotions.items.0.period', 'del 5 al 18 de octubre de 2026')
        // Their own title never hides what it does.
        ->where('promotions.items.0.summary', '20 % menos en Corte'));

    $this->actingAs($this->maria)->post(route('salons.booking.store', $this->salon), [
        'services' => [['id' => $this->cut->id]],
        'start' => CarbonImmutable::parse('2026-10-07 10:00', 'America/Lima')->getTimestamp(),
    ])->assertSessionHasNoErrors();
    auth()->logout();

    // The shared link says it is over instead of promising a price.
    $shown()->assertInertia(fn (Assert $page) => $page->has('promotions.items', 0)->where('promotions.shared.hasEnded', true));
});

it('tells the client what the promotion took off, on the booking and in the mail', function () {
    publicPromo();

    $this->actingAs($this->maria)->post(route('salons.booking.store', $this->salon), [
        'services' => [['id' => $this->cut->id]],
        'start' => CarbonImmutable::parse('2026-10-07 10:00', 'America/Lima')->getTimestamp(),
    ])->assertSessionHasNoErrors();
    $booking = Booking::withoutGlobalScope('salon')->sole();

    $this->actingAs($this->maria)->get(route('bookings.show', $booking->code))->assertInertia(fn (Assert $page) => $page
        ->where('booking.price', '28.00')
        ->where('booking.promotion', ['name' => '20 % menos en Corte', 'saved' => '7.00'])
        ->where('booking.services.0.price', '28.00')
        ->where('booking.services.0.listPrice', '35.00'));

    Notification::assertSentTo($this->maria, BookingNotification::class, fn (BookingNotification $notification): bool => $notification->notice === BookingNotice::Confirmed
        && in_array('Promoción 20 % menos en Corte: −S/ 7.00. Total: S/ 28.00.', $notification->notes, true));
});

it('lets the team book at the normal price on purpose', function () {
    publicPromo();
    $form = fn (string $time, array $with = []) => [
        'services' => [['id' => $this->cut->id]],
        'staff' => $this->staff->id,
        'start' => CarbonImmutable::parse("2026-10-07 {$time}", 'America/Lima')->getTimestamp(),
        'client_name' => 'Luis Paredes',
        ...$with,
    ];

    $this->actingAs($this->owner)->post(route('agenda.bookings.store'), $form('10:00'))->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->post(route('agenda.bookings.store'), $form('11:00', ['with_promotion' => false]))->assertSessionHasNoErrors();

    $prices = Booking::withoutGlobalScope('salon')->orderBy('starts_at')->pluck('price_cents')->all();
    expect($prices)->toBe([2800, 3500]);
});

it('books nothing, and says so, when the promotion they confirmed is gone', function () {
    $promotion = publicPromo();
    $book = fn (array $with = []) => $this->actingAs($this->maria)->post(route('salons.booking.store', $this->salon), [
        'services' => [['id' => $this->cut->id]],
        'start' => CarbonImmutable::parse('2026-10-07 10:00', 'America/Lima')->getTimestamp(),
        ...$with,
    ]);

    // Paused between seeing the price and confirming it.
    $promotion->forceFill(['status' => PromotionStatus::Paused])->save();
    $book(['expects_promotion' => true])->assertInertiaFlash('toast.type', 'error')
        // Sent back to the times: the wizard reads "horario" in what failed.
        ->assertInertiaFlash('toast.message', 'La promoción ya no está disponible para ese horario. Elige el horario de nuevo: verás el precio actualizado antes de confirmar.');
    expect(Booking::withoutGlobalScope('salon')->count())->toBe(0);

    // Having seen the normal price, they book at it.
    $book(['expects_promotion' => false])->assertSessionHasNoErrors();
    expect(Booking::withoutGlobalScope('salon')->sole()->price_cents)->toBe(3500);
});

it('takes a shared code only in the shape codes have', function () {
    publicPromo();

    $this->get('/s/'.$this->salon->slug.'?promo[]=x')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('promotions.shared', null));
    $this->get('/s/'.$this->salon->slug.'?promo=%3Cscript%3E')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('promotions.shared', null));
});
