<?php

use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\CreateBooking;
use App\Actions\Booking\NotifyStaff;
use App\Actions\Salon\CreateSalon;
use App\Data\Booking\NewBooking;
use App\Enums\BookingNotice;
use App\Enums\BookingSource;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\PushResult;
use App\Models\Booking;
use App\Models\PushSubscription;
use App\Models\SalonClient;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\BookingNotification;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\StaffBookingNotification;
use App\Support\Push\PushSender;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Step 6.5: notifications on the person's own devices. María books at
| Estudio Luz with Ana, who works there with her own account. "Now" is
| Wednesday 2026-10-07 08:00 in Lima.
*/

const FCM = 'https://fcm.googleapis.com/fcm/send/';

/** Records what would have gone to a push service. */
function fakePush(PushResult $answer = PushResult::Sent): object
{
    $sender = new class($answer) implements PushSender
    {
        /** @var list<array{endpoint: string, message: array<string, string>}> */
        public array $sent = [];

        public function __construct(private readonly PushResult $answer) {}

        public function isConfigured(): bool
        {
            return true;
        }

        public function send(PushSubscription $subscription, array $message): PushResult
        {
            $this->sent[] = ['endpoint' => $subscription->endpoint, 'message' => $message];

            return $this->answer;
        }
    };
    app()->instance(PushSender::class, $sender);

    return $sender;
}

function device(User $user, string $name): PushSubscription
{
    test()->actingAs($user)->postJson(route('push.store'), [
        'endpoint' => FCM.$name,
        'keys' => ['p256dh' => 'clave-publica-'.$name, 'auth' => 'secreto-'.$name],
    ])->assertCreated();

    return PushSubscription::query()->where('endpoint_hash', PushSubscription::hashOf(FCM.$name))->firstOrFail();
}

function pushBooking(string $at, BookingSource $source = BookingSource::Client): Booking
{
    return app(CreateBooking::class)->handle(new NewBooking(
        salon: test()->salon->refresh(),
        client: test()->client,
        selections: [['service_id' => test()->cut->id, 'variant_id' => null]],
        staff: test()->staff,
        startsAt: CarbonImmutable::parse($at, 'America/Lima'),
        source: $source,
    ));
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Estudio Luz', BusinessType::Unisex, Clientele::Everyone);
    $this->salon->forceFill(['timezone' => 'America/Lima', 'deposit_percent' => 0])->save();
    $this->ana = User::factory()->create(['name' => 'Ana Pérez']);
    $this->staff = Staff::factory()->for($this->salon)->linkedTo($this->ana)->working([3, 4, 5], '09:00', '18:00')->create(['name' => 'Ana']);
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'price_cents' => 3500, 'active_minutes' => 30]);
    $this->staff->services()->attach($this->cut->id);

    $this->maria = User::factory()->create(['name' => 'María Quispe']);
    $this->client = SalonClient::factory()->for($this->salon)->create(['user_id' => $this->maria->id, 'name' => 'María Quispe']);
});

it('keeps a device for whoever is signed in on it, and only real push services', function () {
    $device = device($this->maria, 'telefono');
    expect($device->user_id)->toBe($this->maria->id)->and($device->toArray())->not->toHaveKey('auth_token');

    // The same phone, another person signs in: it is theirs now.
    device($this->ana, 'telefono');
    expect(PushSubscription::query()->count())->toBe(1)
        ->and($device->refresh()->user_id)->toBe($this->ana->id);

    // The server posts to this address later: never to a host of the person's choosing.
    foreach (['https://example.com/push', 'http://fcm.googleapis.com/fcm/send/x', 'https://fcm.googleapis.com.evil.test/x', 'https://127.0.0.1/x'] as $endpoint) {
        $this->actingAs($this->maria)->postJson(route('push.store'), ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'a', 'auth' => 'b']])
            ->assertUnprocessable();
    }

    auth()->logout();
    $this->postJson(route('push.store'), ['endpoint' => FCM.'x', 'keys' => ['p256dh' => 'a', 'auth' => 'b']])->assertUnauthorized();
});

it('lets a person remove their own device, never another person\'s', function () {
    device($this->maria, 'telefono');

    $this->actingAs($this->ana)->deleteJson(route('push.destroy'), ['endpoint' => FCM.'telefono'])->assertOk();
    expect(PushSubscription::query()->count())->toBe(1);

    $this->actingAs($this->maria)->deleteJson(route('push.destroy'), ['endpoint' => FCM.'telefono'])->assertOk();
    expect(PushSubscription::query()->count())->toBe(0);
});

