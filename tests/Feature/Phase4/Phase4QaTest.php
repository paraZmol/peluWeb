<?php

use App\Actions\Booking\CreateBooking;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\SalonPermission;
use App\Enums\ScheduleAccess;
use App\Models\Booking;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\PersonalAccessToken;

/*
| Phase 4 closing QA: functional gaps between the plan and the app, by role.
| "Now" is Wednesday 2026-10-07 08:00 in Lima. Pedro works every day
| 00:00-23:55; Kevin (a professional, no extra permissions) Wed-Fri 09-18.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create(['slug' => 'barberia-qa', 'timezone' => 'America/Lima', 'phone' => '999888777']);
    $this->pedro = Staff::factory()->for($this->salon)->working([1, 2, 3, 4, 5, 6, 7], '00:00', '23:55')->create(['name' => 'Pedro']);
    $this->member = SalonMember::factory()->for($this->salon)->create();
    $this->kevin = Staff::factory()->for($this->salon)->linkedTo($this->member->user)->working([3, 4, 5], '09:00', '18:00')->create(['name' => 'Kevin']);
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'price_cents' => 3500, 'active_minutes' => 30]);
    $this->pedro->services()->attach($this->cut->id);
    $this->kevin->services()->attach($this->cut->id);
    $this->customer = User::factory()->create(['name' => 'Ana Cliente', 'email' => 'ana@example.com', 'password' => 'clave-segura-123']);
});

function qa4At(string $time, string $date = '2026-10-09'): int
{
    return CarbonImmutable::parse("{$date} {$time}", 'America/Lima')->getTimestamp();
}

/**
 * A booking through the action. Without $user it is for a fresh walk-in.
 */
function qa4Book(string $time, string $date = '2026-10-09', ?Staff $staff = null, ?User $user = null, BookingSource $source = BookingSource::Staff, ?SalonClient $client = null): Booking
{
    $test = test();
    $client ??= $user === null
        ? SalonClient::factory()->for($test->salon)->create()
        : (SalonClient::withoutGlobalScopes()->where('salon_id', $test->salon->id)->where('user_id', $user->id)->first()
            ?? SalonClient::factory()->for($test->salon)->forUser($user)->create());

    return app(CreateBooking::class)->handle(new NewBooking(
        salon: $test->salon->refresh(),
        client: $client,
        selections: [['service_id' => $test->cut->id, 'variant_id' => null]],
        staff: $staff ?? $test->pedro,
        startsAt: CarbonImmutable::createFromTimestampUTC(qa4At($time, $date)),
        source: $source,
    ));
}

describe('4.8 new booking by the team: what a professional can reach', function () {
    it('refuses a client id the professional never attended (otherwise ids can be walked to copy the client list)', function () {
        $stranger = SalonClient::factory()->for($this->salon)->create(['name' => 'Rosa Secreta', 'phone' => '900111222']);

        $response = $this->actingAs($this->member->user)->post(route('agenda.bookings.store'), [
            'services' => [['id' => $this->cut->id]],
            'staff' => $this->kevin->id,
            'start' => qa4At('10:00'),
            'client_id' => $stranger->id,
        ]);

        expect(Booking::withoutGlobalScopes()->count())->toBe(0, 'booked for a client the professional has never attended')
            ->and(session('inertia.flash.toast.message') ?? '')->not->toContain('Rosa Secreta');
        expect($response->status())->toBeIn([302, 403, 404, 422]);
    });

    it('does not attach the visit to a colleague\'s client just because the professional typed that phone', function () {
        $stranger = SalonClient::factory()->for($this->salon)->create(['name' => 'Rosa Secreta', 'phone' => '900111222']);
        qa4Book('11:00', staff: $this->pedro, client: $stranger);

        $this->actingAs($this->member->user)->post(route('agenda.bookings.store'), [
            'services' => [['id' => $this->cut->id]],
            'staff' => $this->kevin->id,
            'start' => qa4At('10:00'),
            'client_name' => 'Cualquiera',
            'client_phone' => '900111222',
        ]);

        $mine = Booking::withoutGlobalScopes()->where('staff_id', $this->kevin->id)->first();
        expect($mine?->salon_client_id)->not->toBe($stranger->id, 'the phone revealed (and linked) a client of another professional');
    });

    it('lets a professional book for a client they attended before, picked by id', function () {
        $mine = qa4Book('09:00', staff: $this->kevin);

        $this->actingAs($this->member->user)->post(route('agenda.bookings.store'), [
            'services' => [['id' => $this->cut->id]],
            'staff' => $this->kevin->id,
            'start' => qa4At('10:00'),
            'client_id' => $mine->salon_client_id,
        ])->assertRedirect();

        expect(Booking::withoutGlobalScopes()->where('salon_client_id', $mine->salon_client_id)->count())->toBe(2);
    });

    it('accepts emojis, tildes and ñ in the name and a note, and keeps them intact', function () {
        $this->actingAs($this->owner)->post(route('agenda.bookings.store'), [
            'services' => [['id' => $this->cut->id]],
            'staff' => $this->pedro->id,
            'start' => qa4At('10:00'),
            'client_name' => 'Ñandú Peña Núñez 💈',
            'note' => 'Quiere "degradado" bajo — ¡con ñ!',
        ])->assertRedirect();

        $booking = Booking::withoutGlobalScopes()->sole();
        expect($booking->client->name)->toContain('Ñandú Peña Núñez')
            ->and($booking->client_note)->toBe('Quiere "degradado" bajo — ¡con ñ!');
    });

    it('answers a phone with spaces or letters with the Spanish message', function (string $phone) {
        $this->actingAs($this->owner)->post(route('agenda.bookings.store'), [
            'services' => [['id' => $this->cut->id]],
            'staff' => $this->pedro->id,
            'start' => qa4At('10:00'),
            'client_name' => 'Ana',
            'client_phone' => $phone,
        ])->assertSessionHasErrors(['client_phone' => 'Escribe el teléfono solo con números, por ejemplo 987654321.']);
    })->with(['letters' => 'nueve ocho', 'spaces' => '987 654 321', 'too short' => '12345']);
});

