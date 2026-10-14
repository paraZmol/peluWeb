<?php

use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Salon\CreateSalon;
use App\Enums\BookingStatus;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\PromotionMechanic;
use App\Enums\PromotionStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

/*
| booking-breaker against "Solo con…" (step 6.7 d, filter by professional).
| Estudio Luz: Corte S/ 35; Ana and Leo do it, Wednesday to Friday, 9 to 18.
| The promotion (20 % off) is only with Leo. "Now" is Wednesday 2026-10-07
| 08:00 in Lima.
*/

/**
 * @param  array<string, mixed>  $with
 * @param  list<int>|null  $staff  null = only with Leo
 */
function spbPromo(array $with = [], ?array $staff = null, ?array $services = null): Promotion
{
    $promotion = new Promotion;
    $promotion->forceFill([
        'salon_id' => test()->salon->id,
        'mechanic' => PromotionMechanic::Discount,
        'card' => 'discount',
        'name' => '20 % menos en Corte',
        'status' => PromotionStatus::Published,
        'starts_on' => '2026-10-05',
        'ends_on' => '2026-10-31',
        'percent' => 20,
        'share_code' => Str::lower(Str::random(10)),
        ...$with,
    ])->save();
    $promotion->services()->sync($services ?? [test()->cut->id]);
    $promotion->staff()->sync($staff ?? [test()->leo->id]);

    return $promotion;
}

function spbAt(string $time, string $day = '2026-10-07'): CarbonImmutable
{
    return CarbonImmutable::parse("{$day} {$time}", 'America/Lima');
}

/**
 * @param  array<string, mixed>  $with
 */
function spbBook(?Staff $staff, string $time = '10:00', array $with = [], ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->maria)->post(route('salons.booking.store', test()->salon), [
        'services' => [['id' => test()->cut->id]],
        'staff' => $staff?->id,
        'start' => spbAt($time)->getTimestamp(),
        ...$with,
    ]);
}

/**
 * @param  array<string, mixed>  $with
 */
function spbTeamBook(?Staff $staff, string $time, array $with = []): TestResponse
{
    return test()->actingAs(test()->owner)->post(route('agenda.bookings.store'), [
        'services' => [['id' => test()->cut->id]],
        'staff' => $staff?->id,
        'start' => spbAt($time)->getTimestamp(),
        'client_name' => 'Rosa',
        'client_phone' => '987654321',
        ...$with,
    ]);
}

function spbLatest(): Booking
{
    return Booking::withoutGlobalScope('salon')->latest('id')->firstOrFail();
}

