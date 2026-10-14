<?php

use App\Actions\Salon\CreateSalon;
use App\Enums\BusinessType;
use App\Enums\Clientele;
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
| Step 6.7 (d): a promotion only with some of the team. Estudio Luz: Corte
| S/ 35; Ana and Leo do it, Wednesday to Friday, 9 to 18. The promotion is
| only with Leo. "Now" is Wednesday 2026-10-07 08:00 in Lima.
*/

/**
 * @param  array<string, mixed>  $with
 */
function staffPromoSave(array $with = []): TestResponse
{
    return test()->actingAs(test()->owner)->post(route('promotions.store'), [
        'card' => 'discount',
        'mechanic' => 'discount',
        'services' => [test()->cut->id],
        'percent' => 20,
        'starts_on' => '2026-10-07',
        'ends_on' => '2026-10-31',
        'staff' => [test()->leo->id],
        ...$with,
    ]);
}

function staffPromoBook(?Staff $staff, string $time = '10:00'): Booking
{
    test()->actingAs(test()->maria)->post(route('salons.booking.store', test()->salon), [
        'services' => [['id' => test()->cut->id]],
        'staff' => $staff?->id,
        'start' => CarbonImmutable::parse("2026-10-07 {$time}", 'America/Lima')->getTimestamp(),
    ])->assertSessionHasNoErrors();

    return Booking::withoutGlobalScope('salon')->latest('id')->firstOrFail();
}

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Estudio Luz', BusinessType::Unisex, Clientele::Everyone);
    $this->salon->forceFill(['timezone' => 'America/Lima', 'deposit_percent' => 0, 'min_lead_minutes' => 0])->save();
    $this->ana = Staff::factory()->for($this->salon)->working([3, 4, 5], '09:00', '18:00')->create(['name' => 'Ana', 'position' => 1]);
    $this->leo = Staff::factory()->for($this->salon)->working([3, 4, 5], '09:00', '18:00')->create(['name' => 'Leo', 'position' => 2]);
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'price_cents' => 3500, 'active_minutes' => 30]);
    $this->ana->services()->attach($this->cut->id);
    $this->leo->services()->attach($this->cut->id);
    $this->maria = User::factory()->create();
});

it('holds only with one of its professionals chosen, never with "anyone"', function () {
    staffPromoSave()->assertSessionHasNoErrors();
    Promotion::withoutGlobalScope('salon')->sole()->forceFill(['status' => PromotionStatus::Published])->save();

    expect(staffPromoBook($this->leo, '10:00')->price_cents)->toBe(2800)
        ->and(staffPromoBook($this->ana, '10:00')->price_cents)->toBe(3500)
        ->and(staffPromoBook(null, '11:00')->price_cents)->toBe(3500);
});

it('marks the times with promo only when one of its professionals is chosen', function () {
    staffPromoSave();
    Promotion::withoutGlobalScope('salon')->sole()->forceFill(['status' => PromotionStatus::Published])->save();
    $marked = fn (?Staff $staff) => collect($this->getJson(route('salons.booking.availability', [
        'salon' => $this->salon->slug, 'services' => [['id' => $this->cut->id]], 'date' => '2026-10-07', 'staff' => $staff?->id,
    ]))->assertOk()->json('slots'))->whereNotNull('promotion')->count();

    expect($marked($this->leo))->toBeGreaterThan(0)
        ->and($marked($this->ana))->toBe(0)
        ->and($marked(null))->toBe(0);
});

it('says on the page and in the fine print who it is with', function () {
    staffPromoSave();
    Promotion::withoutGlobalScope('salon')->sole()->forceFill(['status' => PromotionStatus::Published])->save();

    $this->get(route('salons.show', $this->salon))->assertInertia(fn (Assert $page) => $page
        ->where('promotions.items.0.staff', [['id' => $this->leo->id, 'name' => 'Leo']])
        ->where('promotions.items.0.finePrint', fn (string $text) => str_contains($text, 'Solo con Leo: al reservar, elige a esa persona.')));
});

it('refuses a professional of another salon, and keeps the team once booked', function () {
    $stranger = User::factory()->create();
    $other = app(CreateSalon::class)->handle($stranger, 'Otro Salón', BusinessType::Unisex, Clientele::Everyone);
    $theirs = Staff::factory()->for($other)->create();

    staffPromoSave(['staff' => [$theirs->id]])->assertSessionHasErrors('staff.0');

    staffPromoSave()->assertSessionHasNoErrors();
    $promotion = Promotion::withoutGlobalScope('salon')->latest('id')->firstOrFail();
    $promotion->forceFill(['status' => PromotionStatus::Published])->save();
    staffPromoBook($this->leo);

    $this->actingAs($this->owner)->put(route('promotions.update', $promotion), [
        'card' => 'discount', 'mechanic' => 'discount', 'services' => [$this->cut->id], 'percent' => 20,
        'starts_on' => '2026-10-07', 'ends_on' => '2026-10-31', 'staff' => [$this->ana->id],
    ])->assertInertiaFlash('toast.type', 'error');

    expect($promotion->fresh()->staffIds())->toBe([$this->leo->id]);
});