describe('4.7 agenda: who sees what', function () {
    it('keeps strangers out of every agenda endpoint, with the id of someone else\'s booking', function () {
        $booking = qa4Book('10:00');
        $foreignOwner = User::factory()->create();
        Salon::factory()->ownedBy($foreignOwner)->create();
        $actions = ['confirmar', 'iniciar', 'completar', 'no-vino', 'cancelar', 'mover'];

        // A client first: tests share one container, so a salon resolved for
        // an earlier request would linger (a test artifact, not production).
        foreach ($actions as $action) {
            expect($this->actingAs($this->customer)->post("/agenda/{$booking->id}/{$action}", ['start' => qa4At('11:00')])->status())->toBeIn([302, 403, 404]);
        }
        expect($this->actingAs($this->customer)->get(route('agenda.index'))->status())->toBeIn([302, 403, 404]);

        foreach ($actions as $action) {
            $this->actingAs($foreignOwner)->post("/agenda/{$booking->id}/{$action}", ['start' => qa4At('11:00')])
                ->assertNotFound();
        }
        $this->actingAs($foreignOwner)->getJson("/agenda/{$booking->id}/horarios?date=2026-10-09")->assertNotFound();
        auth()->logout();
        $this->get(route('agenda.index'))->assertRedirect(route('login'));

        expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed)
            ->and($booking->starts_at->getTimestamp())->toBe(qa4At('10:00'));
    });

    it('does not let a professional touch a colleague\'s booking, nor a loose ManageCash confirm it', function () {
        $this->salon->forceFill(['deposit_percent' => 30])->save();
        $cashier = SalonMember::factory()->for($this->salon)->withPermissions(SalonPermission::ManageCash)->create();
        $lola = Staff::factory()->for($this->salon)->linkedTo($cashier->user)->working([5], '09:00', '18:00')->create(['name' => 'Lola']);
        $lola->services()->attach($this->cut->id);
        $others = qa4Book('10:00', staff: $this->pedro, user: $this->customer, source: BookingSource::Client);
        $own = qa4Book('12:00', staff: $lola, user: User::factory()->create(), source: BookingSource::Client);
        expect([$others->status, $own->status])->toBe([BookingStatus::Pending, BookingStatus::Pending]);

        $this->actingAs($cashier->user)->post(route('agenda.bookings.confirm', $others))->assertForbidden();
        $this->actingAs($cashier->user)->post(route('agenda.bookings.confirm', $own))->assertRedirect();
        // A professional without the cash permission cannot confirm even their own.
        $this->actingAs($this->member->user)->post(route('agenda.bookings.confirm', qa4Book('14:00', staff: $this->kevin, user: User::factory()->create(), source: BookingSource::Client)))->assertForbidden();

        expect($others->refresh()->status)->toBe(BookingStatus::Pending)
            ->and($own->refresh()->status)->toBe(BookingStatus::Confirmed);
    });

    it('lets a professional see only their own list whatever the query asks for', function () {
        qa4Book('10:00', staff: $this->pedro);
        $kevins = qa4Book('11:00', staff: $this->kevin);

        $this->actingAs($this->member->user)->get(route('agenda.index', ['fecha' => '2026-10-09', 'profesional' => $this->pedro->id]))
            ->assertInertia(fn (Assert $page) => $page->has('bookings', 1)->where('bookings.0.id', $kevins->id)->where('seesEveryone', false));
    });

    it('ignores a professional of another salon in the filter and shows the whole team', function () {
        qa4Book('10:00', staff: $this->pedro);
        qa4Book('11:00', staff: $this->kevin);
        $foreign = Staff::factory()->for(Salon::factory()->create())->create();

        $this->actingAs($this->owner)->get(route('agenda.index', ['fecha' => '2026-10-09', 'profesional' => $foreign->id]))
            ->assertInertia(fn (Assert $page) => $page->has('bookings', 2)->where('selectedStaffId', null));
    });

    it('puts a Sunday-night visit in its own week and Monday\'s early visit in the next (salon clock, not UTC)', function () {
        $sunday = qa4Book('23:00', '2026-10-11');
        $monday = qa4Book('00:30', '2026-10-12');

        $week = fn (string $date) => $this->actingAs($this->owner)->get(route('agenda.index', ['vista' => 'semana', 'fecha' => $date, 'profesional' => $this->pedro->id]));

        $week('2026-10-07')->assertInertia(fn (Assert $page) => $page->has('bookings', 1)->where('bookings.0.id', $sunday->id));
        $week('2026-10-14')->assertInertia(fn (Assert $page) => $page->has('bookings', 1)->where('bookings.0.id', $monday->id));
    });

    it('sends cancelled visits to the page (the grid hides them) and drops expired holds', function () {
        $cancelled = qa4Book('10:00');
        $cancelled->forceFill(['status' => BookingStatus::Cancelled])->save();
        $expired = qa4Book('12:00');
        $expired->forceFill(['status' => BookingStatus::Expired])->save();
        qa4Book('14:00');

        $this->actingAs($this->owner)->get(route('agenda.index', ['fecha' => '2026-10-09']))
            ->assertInertia(fn (Assert $page) => $page->has('bookings', 2)->where('bookings.0.status', 'cancelled'));
    });

    it('opens the board only with two or more professionals and falls back to the list otherwise', function () {
        $this->kevin->forceFill(['is_active' => false])->save();

        $this->actingAs($this->owner)->get(route('agenda.index', ['vista' => 'tablero']))
            ->assertInertia(fn (Assert $page) => $page->where('view', 'lista'));
    });
});