it('keeps at most ten devices per person', function () {
    foreach (range(1, PushSubscription::MAX_PER_USER + 2) as $number) {
        device($this->maria, "equipo-{$number}");
    }

    expect(PushSubscription::query()->where('user_id', $this->maria->id)->count())->toBe(PushSubscription::MAX_PER_USER)
        ->and(PushSubscription::query()->where('endpoint_hash', PushSubscription::hashOf(FCM.'equipo-1'))->exists())->toBeFalse();
});

it('sends the booking\'s news to every device of the client, besides the email', function () {
    device($this->maria, 'telefono');
    device($this->maria, 'tablet');
    device($this->ana, 'de-ana');
    $push = fakePush();

    $booking = pushBooking('2026-10-07 10:00', BookingSource::Staff);

    $toMaria = collect($push->sent)->filter(fn (array $sent): bool => ! str_ends_with($sent['endpoint'], 'de-ana'))->values();
    expect($toMaria)->toHaveCount(2)
        ->and($toMaria[0]['message']['title'])->toBe('Reserva confirmada en Estudio Luz')
        ->and($toMaria[0]['message']['body'])->toContain('Corte')
        ->and($toMaria[0]['message']['url'])->toBe(route('bookings.show', $booking->code))
        ->and($toMaria[0]['message']['tag'])->toBe('booking-'.$booking->code)
        ->and(PushSubscription::query()->whereNotNull('last_used_at')->count())->toBeGreaterThanOrEqual(2);
});

it('forgets a device the push service says is gone, and keeps one that only failed', function (PushResult $answer, int $left) {
    device($this->maria, 'telefono');
    $channel = new WebPushChannel(fakePush($answer));

    $channel->send($this->maria, new BookingNotification(BookingNotice::Confirmed, [
        'salon' => 'Estudio Luz', 'when' => 'Miércoles', 'services' => 'Corte', 'staff' => 'Ana', 'address' => null, 'url' => 'https://peluweb.test/mis-reservas/ABC',
    ]));

    expect(PushSubscription::query()->count())->toBe($left);
})->with([[PushResult::Gone, 0], [PushResult::Failed, 1]]);

it('tells the professional on their phone when their agenda changes, unless they changed it', function () {
    device($this->ana, 'de-ana');
    Notification::fake();

    // María books online with Ana.
    $this->actingAs($this->maria);
    $booking = pushBooking('2026-10-07 10:00');
    Notification::assertSentTo($this->ana, StaffBookingNotification::class, function (StaffBookingNotification $notification): bool {
        $message = $notification->toWebPush($this->ana);

        return $message['title'] === 'Cita confirmada'
            && str_contains($message['body'], 'María Quispe')
            && str_contains($message['body'], 'Corte')
            && str_contains($message['url'], '/agenda?fecha=2026-10-07');
    });

    // Ana cancels it herself: she does not need to be told.
    $this->actingAs($this->ana);
    app(ChangeBookingStatus::class)->cancel($booking, $this->ana, byClient: false);
    Notification::assertSentToTimes($this->ana, StaffBookingNotification::class, 1);
});

it('queues nothing for a professional without a device, nor for the client\'s courtesies', function () {
    Notification::fake();
    $this->actingAs($this->maria);
    pushBooking('2026-10-07 10:00');
    Notification::assertNotSentTo($this->ana, StaffBookingNotification::class);

    device($this->ana, 'de-ana');
    $this->actingAs($this->maria);
    Notification::fake();
    app(NotifyStaff::class)->handle(Booking::withoutGlobalScope('salon')->firstOrFail(), BookingNotice::Reminder);
    Notification::assertNothingSent();
});

it('offers the switch only where the server has its keys', function () {
    config(['services.webpush.public_key' => null, 'services.webpush.private_key' => null]);
    $this->actingAs($this->maria)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('features.pushKey', null));

    config(['services.webpush.public_key' => 'publica', 'services.webpush.private_key' => 'privada']);
    $this->actingAs($this->maria)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('features.pushKey', 'publica'));
    expect($this->actingAs($this->maria)->get(route('profile.edit'))->getContent())->not->toContain('privada');
});

it('generates a pair of keys to paste into the environment', function () {
    $this->artisan('webpush:keys')
        ->expectsOutputToContain('VAPID_PUBLIC_KEY=')
        ->expectsOutputToContain('VAPID_PRIVATE_KEY=')
        ->assertSuccessful();
});
