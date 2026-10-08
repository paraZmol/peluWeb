<?php

use App\Actions\Fortify\ResetUserPassword;
use App\Enums\BookingStatus;
use App\Enums\ScheduleAccess;
use App\Models\Booking;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

/*
| API v1 for the mobile app: tokens, what a salon offers, free times, and
| the account's own bookings. "Now" is Wednesday 2026-10-07 08:00 in Lima;
| Pedro works Wed–Fri 09:00–18:00.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create(['slug' => 'barberia-lucho', 'timezone' => 'America/Lima', 'phone' => '999888777']);
    $this->pedro = Staff::factory()->for($this->salon)->working([3, 4, 5], '09:00', '18:00')->create(['name' => 'Pedro']);
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'price_cents' => 3500, 'active_minutes' => 30]);
    $this->pedro->services()->attach($this->cut->id);
    $this->customer = User::factory()->create(['name' => 'Ana Cliente', 'email' => 'ana@example.com', 'password' => 'clave-segura-123']);
});

function apiAt(string $time, string $date = '2026-10-09'): int
{
    return CarbonImmutable::parse("{$date} {$time}", 'America/Lima')->getTimestamp();
}

function apiBookingPayload(string $time = '10:00', array $overrides = []): array
{
    return ['services' => [['id' => test()->cut->id]], 'staff' => test()->pedro->id, 'start' => apiAt($time), ...$overrides];
}

function apiBook(string $time = '10:00'): string
{
    return test()->postJson('/api/v1/salons/barberia-lucho/bookings', apiBookingPayload($time))->assertCreated()->json('booking.code');
}

describe('tokens', function () {
    it('hands out a token for the right password and nothing else', function () {
        $response = $this->postJson('/api/v1/login', ['email' => 'ANA@example.com', 'password' => 'clave-segura-123', 'device_name' => 'Pixel de Ana'])
            ->assertCreated()
            ->assertJsonPath('user.email', 'ana@example.com')
            ->assertJsonPath('user.emailVerified', true)
            ->assertJsonMissingPath('user.id')
            ->assertJsonMissingPath('user.password');

        $this->withToken($response->json('token'))->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.name', 'Ana Cliente');
        expect(PersonalAccessToken::query()->sole()->name)->toBe('Pixel de Ana');
    });

    it('answers the same for a wrong password and an unknown account', function () {
        $wrong = $this->postJson('/api/v1/login', ['email' => 'ana@example.com', 'password' => 'otra', 'device_name' => 'x'])->assertStatus(422);
        $unknown = $this->postJson('/api/v1/login', ['email' => 'nadie@example.com', 'password' => 'otra', 'device_name' => 'x'])->assertStatus(422);

        expect($wrong->json('errors'))->toBe($unknown->json('errors'))
            ->and(PersonalAccessToken::query()->count())->toBe(0);
    });

    it('sends accounts with two-step verification to the web', function () {
        $this->customer->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();

        $this->postJson('/api/v1/login', ['email' => 'ana@example.com', 'password' => 'clave-segura-123', 'device_name' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('email');

        expect(PersonalAccessToken::query()->count())->toBe(0);
    });

    it('gives no token to an unverified account nor to the platform admin', function () {
        User::factory()->unverified()->create(['email' => 'nueva@example.com', 'password' => 'clave-segura-123']);
        User::factory()->create(['email' => 'admin@example.com', 'password' => 'clave-segura-123'])->forceFill(['is_platform_admin' => true])->save();

        $this->postJson('/api/v1/login', ['email' => 'nueva@example.com', 'password' => 'clave-segura-123', 'device_name' => 'x'])->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/login', ['email' => 'admin@example.com', 'password' => 'clave-segura-123', 'device_name' => 'x'])->assertJsonValidationErrors('email');

        expect(PersonalAccessToken::query()->count())->toBe(0);
    });

    it('voids every token when the password changes or a second factor is turned on', function () {
        $this->customer->createToken('Teléfono');
        $this->customer->createToken('Tablet');

        $this->customer->forceFill(['name' => 'Ana C.'])->save();
        expect(PersonalAccessToken::query()->count())->toBe(2);

        $this->customer->forceFill(['password' => 'otra-clave-segura-456'])->save();
        expect(PersonalAccessToken::query()->count())->toBe(0);

        $this->customer->createToken('Teléfono');
        $this->customer->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
        expect(PersonalAccessToken::query()->count())->toBe(0);
    });

    it('voids the tokens of whoever held an email once its owner resets the password', function () {
        $token = $this->customer->createToken('Atacante')->plainTextToken;

        app(ResetUserPassword::class)->reset($this->customer, ['password' => 'Nueva-clave-segura-789', 'password_confirmation' => 'Nueva-clave-segura-789']);

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    });

    it('keeps at most ten devices signed in', function () {
        foreach (range(1, 12) as $device) {
            $this->app['cache']->clear();
            $this->postJson('/api/v1/login', ['email' => 'ana@example.com', 'password' => 'clave-segura-123', 'device_name' => "Equipo {$device}"])->assertCreated();
        }

        expect(PersonalAccessToken::query()->count())->toBe(10)
            ->and(PersonalAccessToken::query()->pluck('name'))->not->toContain('Equipo 1')->toContain('Equipo 12');
    });

    it('slows down guessing one account from many addresses', function () {
        foreach (range(1, 5) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$attempt}"])
                ->postJson('/api/v1/login', ['email' => 'ana@example.com', 'password' => 'mal', 'device_name' => 'x'])->assertStatus(422);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->postJson('/api/v1/login', ['email' => 'Ana@Example.com', 'password' => 'clave-segura-123', 'device_name' => 'x'])->assertStatus(429);
    });

    it('validates the request and slows down guessing', function () {
        $this->postJson('/api/v1/login', [])->assertStatus(422)->assertJsonValidationErrors(['email', 'password', 'device_name']);

        foreach (range(1, 4) as $attempt) {
            $this->postJson('/api/v1/login', ['email' => 'ana@example.com', 'password' => 'mal', 'device_name' => 'x'])->assertStatus(422);
        }

        $this->postJson('/api/v1/login', ['email' => 'ana@example.com', 'password' => 'clave-segura-123', 'device_name' => 'x'])->assertStatus(429);
    });

    it('signs out only the device that asks', function () {
        $phone = $this->customer->createToken('Teléfono')->plainTextToken;
        $tablet = $this->customer->createToken('Tablet')->plainTextToken;

        $this->withToken($phone)->postJson('/api/v1/logout')->assertNoContent();

        expect(PersonalAccessToken::query()->pluck('name')->all())->toBe(['Tablet']);
        $this->app['auth']->forgetGuards();
        $this->withToken($phone)->getJson('/api/v1/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($tablet)->getJson('/api/v1/me')->assertOk();
    });

    it('needs a token, and a web session is not one', function () {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/bookings')->assertUnauthorized();
        $this->postJson('/api/v1/salons/barberia-lucho/bookings', apiBookingPayload())->assertUnauthorized();
        $this->get('/api/v1/bookings')->assertUnauthorized();
        $this->get('/api/v1/me')->assertUnauthorized()->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($this->customer, 'web')->getJson('/api/v1/bookings')->assertUnauthorized();
    });

    it('stops working when it expires', function () {
        $token = $this->customer->createToken('Teléfono')->plainTextToken;

        $this->travel(91)->days();

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    });
});

describe('a salon and its free times', function () {
    it('describes what can be booked, to anyone', function () {
        $this->getJson('/api/v1/salons/barberia-lucho')
            ->assertOk()
            ->assertJsonPath('salon.name', $this->salon->name)
            ->assertJsonPath('salon.staff.0.name', 'Pedro')
            ->assertJsonPath('salon.serviceGroups.0.services.0.price', '35.00')
            ->assertJsonPath('isOpen', true)
            ->assertJsonCount(61, 'days')
            ->assertJsonMissingPath('salon.id');
    });

    it('lists the free starts of a day with the quote', function () {
        $response = $this->getJson('/api/v1/salons/barberia-lucho/availability?'.http_build_query(['date' => '2026-10-09', 'services' => [['id' => $this->cut->id]]]))
            ->assertOk()
            ->assertJsonPath('slots.0', ['start' => apiAt('09:00'), 'time' => '09:00'])
            ->assertJsonPath('quote.price', '35.00');

        expect(collect($response->json('slots'))->pluck('time'))->toContain('17:30')->not->toContain('18:00');
    });

    it('never sends VIP or team-only starts without the token of a VIP of this salon', function () {
        $vip = Staff::factory()->for($this->salon)
            ->working([5], '12:00', '14:00', ScheduleAccess::Vip)
            ->working([5], '20:00', '21:00', ScheduleAccess::StaffOnly)
            ->create();
        $vip->services()->attach($this->cut->id);
        $url = '/api/v1/salons/barberia-lucho/availability?'.http_build_query(['date' => '2026-10-09', 'staff' => $vip->id, 'services' => [['id' => $this->cut->id]]]);

        $this->getJson($url)->assertJsonCount(0, 'slots');
        Sanctum::actingAs($this->customer);
        $this->getJson($url)->assertJsonCount(0, 'slots');
        // The owner of the salon is not a VIP client either.
        Sanctum::actingAs($this->owner);
        $this->getJson($url)->assertJsonCount(0, 'slots');

        SalonClient::factory()->for($this->salon)->forUser($this->customer)->vip()->create();
        Sanctum::actingAs($this->customer);
        $times = collect($this->getJson($url.'&level=team&is_vip=1')->json('slots'))->pluck('time');

        expect($times)->toContain('12:00')->not->toContain('20:00');
    });

    it('hides a suspended salon and answers bad input with 422, as JSON', function () {
        $this->getJson('/api/v1/salons/barberia-lucho/availability')->assertStatus(422)->assertJsonValidationErrors(['date', 'services']);
        $this->get('/api/v1/salons/barberia-lucho/availability')->assertStatus(422)->assertHeader('content-type', 'application/json');
        $this->getJson('/api/v1/salons/no-existe')->assertNotFound();

        $this->salon->forceFill(['status' => 'suspended'])->save();
        $this->getJson('/api/v1/salons/barberia-lucho')->assertNotFound();
        $this->getJson('/api/v1/salons/barberia-lucho/availability?'.http_build_query(['date' => '2026-10-09', 'services' => [['id' => $this->cut->id]]]))->assertNotFound();
    });
});

describe('the account\'s bookings', function () {
    beforeEach(function () {
        Sanctum::actingAs($this->customer);
    });

    it('books, lists, shows, moves and cancels', function () {
        $code = apiBook('10:00');

        $booking = Booking::withoutGlobalScopes()->sole();
        expect($booking->status)->toBe(BookingStatus::Confirmed)
            ->and($booking->created_by)->toBe($this->customer->id)
            ->and(SalonClient::withoutGlobalScopes()->sole()->user_id)->toBe($this->customer->id);

        $this->getJson('/api/v1/bookings')->assertOk()->assertJsonCount(1, 'bookings')->assertJsonPath('bookings.0.code', $code);
        $this->getJson("/api/v1/bookings/{$code}")->assertOk()
            ->assertJsonPath('booking.date', '2026-10-09')
            ->assertJsonPath('booking.time', '10:00')
            ->assertJsonPath('booking.staffName', 'Pedro')
            ->assertJsonPath('booking.canMove', true)
            ->assertJsonMissingPath('booking.id');

        $slots = collect($this->getJson("/api/v1/bookings/{$code}/slots?date=2026-10-09")->assertOk()->json('slots'))->pluck('time');
        expect($slots)->toContain('10:00')->toContain('16:00');

        $this->postJson("/api/v1/bookings/{$code}/move", ['start' => apiAt('16:00'), 'staff' => 999, 'byClient' => false])
            ->assertOk()->assertJsonPath('booking.time', '16:00');
        $this->postJson("/api/v1/bookings/{$code}/cancel")->assertOk()->assertJsonPath('booking.status', 'cancelled');
    });

    it('answers a business rule with 422 and a message', function () {
        apiBook('10:00');

        $this->postJson('/api/v1/salons/barberia-lucho/bookings', apiBookingPayload('10:15'))
            ->assertStatus(422)->assertJsonPath('message', 'Ese horario se acaba de ocupar. Elige otro, por favor.');
        $this->post('/api/v1/salons/barberia-lucho/bookings', apiBookingPayload('10:15'))->assertStatus(422);
        $this->postJson('/api/v1/salons/barberia-lucho/bookings', apiBookingPayload('10:00', ['start' => null]))->assertJsonValidationErrors('start');

        expect(Booking::withoutGlobalScopes()->count())->toBe(1);
    });

    it('asks for the deposit like the web does and ignores forged fields', function () {
        $this->salon->forceFill(['deposit_percent' => 30])->save();

        $this->postJson('/api/v1/salons/barberia-lucho/bookings', apiBookingPayload('10:00', ['status' => 'confirmed', 'deposit_cents' => 0, 'source' => 'staff', 'is_vip' => true]))
            ->assertCreated()
            ->assertJsonPath('booking.status', 'pending')
            ->assertJsonPath('booking.deposit.amount', '10.50')
            ->assertJsonPath('booking.isVip', false);
    });

    it('never shows or changes the bookings of another account', function () {
        $code = apiBook('10:00');
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/bookings')->assertJsonCount(0, 'bookings');
        $this->getJson("/api/v1/bookings/{$code}")->assertNotFound();
        $this->getJson("/api/v1/bookings/{$code}/slots?date=2026-10-09")->assertNotFound();
        $this->postJson("/api/v1/bookings/{$code}/move", ['start' => apiAt('16:00')])->assertNotFound();
        $this->postJson("/api/v1/bookings/{$code}/cancel")->assertNotFound();
        // Not even the salon's owner: the API is the client's side.
        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v1/bookings/{$code}/cancel")->assertNotFound();

        expect(Booking::withoutGlobalScopes()->sole()->status)->toBe(BookingStatus::Confirmed);
    });

    it('does not accept a professional or a service of another salon', function () {
        $other = Salon::factory()->create();
        $foreignStaff = Staff::factory()->for($other)->working([5])->create();
        $foreignService = Service::factory()->for($other)->create();

        $this->postJson('/api/v1/salons/barberia-lucho/bookings', apiBookingPayload('10:00', ['staff' => $foreignStaff->id]))->assertStatus(422);
        $this->postJson('/api/v1/salons/barberia-lucho/bookings', apiBookingPayload('10:00', ['services' => [['id' => $foreignService->id]]]))->assertStatus(422);

        expect(Booking::withoutGlobalScopes()->count())->toBe(0);
    });

    it('needs a verified email to book', function () {
        Sanctum::actingAs(User::factory()->unverified()->create());

        $this->postJson('/api/v1/salons/barberia-lucho/bookings', apiBookingPayload())->assertForbidden();
        $this->getJson('/api/v1/bookings')->assertForbidden();
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.emailVerified', false);
    });

    it('refuses to book in a paused or suspended salon', function () {
        $this->salon->forceFill(['accepts_online_bookings' => false])->save();
        $this->postJson('/api/v1/salons/barberia-lucho/bookings', apiBookingPayload())->assertStatus(422);

        $this->salon->forceFill(['status' => 'suspended'])->save();
        $this->postJson('/api/v1/salons/barberia-lucho/bookings', apiBookingPayload())->assertNotFound();

        expect(Booking::withoutGlobalScopes()->count())->toBe(0);
    });
});