beforeEach(function () {
    Notification::fake();
    $this->travelTo(spbAt('08:00'));

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

describe('"Cualquiera"', function () {
    it('does not get it when the only one free is the promotion\'s professional', function () {
        spbPromo();
        // Ana is taken at 10:00: "anyone" can only land on Leo.
        spbTeamBook($this->ana, '10:00')->assertSessionHasNoErrors();

        spbBook(null, '10:00')->assertSessionHasNoErrors();

        expect(spbLatest()->staff_id)->toBe($this->leo->id)
            ->and(spbLatest()->price_cents)->toBe(3500)
            ->and(spbLatest()->promotion_id)->toBeNull();
    });

    it('books nothing when the client confirmed a promotion price with "anyone"', function () {
        spbPromo();

        spbBook(null, '10:00', ['expects_promotion' => true])->assertInertiaFlash('toast.type', 'error');

        expect(Booking::withoutGlobalScope('salon')->count())->toBe(0);
    });
});

describe('the team books', function () {
    it('with the promotion only when it books with its professional', function () {
        spbPromo();

        spbTeamBook($this->leo, '10:00')->assertSessionHasNoErrors();
        expect(spbLatest()->price_cents)->toBe(2800);

        spbTeamBook(null, '11:00')->assertSessionHasNoErrors();
        expect(spbLatest()->price_cents)->toBe(3500);

        spbTeamBook($this->ana, '12:00')->assertSessionHasNoErrors();
        expect(spbLatest()->price_cents)->toBe(3500);
    });

    it('marks the times with promo in the agenda only with its professional', function () {
        spbPromo();
        $marked = fn (?Staff $staff) => collect($this->actingAs($this->owner)->getJson(route('agenda.bookings.availability', [
            'services' => [['id' => $this->cut->id]], 'date' => '2026-10-07', 'staff' => $staff?->id,
        ]))->assertOk()->json('slots'))->whereNotNull('promotion')->count();

        expect($marked($this->leo))->toBeGreaterThan(0)
            ->and($marked($this->ana))->toBe(0)
            ->and($marked(null))->toBe(0);
    });
});

describe('the mobile API', function () {
    it('applies it only with its professional chosen', function () {
        spbPromo();
        Sanctum::actingAs($this->maria);
        $book = fn (?Staff $staff, string $time) => $this->postJson(route('api.v1.bookings.store', $this->salon), [
            'services' => [['id' => $this->cut->id]], 'staff' => $staff?->id, 'start' => spbAt($time)->getTimestamp(),
        ])->assertCreated();

        $book($this->leo, '10:00');
        expect(spbLatest()->price_cents)->toBe(2800);

        $book(null, '11:00');
        expect(spbLatest()->price_cents)->toBe(3500);

        $book($this->ana, '12:00');
        expect(spbLatest()->price_cents)->toBe(3500);
    });
});

describe('moving a booking with it', function () {
    it('cannot be moved by the client to another professional', function () {
        spbPromo();
        spbBook($this->leo, '10:00')->assertSessionHasNoErrors();
        $booking = spbLatest();

        $this->actingAs($this->maria)->post(route('bookings.move', $booking->code), [
            'start' => spbAt('12:00')->getTimestamp(), 'staff' => $this->ana->id,
        ]);

        expect($booking->refresh()->staff_id)->toBe($this->leo->id)
            ->and($booking->price_cents)->toBe(2800);
    });

    it('keeps its price when the salon passes it to someone else', function () {
        spbPromo();
        spbBook($this->leo, '10:00')->assertSessionHasNoErrors();
        $booking = spbLatest();

        $this->actingAs($this->owner)->post(route('agenda.bookings.move', $booking), [
            'start' => spbAt('12:00')->getTimestamp(), 'staff' => $this->ana->id,
        ])->assertSessionHasNoErrors();

        expect($booking->refresh()->staff_id)->toBe($this->ana->id)
            ->and($booking->price_cents)->toBe(2800)
            ->and($booking->promotion_cents)->toBe(700);
    });
});

describe('a deposit confirmed after its hold ran out', function () {
    it('is refused when its single place went to someone else with Leo', function () {
        $this->salon->forceFill(['deposit_percent' => 50, 'hold_minutes' => 30])->save();
        spbPromo(['quota' => 1]);

        spbBook($this->leo, '10:00')->assertSessionHasNoErrors();
        $first = spbLatest();
        expect($first->price_cents)->toBe(2800);

        $this->travel(31)->minutes();
        spbBook($this->leo, '12:00', as: User::factory()->create())->assertSessionHasNoErrors();
        expect(spbLatest()->price_cents)->toBe(2800);

        expect(fn () => app(ChangeBookingStatus::class)->confirm($first->refresh(), $this->owner))
            ->toThrow(BookingException::class);
    });

    it('is confirmed when its place is still there, even if the salon moved it to Ana', function () {
        $this->salon->forceFill(['deposit_percent' => 50, 'hold_minutes' => 30])->save();
        spbPromo(['quota' => 1]);
        spbBook($this->leo, '10:00')->assertSessionHasNoErrors();
        $booking = spbLatest();
        $this->actingAs($this->owner)->post(route('agenda.bookings.move', $booking), [
            'start' => spbAt('12:00')->getTimestamp(), 'staff' => $this->ana->id,
        ])->assertSessionHasNoErrors();

        $this->travel(31)->minutes();

        expect(app(ChangeBookingStatus::class)->confirm($booking->refresh(), $this->owner)->status)->toBe(BookingStatus::Confirmed)
            ->and($booking->refresh()->price_cents)->toBe(2800);
    });
});

describe('its professional leaves after it was published', function () {
    it('does not open it to the rest of the team when Leo is deactivated', function () {
        $promotion = spbPromo();
        $this->leo->forceFill(['is_active' => false])->save();

        spbBook($this->ana, '10:00')->assertSessionHasNoErrors();
        expect(spbLatest()->price_cents)->toBe(3500);
        spbBook(null, '11:00')->assertSessionHasNoErrors();
        expect(spbLatest()->price_cents)->toBe(3500);

        // Nobody could get it: not advertised, and the owner is asked to look at it.
        $this->get(route('salons.show', $this->salon))->assertInertia(fn (Assert $page) => $page->where('promotions.items', []));
        $this->actingAs($this->owner)->get(route('promotions.index'))->assertInertia(fn (Assert $page) => $page
            ->where('promotions.0.id', $promotion->id)
            ->where('promotions.0.state', 'needs_review'));
    });

    it('does not open it to the rest of the team when Leo is removed', function () {
        $promotion = spbPromo();
        $this->actingAs($this->owner)->delete(route('staff.destroy', $this->leo))->assertSessionHasNoErrors();

        spbBook($this->ana, '10:00')->assertSessionHasNoErrors();
        expect(spbLatest()->price_cents)->toBe(3500)
            ->and(spbLatest()->promotion_id)->toBeNull()
            ->and($promotion->fresh()->terms()->staffIds)->toBe([$this->leo->id]);

        $this->get(route('salons.show', $this->salon))->assertInertia(fn (Assert $page) => $page->where('promotions.items', []));
    });

    it('keeps naming only who is still there when one of two leaves', function () {
        $third = Staff::factory()->for($this->salon)->working([3, 4, 5], '09:00', '18:00')->create(['name' => 'Sol', 'position' => 3]);
        $third->services()->attach($this->cut->id);
        spbPromo(staff: [$this->leo->id, $third->id]);
        $this->actingAs($this->owner)->delete(route('staff.destroy', $this->leo))->assertSessionHasNoErrors();

        spbBook($third, '10:00')->assertSessionHasNoErrors();
        expect(spbLatest()->price_cents)->toBe(2800);
        spbBook($this->ana, '11:00')->assertSessionHasNoErrors();
        expect(spbLatest()->price_cents)->toBe(3500);

        $this->get(route('salons.show', $this->salon))->assertInertia(fn (Assert $page) => $page
            ->where('promotions.items.0.staff', [['id' => $third->id, 'name' => 'Sol']])
            ->where('promotions.items.0.finePrint', fn (string $text) => str_contains($text, 'Solo con Sol: al reservar')));
    });
});

describe('the form', function () {
    it('still lets the owner give a later end once one of its two professionals left, without widening it', function () {
        $third = Staff::factory()->for($this->salon)->working([3, 4, 5], '09:00', '18:00')->create(['name' => 'Sol', 'position' => 3]);
        $third->services()->attach($this->cut->id);
        $promotion = spbPromo(staff: [$this->leo->id, $third->id]);
        spbBook($third, '10:00')->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->delete(route('staff.destroy', $this->leo))->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->get(route('promotions.index'))->assertInertia(fn (Assert $page) => $page
            ->where('promotions.0.form.staff', [$third->id]));

        $this->actingAs($this->owner)->put(route('promotions.update', $promotion), [
            'card' => 'discount', 'mechanic' => 'discount', 'services' => [$this->cut->id], 'percent' => 20,
            'starts_on' => '2026-10-05', 'ends_on' => '2026-11-15', 'staff' => [$third->id],
        ])->assertSessionHasNoErrors();

        expect($promotion->fresh()->ends_on->toDateString())->toBe('2026-11-15');
        spbBook($this->ana, '11:00')->assertSessionHasNoErrors();
        expect(spbLatest()->price_cents)->toBe(3500);
    });

    it('refuses an inactive or removed professional of the salon', function () {
        $this->leo->forceFill(['is_active' => false])->save();
        $gone = Staff::factory()->for($this->salon)->create();
        $gone->delete();

        foreach ([$this->leo->id, $gone->id] as $id) {
            $this->actingAs($this->owner)->post(route('promotions.store'), [
                'card' => 'discount', 'mechanic' => 'discount', 'services' => [$this->cut->id], 'percent' => 20,
                'starts_on' => '2026-10-07', 'ends_on' => '2026-10-31', 'staff' => [$id],
            ])->assertSessionHasErrors('staff.0');
        }

        expect(Promotion::withoutGlobalScope('salon')->count())->toBe(0);
    });
});

describe('places and "one per client" with the filter', function () {
    it('spends a place only on a booking with its professional', function () {
        spbPromo(['quota' => 1]);

        spbBook($this->ana, '10:00')->assertSessionHasNoErrors();
        spbBook($this->leo, '11:00')->assertSessionHasNoErrors();
        expect(spbLatest()->price_cents)->toBe(2800);

        spbBook($this->leo, '12:00', as: User::factory()->create())->assertSessionHasNoErrors();
        expect(spbLatest()->price_cents)->toBe(3500);
    });

    it('gives it once per client, with its professional', function () {
        spbPromo(['once_per_client' => true]);

        spbBook($this->leo, '10:00')->assertSessionHasNoErrors();
        expect(spbLatest()->price_cents)->toBe(2800);
        spbBook($this->leo, '11:00')->assertSessionHasNoErrors();
        expect(spbLatest()->price_cents)->toBe(3500);
    });
});

describe('other cards with the filter', function () {
    beforeEach(function () {
        $this->wash = Service::factory()->for($this->salon)->create(['name' => 'Lavado', 'price_cents' => 2000, 'active_minutes' => 15]);
        $this->ana->services()->attach($this->wash->id);
        $this->leo->services()->attach($this->wash->id);
        // Leo charges more for his cut: the combo with him costs more.
        DB::table('service_staff')->where('staff_id', $this->leo->id)->where('service_id', $this->cut->id)->update(['price_cents' => 4500]);
    });

    it('announces the combo at the price its professional charges', function () {
        spbPromo(['mechanic' => PromotionMechanic::Combo, 'card' => 'combo', 'percent' => null, 'amount_cents' => 1000, 'name' => 'Combo'], services: [$this->cut->id, $this->wash->id]);

        $this->actingAs($this->maria)->post(route('salons.booking.store', $this->salon), [
            'services' => [['id' => $this->cut->id], ['id' => $this->wash->id]],
            'staff' => $this->leo->id,
            'start' => spbAt('10:00')->getTimestamp(),
        ])->assertSessionHasNoErrors();
        $paid = spbLatest()->price_cents;
        expect($paid)->toBe(5500);

        // Ana's S/ 45 is not on offer: with Leo it is S/ 55.
        $this->get(route('salons.show', $this->salon))->assertInertia(fn (Assert $page) => $page
            ->where('promotions.items.0.combo.price', '55.00'));
    });

    it('saves a combo typed at S/ 55 with Leo as S/ 55 with Leo', function () {
        $this->actingAs($this->owner)->post(route('promotions.store'), [
            'card' => 'combo', 'mechanic' => 'combo', 'services' => [$this->cut->id, $this->wash->id], 'combo_price' => 55,
            'starts_on' => '2026-10-07', 'ends_on' => '2026-10-31', 'staff' => [$this->leo->id],
        ])->assertSessionHasNoErrors();
        Promotion::withoutGlobalScope('salon')->sole()->forceFill(['status' => PromotionStatus::Published])->save();

        $this->actingAs($this->maria)->post(route('salons.booking.store', $this->salon), [
            'services' => [['id' => $this->cut->id], ['id' => $this->wash->id]],
            'staff' => $this->leo->id,
            'start' => spbAt('10:00')->getTimestamp(),
        ])->assertSessionHasNoErrors();

        expect(spbLatest()->price_cents)->toBe(5500);
    });

    it('does not announce a combo its professional does not do whole', function () {
        $this->leo->services()->detach($this->wash->id);
        spbPromo(['mechanic' => PromotionMechanic::Combo, 'card' => 'combo', 'percent' => null, 'amount_cents' => 1000, 'name' => 'Combo'], services: [$this->cut->id, $this->wash->id]);

        $this->get(route('salons.show', $this->salon))->assertInertia(fn (Assert $page) => $page->where('promotions.items', []));
    });

    it('gives the 2x1 only with its professional', function () {
        DB::table('service_staff')->where('staff_id', $this->leo->id)->update(['price_cents' => null]);
        spbPromo(['mechanic' => PromotionMechanic::Pair, 'card' => 'pair', 'percent' => 50, 'name' => '2x1']);
        $pair = fn (?Staff $staff, string $time) => $this->actingAs($this->maria)->post(route('salons.booking.store', $this->salon), [
            'services' => [['id' => $this->cut->id], ['id' => $this->cut->id, 'companion' => 'Lucía']],
            'staff' => $staff?->id,
            'start' => spbAt($time)->getTimestamp(),
        ])->assertSessionHasNoErrors();

        $pair($this->leo, '10:00');
        expect(spbLatest()->price_cents)->toBe(5250);
        $pair(null, '12:00');
        expect(spbLatest()->price_cents)->toBe(7000);
    });

    it('gives the first visit only with its professional', function () {
        DB::table('service_staff')->where('staff_id', $this->leo->id)->update(['price_cents' => null]);
        spbPromo(['card' => 'first_visit', 'new_clients_only' => true]);

        spbBook($this->ana, '10:00')->assertSessionHasNoErrors();
        expect(spbLatest()->price_cents)->toBe(3500);
        // Her visit with Ana already counts: no "first visit" with Leo after it.
        spbBook($this->leo, '11:00')->assertSessionHasNoErrors();
        expect(spbLatest()->price_cents)->toBe(3500);

        spbBook($this->leo, '12:00', as: User::factory()->create())->assertSessionHasNoErrors();
        expect(spbLatest()->price_cents)->toBe(2800);
    });
});

it('reads who each promotion is with without a query per promotion', function () {
    $count = function (): int {
        $queries = 0;
        DB::listen(function ($query) use (&$queries): void {
            $queries += str_contains($query->sql, 'promotion_staff') ? 1 : 0;
        });
        $this->get(route('salons.show', $this->salon))->assertOk();
        $this->actingAs($this->owner)->get(route('promotions.index'))->assertOk();
        DB::flushQueryLog();

        return $queries;
    };

    spbPromo();
    $one = $count();
    spbPromo(['name' => 'Otra']);
    spbPromo(['name' => 'Y otra']);

    expect($count())->toBe($one);
});
