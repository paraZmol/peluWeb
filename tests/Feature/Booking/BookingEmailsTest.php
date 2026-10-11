<?php

use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\CreateBooking;
use App\Actions\Booking\RescheduleBooking;
use App\Actions\Salon\CreateSalon;
use App\Data\Booking\BookingMove;
use App\Data\Booking\NewBooking;
use App\Enums\BookingNotice;
use App\Enums\BookingSource;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Models\Booking;
use App\Models\SalonClient;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\BookingNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

/*
| Step 6.3: what the client is told by email about their booking. "Now" is
| Monday 2026-10-05 08:00 in Lima; the cut costs S/ 35.00.
*/

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Estudio Luz', BusinessType::Unisex, Clientele::Everyone);
    $this->salon->forceFill(['timezone' => 'America/Lima', 'deposit_percent' => 0, 'address' => 'Av. Larco 123', 'district' => 'Miraflores'])->save();
    $this->staff = Staff::factory()->for($this->salon)->working([1, 2, 3, 4, 5], '09:00', '18:00')->create(['name' => 'Ana']);
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'price_cents' => 3500, 'active_minutes' => 30]);
    $this->staff->services()->attach($this->cut->id);

    $this->user = User::factory()->create();
    $this->client = SalonClient::factory()->for($this->salon)->create(['user_id' => $this->user->id]);
});

function mailBooking(string $at, ?SalonClient $client = null): Booking
{
    return app(CreateBooking::class)->handle(new NewBooking(
        salon: test()->salon->refresh(),
        client: $client ?? test()->client,
        selections: [['service_id' => test()->cut->id, 'variant_id' => null]],
        staff: test()->staff,
        startsAt: CarbonImmutable::parse($at, 'America/Lima'),
        source: BookingSource::Client,
    ));
}

/**
 * @return list<BookingNotice>
 */
function mailNotices(): array
{
    return Notification::sent(test()->user, BookingNotification::class)
        ->map(fn (BookingNotification $notification): BookingNotice => $notification->notice)
        ->values()->all();
}

it('confirms a booking that needs no deposit, saying where and when', function () {
    mailBooking('2026-10-07 10:00');

    Notification::assertSentTo($this->user, BookingNotification::class, function (BookingNotification $notification): bool {
        $mail = $notification->toMail($this->user);

        return $notification->notice === BookingNotice::Confirmed
            && $mail->subject === 'Reserva confirmada en Estudio Luz'
            && $notification->booking['when'] === 'Miércoles 7 de octubre de 2026, 10:00 a. m.'
            && $notification->booking['services'] === 'Corte'
            && $notification->booking['staff'] === 'Ana'
            && $notification->booking['address'] === 'Av. Larco 123, Miraflores'
            && str_contains($mail->actionUrl, '/mis-reservas/')
            && $notification->notes === [];
    });
});

it('asks for the deposit first and confirms once it arrives', function () {
    $this->salon->forceFill(['deposit_percent' => 30])->save();

    $booking = mailBooking('2026-10-07 10:00');

    Notification::assertSentTo($this->user, BookingNotification::class, fn (BookingNotification $notification): bool => $notification->notice === BookingNotice::Received
        && str_starts_with($notification->notes[0], 'Seña: S/ 10.50. Envíala antes de las '));

    app(ChangeBookingStatus::class)->confirm($booking, $this->owner);

    expect(mailNotices())->toBe([BookingNotice::Received, BookingNotice::Confirmed]);
    Notification::assertSentTo($this->user, BookingNotification::class, fn (BookingNotification $notification): bool => $notification->notice === BookingNotice::Confirmed
        && $notification->notes === ['Seña recibida: S/ 10.50.']);
});

it('tells about a cancellation and what happens with the deposit', function (bool $byClient, string $at, string $note) {
    $this->salon->forceFill(['deposit_percent' => 30, 'cancel_window_hours' => 24])->save();
    $booking = app(ChangeBookingStatus::class)->confirm(mailBooking($at), $this->owner);

    app(ChangeBookingStatus::class)->cancel($booking, $byClient ? $this->user : $this->owner, byClient: $byClient);

    Notification::assertSentTo($this->user, BookingNotification::class, fn (BookingNotification $notification): bool => $notification->notice === BookingNotice::Cancelled
        && $notification->notes === [$note]);
})->with([
    'the salon cancels' => [false, '2026-10-07 10:00', 'El salón te devolverá la seña de S/ 10.50.'],
    'the client cancels in time' => [true, '2026-10-07 10:00', 'El salón te devolverá la seña de S/ 10.50.'],
    'the client cancels late' => [true, '2026-10-05 16:00', 'La seña de S/ 10.50 no se devuelve: la cancelación llegó fuera del plazo del salón.'],
]);

it('tells the new time when a booking is moved', function () {
    $booking = mailBooking('2026-10-07 10:00');

    app(RescheduleBooking::class)->handle(new BookingMove($booking, CarbonImmutable::parse('2026-10-08 15:30', 'America/Lima'), byClient: false, actor: $this->owner));

    Notification::assertSentTo($this->user, BookingNotification::class, fn (BookingNotification $notification): bool => $notification->notice === BookingNotice::Moved
        && $notification->booking['when'] === 'Jueves 8 de octubre de 2026, 3:30 p. m.');
});

it('writes to nobody without an account or without a verified address', function () {
    mailBooking('2026-10-07 10:00', SalonClient::factory()->for($this->salon)->create());
    $unverified = User::factory()->unverified()->create();
    mailBooking('2026-10-07 11:00', SalonClient::factory()->for($this->salon)->create(['user_id' => $unverified->id]));

    Notification::assertNothingSent();
});

it('reminds once, the day before, only who booked with enough notice', function () {
    $early = mailBooking('2026-10-07 10:00');
    $this->travelTo(CarbonImmutable::parse('2026-10-06 09:00', 'America/Lima'));
    $lastMinute = mailBooking('2026-10-07 09:30');
    $cancelled = mailBooking('2026-10-07 15:00');
    app(ChangeBookingStatus::class)->cancel($cancelled, $this->owner, byClient: false);

    // 25 hours before: not yet.
    $this->artisan('bookings:send-reminders')->assertSuccessful();
    expect(mailNotices())->not->toContain(BookingNotice::Reminder);

    $this->travelTo(CarbonImmutable::parse('2026-10-06 10:15', 'America/Lima'));
    $this->artisan('bookings:send-reminders')->assertSuccessful();
    $this->artisan('bookings:send-reminders')->assertSuccessful();

    expect(array_values(array_filter(mailNotices(), fn (BookingNotice $notice): bool => $notice === BookingNotice::Reminder)))->toHaveCount(1)
        ->and($early->refresh()->reminder_sent_at)->not->toBeNull()
        // Booked the day before: marked, so it is not looked at again, but not written to.
        ->and($lastMinute->refresh()->reminder_sent_at)->not->toBeNull()
        ->and($cancelled->refresh()->reminder_sent_at)->toBeNull();
});

it('renders the mail without leaking markdown from a salon\'s name', function () {
    $this->salon->forceFill(['name' => 'Estudio *Luz* [x](http://evil.test)'])->save();
    mailBooking('2026-10-07 10:00');

    Notification::assertSentTo($this->user, BookingNotification::class, function (BookingNotification $notification): bool {
        $html = (string) $notification->toMail($this->user)->render();

        return ! str_contains($html, 'href="http://evil.test"') && ! str_contains($html, '<em>Luz</em>');
    });
});