describe('4.8 moving: the client side', function () {
    it('stops offering "Cambiar día u hora" once the three moves are used', function () {
        $mine = qa4Book('10:00', user: $this->customer, source: BookingSource::Client);

        foreach (['11:00', '12:00', '13:00'] as $time) {
            $this->actingAs($this->customer)->post(route('bookings.move', $mine->code), ['start' => qa4At($time)])->assertRedirect();
        }
        $this->actingAs($this->customer)->post(route('bookings.move', $mine->code), ['start' => qa4At('14:00')])
            ->assertInertiaFlash('toast.type', 'error');

        expect($mine->refresh()->starts_at->getTimestamp())->toBe(qa4At('13:00'));
        $this->actingAs($this->customer)->get(route('bookings.show', $mine->code))
            ->assertInertia(fn (Assert $page) => $page->where('booking.canMove', false));
    });

    it('hides the button inside the cancellation window and offers no times', function () {
        $this->salon->forceFill(['cancel_window_hours' => 24])->save();
        $soon = qa4Book('07:00', '2026-10-08', user: $this->customer, source: BookingSource::Client);
        $later = qa4Book('10:00', '2026-10-10', user: $this->customer, source: BookingSource::Client);

        $this->actingAs($this->customer)->get(route('bookings.show', $soon->code))->assertInertia(fn (Assert $page) => $page->where('booking.canMove', false));
        $this->actingAs($this->customer)->get(route('bookings.show', $later->code))->assertInertia(fn (Assert $page) => $page->where('booking.canMove', true));
        $this->actingAs($this->customer)->post(route('bookings.move', $soon->code), ['start' => qa4At('12:00', '2026-10-08')])->assertInertiaFlash('toast.type', 'error');
    });

    it('lets the owner hand a visit to a colleague who performs the service, from the move endpoint', function () {
        $booking = qa4Book('10:00', staff: $this->pedro);

        $this->actingAs($this->owner)->post(route('agenda.bookings.move', $booking), ['start' => qa4At('10:00'), 'staff' => $this->kevin->id])->assertRedirect();

        expect($booking->refresh()->staff_id)->toBe($this->kevin->id);
    });
});

