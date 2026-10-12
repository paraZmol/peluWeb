<?php

use App\Actions\Booking\CreateBooking;
use App\Actions\Salon\CreateSalon;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\PromotionStatus;
use App\Models\Promotion;
use App\Models\SalonClient;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\ServiceVariant;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Step 6.7 (b): the owner's "Promociones". Estudio Luz offers Corte
| (S/ 35), Barba (S/ 18) and Rasurado (S/ 12). "Now" is Wednesday
| 2026-10-07 08:00 in Lima.
*/

/**
 * @param  array<string, mixed>  $with
 */
function promoForm(array $with = []): array
{
    return [
        'card' => 'discount',
        'mechanic' => 'discount',
        'services' => [test()->cut->id],
        'percent' => 20,
        'starts_on' => '2026-10-07',
        'ends_on' => '2026-10-21',
        ...$with,
    ];
}

/**
 * @param  array<string, mixed>  $with
 */
function savePromo(array $with = []): TestResponse
{
    return test()->actingAs(test()->owner)->post(route('promotions.store'), promoForm($with));
}

function lastPromo(): Promotion
{
    return Promotion::withoutGlobalScope('salon')->latest('id')->firstOrFail();
}

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Estudio Luz', BusinessType::Unisex, Clientele::Everyone);
    $this->salon->forceFill(['timezone' => 'America/Lima', 'deposit_percent' => 0])->save();
    $this->staff = Staff::factory()->for($this->salon)->working([3, 4, 5], '09:00', '18:00')->create();
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'price_cents' => 3500, 'active_minutes' => 30, 'position' => 1]);
    $this->beard = Service::factory()->for($this->salon)->create(['name' => 'Barba', 'price_cents' => 1800, 'active_minutes' => 20, 'position' => 2]);
    $this->shave = Service::factory()->for($this->salon)->create(['name' => 'Rasurado', 'price_cents' => 1200, 'active_minutes' => 15, 'position' => 3]);
    $this->staff->services()->attach([$this->cut->id, $this->beard->id, $this->shave->id]);
});

it('saves a draft with the title the system writes, and publishes it', function () {
    savePromo(['services' => [$this->beard->id, $this->cut->id]])->assertSessionHasNoErrors();
    $promotion = lastPromo();

    expect($promotion->status)->toBe(PromotionStatus::Draft)
        ->and($promotion->name)->toBe('20 % menos en Corte y Barba')
        ->and($promotion->share_code)->toMatch('/^[a-z0-9]{10}$/');

    $this->actingAs($this->owner)->post(route('promotions.publish', $promotion))->assertSessionHasNoErrors();

    $this->actingAs($this->owner)->get(route('promotions.index'))->assertInertia(fn (Assert $page) => $page
        ->component('owner/promotions/index')
        ->has('promotions', 1)
        ->where('promotions.0.name', '20 % menos en Corte y Barba')
        ->where('promotions.0.state', 'active')
        ->where('promotions.0.stateLabel', 'Activa')
        ->where('promotions.0.isLocked', false)
        ->where('promotions.0.finePrint', fn (string $text) => str_contains($text, 'Válido para citas del 7 de octubre al 21 de octubre.') && str_contains($text, 'Aplica a: Corte, Barba.'))
        ->where('promotions.0.shareUrl', route('salons.promotions.show', [$this->salon->slug, $promotion->share_code]))
        ->has('services')
        ->where('limits.maxActive', 3));
});

it('publishes in one step, as scheduled when it starts later', function () {
    savePromo(['publish' => true, 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-20', 'card' => 'gift', 'mechanic' => 'gift', 'percent' => null, 'gift_service_id' => $this->shave->id])
        ->assertSessionHasNoErrors();

    expect(lastPromo()->name)->toBe('Rasurado gratis con tu Corte')->and(lastPromo()->status)->toBe(PromotionStatus::Published);
    $this->actingAs($this->owner)->get(route('promotions.index'))
        ->assertInertia(fn (Assert $page) => $page->where('promotions.0.state', 'scheduled'));
});

it('refuses what a promotion cannot be', function (array $with, string $field) {
    savePromo($with)->assertSessionHasErrors($field);

    expect(Promotion::withoutGlobalScope('salon')->count())->toBe(0);
})->with([
    'too small a discount' => [['percent' => 4], 'percent'],
    'too big a discount' => [['percent' => 71], 'percent'],
    'percent and amount at once' => [['amount' => '5'], 'percent'],
    'neither' => [['percent' => null], 'percent'],
    'an amount that leaves the cheapest free' => [['percent' => null, 'amount' => '35'], 'amount'],
    'no services' => [['services' => []], 'services'],
    'ends before it starts' => [['ends_on' => '2026-10-06'], 'ends_on'],
    'longer than 90 days' => [['ends_on' => '2027-01-06'], 'ends_on'],
    'already over' => [['starts_on' => '2026-10-01', 'ends_on' => '2026-10-05'], 'ends_on'],
    'quiet hours without days' => [['card' => 'quiet_hours', 'from_time' => '10:00', 'to_time' => '16:00'], 'weekdays'],
    'quiet hours without hours' => [['card' => 'quiet_hours', 'weekdays' => [1, 2]], 'from_time'],
    'a weekday that does not exist' => [['weekdays' => [8]], 'weekdays.0'],
    'a gift without the gift' => [['card' => 'gift', 'mechanic' => 'gift', 'percent' => null], 'gift_service_id'],
    'a discount with a gift' => [['gift_service_id' => 1], 'gift_service_id'],
    'markup in the title' => [['name' => '<b>Promo</b>'], 'name'],
]);

it('gives away only a service with one fixed price, and never one that earns it', function () {
    $colour = Service::factory()->for($this->salon)->create(['name' => 'Color', 'price_cents' => 9000]);
    (new ServiceVariant)->forceFill(['service_id' => $colour->id, 'label' => 'Largo', 'price_cents' => 15000, 'extra_minutes' => 15, 'position' => 0])->save();
    $gift = ['card' => 'gift', 'mechanic' => 'gift', 'percent' => null];

    savePromo([...$gift, 'gift_service_id' => $colour->id])->assertSessionHasErrors('gift_service_id');
    savePromo([...$gift, 'gift_service_id' => $this->cut->id])->assertSessionHasErrors('gift_service_id');
    savePromo([...$gift, 'gift_service_id' => $this->shave->id])->assertSessionHasNoErrors();
});

it('keeps at most three published at a time', function () {
    foreach (range(1, 3) as $number) {
        savePromo(['publish' => true, 'name' => "Promo {$number}"])->assertInertiaFlash('toast.type', 'success');
    }

    savePromo(['publish' => true, 'name' => 'Promo 4'])->assertInertiaFlash('toast.type', 'error');

    // Saved as a draft all the same; pausing one makes room for it.
    $fourth = lastPromo();
    expect($fourth->status)->toBe(PromotionStatus::Draft);
    $first = Promotion::withoutGlobalScope('salon')->oldest('id')->firstOrFail();
    $this->actingAs($this->owner)->post(route('promotions.pause', $first))->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->post(route('promotions.publish', $fourth))->assertInertiaFlash('toast.type', 'success');

    expect($fourth->refresh()->status)->toBe(PromotionStatus::Published)->and($first->refresh()->status)->toBe(PromotionStatus::Paused);
});

it('locks what bookings were promised: only a later end, more places and the title may change', function () {
    savePromo(['publish' => true, 'quota' => 5]);
    $promotion = lastPromo();
    $booking = app(CreateBooking::class)->handle(new NewBooking(
        salon: $this->salon->refresh(),
        client: SalonClient::factory()->for($this->salon)->create(),
        selections: [['service_id' => $this->cut->id, 'variant_id' => null]],
        staff: $this->staff,
        startsAt: CarbonImmutable::parse('2026-10-07 10:00', 'America/Lima'),
        source: BookingSource::Staff,
    ));
    expect($booking->promotion_id)->toBe($promotion->id);
    $update = fn (array $with) => $this->actingAs($this->owner)->put(route('promotions.update', $promotion), promoForm(['quota' => 5, ...$with]));

    foreach ([['percent' => 50], ['services' => [$this->cut->id, $this->beard->id]], ['ends_on' => '2026-10-15'], ['quota' => 2], ['once_per_client' => true], ['starts_on' => '2026-10-08']] as $change) {
        $update($change)->assertInertiaFlash('toast.type', 'error');
    }
    expect($promotion->refresh()->percent)->toBe(20)->and($promotion->quota)->toBe(5);

    $update(['ends_on' => '2026-10-30', 'quota' => 10, 'name' => 'Semana del corte'])->assertInertiaFlash('toast.type', 'success');
    expect($promotion->refresh()->ends_on->toDateString())->toBe('2026-10-30')
        ->and($promotion->quota)->toBe(10)
        ->and($promotion->name)->toBe('Semana del corte');
    $this->actingAs($this->owner)->get(route('promotions.index'))->assertInertia(fn (Assert $page) => $page
        ->where('promotions.0.isLocked', true)
        ->where('promotions.0.results.bookings', 1));
});

it('stops applying while paused, or when one of its services is no longer offered', function () {
    savePromo(['publish' => true, 'services' => [$this->cut->id, $this->beard->id]]);
    $promotion = lastPromo();
    $state = fn () => $this->actingAs($this->owner)->get(route('promotions.index'))->viewData('page')['props']['promotions'][0]['state'];

    $this->actingAs($this->owner)->post(route('promotions.pause', $promotion));
    expect($state())->toBe('paused');
    $this->actingAs($this->owner)->post(route('promotions.publish', $promotion));
    expect($state())->toBe('active');

    $this->beard->forceFill(['is_active' => false])->save();
    expect($state())->toBe('needs_review');
    $this->actingAs($this->owner)->post(route('promotions.pause', $promotion));
    $this->actingAs($this->owner)->post(route('promotions.publish', $promotion))->assertInertiaFlash('toast.type', 'error');

    $this->travelTo(CarbonImmutable::parse('2026-10-25 08:00', 'America/Lima'));
    expect($state())->toBe('ended');
    $this->actingAs($this->owner)->post(route('promotions.publish', $promotion))->assertInertiaFlash('toast.type', 'error');
});

it('copies one as a draft starting today, and deletes only drafts', function () {
    savePromo(['publish' => true, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-11', 'quota' => 7, 'weekdays' => [1, 2]]);
    $original = lastPromo();
    $this->travelTo(CarbonImmutable::parse('2026-10-20 08:00', 'America/Lima'));

    $this->actingAs($this->owner)->post(route('promotions.duplicate', $original))->assertSessionHasNoErrors();
    $copy = lastPromo();

    expect($copy->id)->not->toBe($original->id)
        ->and($copy->status)->toBe(PromotionStatus::Draft)
        ->and([$copy->starts_on->toDateString(), $copy->ends_on->toDateString()])->toBe(['2026-10-20', '2026-10-30'])
        ->and([$copy->quota, $copy->weekdays, $copy->percent])->toBe([7, [1, 2], 20])
        ->and($copy->services()->pluck('services.id')->all())->toBe([$this->cut->id])
        ->and($copy->share_code)->not->toBe($original->share_code);

    $this->actingAs($this->owner)->delete(route('promotions.destroy', $original))->assertInertiaFlash('toast.type', 'error');
    $this->actingAs($this->owner)->delete(route('promotions.destroy', $copy))->assertInertiaFlash('toast.type', 'success');
    expect(Promotion::withoutGlobalScope('salon')->count())->toBe(1);
});

it('is the owner\'s alone, and of their salon only', function () {
    savePromo();
    $promotion = lastPromo();
    $member = User::factory()->create();
    SalonMember::factory()->for($this->salon)->for($member)->create();
    $otherOwner = User::factory()->create();
    $other = app(CreateSalon::class)->handle($otherOwner, 'Otro Salón', BusinessType::Unisex, Clientele::Everyone);
    $theirs = Service::factory()->for($other)->create();

    // The team sees prices change, it does not decide them.
    $this->actingAs($member)->get(route('promotions.index'))->assertForbidden();
    $this->actingAs($member)->post(route('promotions.store'), promoForm())->assertForbidden();
    $this->actingAs($member)->post(route('promotions.publish', $promotion))->assertForbidden();

    // Another salon attacks the URL with this salon's id.
    $this->actingAs($otherOwner)->put(route('promotions.update', $promotion->id), promoForm(['services' => [$theirs->id]]))->assertNotFound();
    $this->actingAs($otherOwner)->post(route('promotions.publish', $promotion->id))->assertNotFound();
    $this->actingAs($otherOwner)->post(route('promotions.duplicate', $promotion->id))->assertNotFound();
    $this->actingAs($otherOwner)->delete(route('promotions.destroy', $promotion->id))->assertNotFound();
    $this->actingAs($otherOwner)->get(route('promotions.index'))->assertInertia(fn (Assert $page) => $page->has('promotions', 0));

    // And this salon cannot put their service in a promotion.
    savePromo(['services' => [$theirs->id]])->assertSessionHasErrors('services.0');

    expect($promotion->refresh()->status)->toBe(PromotionStatus::Draft);
});

it('sends a shared link to the salon\'s page, never to a 404', function () {
    savePromo(['publish' => true]);

    $this->get(route('salons.promotions.show', [$this->salon->slug, lastPromo()->share_code]))
        ->assertRedirect(route('salons.show', ['salon' => $this->salon->slug, 'promo' => lastPromo()->share_code]));
    $this->get(route('salons.promotions.show', [$this->salon->slug, 'yanoexiste']))
        ->assertRedirect(route('salons.show', ['salon' => $this->salon->slug, 'promo' => 'yanoexiste']));
});