describe('4.9 repeat and reference photo', function () {
    it('keeps the chosen variant when repeating (the same service, not the first option)', function () {
        $color = Service::factory()->for($this->salon)->create(['name' => 'Tinte', 'active_minutes' => 60]);
        $short = $color->variants()->create(['label' => 'Corto', 'price_cents' => 8000, 'extra_minutes' => 0, 'position' => 0]);
        $long = $color->variants()->create(['label' => 'Largo', 'price_cents' => 12000, 'extra_minutes' => 30, 'position' => 1]);
        $this->pedro->services()->attach($color->id);
        $client = SalonClient::factory()->for($this->salon)->forUser($this->customer)->create();
        $booking = app(CreateBooking::class)->handle(new NewBooking(
            salon: $this->salon->refresh(),
            client: $client,
            selections: [['service_id' => $color->id, 'variant_id' => $long->id]],
            staff: $this->pedro,
            startsAt: CarbonImmutable::createFromTimestampUTC(qa4At('10:00')),
            source: BookingSource::Client,
        ));

        $this->actingAs($this->customer)->get(route('salons.booking.create', ['salon' => 'barberia-qa', 'repetir' => $booking->code]))
            ->assertInertia(fn (Assert $page) => $page->where('repeat', fn ($repeat) => str_contains(strtolower(json_encode($repeat)), 'variant') && $short->id !== $long->id));
    });

    it('lets no one but the client of the booking read or change its photo', function () {
        $mine = qa4Book('10:00', user: $this->customer, source: BookingSource::Client);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->delete(route('bookings.photo.destroy', $mine->code))->assertNotFound();
        $this->actingAs($this->owner)->post(route('bookings.photo.store', $mine->code), ['image' => UploadedFile::fake()->image('a.jpg')])->assertNotFound();
        auth()->logout();
        $this->post(route('bookings.photo.store', $mine->code), [])->assertRedirect(route('login'));
    });
});

describe('4.10 API with a real token, start to finish', function () {
    it('runs the whole mobile flow: login, salon, times, book, list, move, cancel, logout', function () {
        $login = $this->postJson('/api/v1/login', ['email' => 'ana@example.com', 'password' => 'clave-segura-123', 'device_name' => 'Pixel QA'])->assertCreated();
        $token = $login->json('token');

        $this->getJson('/api/v1/salons/barberia-qa')->assertOk()->assertJsonPath('salon.slug', 'barberia-qa');
        $query = http_build_query(['date' => '2026-10-09', 'services' => [['id' => $this->cut->id]], 'staff' => $this->pedro->id]);
        $slots = $this->withToken($token)->getJson("/api/v1/salons/barberia-qa/availability?{$query}")->assertOk()->json('slots');
        expect(collect($slots)->pluck('time'))->toContain('10:00');

        $created = $this->withToken($token)->postJson('/api/v1/salons/barberia-qa/bookings', [
            'services' => [['id' => $this->cut->id]], 'staff' => $this->pedro->id, 'start' => qa4At('10:00'), 'note' => 'Desde el móvil ñ 💈',
        ])->assertCreated();
        $code = $created->json('booking.code');

        $this->withToken($token)->getJson('/api/v1/bookings')->assertOk()->assertJsonCount(1, 'bookings');
        $this->withToken($token)->postJson("/api/v1/bookings/{$code}/move", ['start' => qa4At('11:00')])->assertOk()->assertJsonPath('booking.time', '11:00');
        $this->withToken($token)->postJson("/api/v1/bookings/{$code}/cancel")->assertOk()->assertJsonPath('booking.status', 'cancelled');
        $this->withToken($token)->postJson('/api/v1/logout')->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/bookings')->assertUnauthorized();
        expect(PersonalAccessToken::query()->count())->toBe(0);
    });

    it('answers JSON errors even without the Accept header', function () {
        $token = $this->customer->createToken('x')->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}", 'Accept' => '*/*'];

        $this->post('/api/v1/salons/barberia-qa/bookings', ['start' => 'nope'], $headers)->assertStatus(422)->assertJsonStructure(['message', 'errors']);
        $this->get('/api/v1/bookings/NOEXISTE', $headers)->assertNotFound()->assertJsonStructure(['message']);
        $this->get('/api/v1/salons/no-existe', ['Accept' => '*/*'])->assertNotFound()->assertJsonStructure(['message']);
        $this->post('/api/v1/login', [], ['Accept' => 'text/html'])->assertStatus(422)->assertJsonStructure(['errors']);
    });

    it('stops the sixth upcoming booking with a message and frees the place after cancelling one', function () {
        $this->actingAs($this->customer);
        $this->app['auth']->forgetGuards();
        $token = $this->customer->createToken('x')->plainTextToken;
        $codes = [];

        foreach (['09:00', '10:00', '11:00', '12:00', '13:00'] as $time) {
            $codes[] = $this->withToken($token)->postJson('/api/v1/salons/barberia-qa/bookings', [
                'services' => [['id' => $this->cut->id]], 'staff' => $this->pedro->id, 'start' => qa4At($time),
            ])->assertCreated()->json('booking.code');
        }

        $this->withToken($token)->postJson('/api/v1/salons/barberia-qa/bookings', [
            'services' => [['id' => $this->cut->id]], 'staff' => $this->pedro->id, 'start' => qa4At('14:00'),
        ])->assertStatus(422)->assertJsonPath('message', fn ($message) => str_contains($message, '5'));

        $this->withToken($token)->postJson("/api/v1/bookings/{$codes[0]}/cancel")->assertOk();
        expect(Booking::withoutGlobalScopes()->count())->toBe(5);
    });

    it('gives the pending booking its deposit data, keeps the hold when moved, and caps moves at three', function () {
        $this->salon->forceFill(['deposit_percent' => 30, 'payment_instructions' => 'Yape 987654321'])->save();
        $token = $this->customer->createToken('x')->plainTextToken;

        $created = $this->withToken($token)->postJson('/api/v1/salons/barberia-qa/bookings', [
            'services' => [['id' => $this->cut->id]], 'staff' => $this->pedro->id, 'start' => qa4At('10:00'),
        ])->assertCreated()->assertJsonPath('booking.status', 'pending')->assertJsonPath('booking.deposit.isPaid', false);
        $code = $created->json('booking.code');
        expect($created->json('booking.deposit.whatsappUrl'))->toContain('wa.me')->toContain($code)
            ->and($created->json('booking.deposit.instructions'))->toBe('Yape 987654321')
            ->and($created->json('booking.deposit.expiresAt'))->not->toBeNull();

        foreach (['11:00', '12:00', '13:00'] as $time) {
            $this->withToken($token)->postJson("/api/v1/bookings/{$code}/move", ['start' => qa4At($time)])->assertOk();
        }
        $this->withToken($token)->postJson("/api/v1/bookings/{$code}/move", ['start' => qa4At('14:00')])->assertStatus(422);
        expect(Booking::withoutGlobalScopes()->sole()->status)->toBe(BookingStatus::Pending);
    });

    it('refuses a forged VIP start to a regular client and accepts it from a VIP of the salon', function () {
        $vipOnly = Staff::factory()->for($this->salon)->working([5], '12:00', '14:00', ScheduleAccess::Vip)->create();
        $vipOnly->services()->attach($this->cut->id);
        $payload = ['services' => [['id' => $this->cut->id]], 'staff' => $vipOnly->id, 'start' => qa4At('12:00')];
        $token = $this->customer->createToken('x')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/salons/barberia-qa/bookings', $payload)->assertStatus(422);
        expect(Booking::withoutGlobalScopes()->count())->toBe(0);

        SalonClient::factory()->for($this->salon)->forUser($this->customer)->vip()->create();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/salons/barberia-qa/bookings', $payload)->assertCreated()->assertJsonPath('booking.isVip', true);
    });

    it('does not let the token of one account list, move or cancel the bookings of another', function () {
        $mine = qa4Book('10:00', user: $this->customer, source: BookingSource::Client);
        $other = User::factory()->create();
        $token = $other->createToken('x')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/bookings')->assertJsonCount(0, 'bookings');
        $this->withToken($token)->postJson("/api/v1/bookings/{$mine->code}/move", ['start' => qa4At('11:00')])->assertNotFound();
        $this->withToken($token)->postJson("/api/v1/bookings/{$mine->code}/cancel")->assertNotFound();
        expect($mine->refresh()->status)->toBe(BookingStatus::Confirmed);
    });
});
