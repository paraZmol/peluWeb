<?php

use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\CreateBooking;
use App\Actions\Reviews\LeaveReview;
use App\Actions\Salon\CreateSalon;
use App\Data\Booking\NewBooking;
use App\Enums\BookingNotice;
use App\Enums\BookingSource;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\ReviewMode;
use App\Enums\SalonPermission;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Models\Booking;
use App\Models\Review;
use App\Models\SalonClient;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\BookingNotification;
use App\Support\CurrentSalon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

/*
| Step 6.2: reviews. María Fernanda booked with her account and was
| attended on Wednesday 2026-10-07 at 10:00 (Lima) by Ana.
*/

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Estudio Luz', BusinessType::Unisex, Clientele::Everyone);
    $this->salon->forceFill(['timezone' => 'America/Lima', 'deposit_percent' => 0])->save();
    $this->staff = Staff::factory()->for($this->salon)->working([3, 4, 5], '09:00', '18:00')->create(['name' => 'Ana']);
    $this->cut = Service::factory()->for($this->salon)->create(['price_cents' => 3500, 'active_minutes' => 30]);
    $this->staff->services()->attach($this->cut->id);

    $this->maria = User::factory()->create(['name' => 'María Fernanda Quispe']);
    $this->client = SalonClient::factory()->for($this->salon)->create(['user_id' => $this->maria->id]);
    $this->visit = reviewVisit('2026-10-07 10:00');
});

function reviewBooking(string $at, ?SalonClient $client = null): Booking
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

function reviewVisit(string $at, ?SalonClient $client = null): Booking
{
    $booking = reviewBooking($at, $client);
    test()->travelTo(CarbonImmutable::parse($at, 'America/Lima'));
    $status = app(ChangeBookingStatus::class);
    $status->start($booking, test()->owner);

    return $status->complete($booking, test()->owner);
}

function leaveReview(Booking $booking, int $rating = 5, ?string $comment = 'Me encantó el corte'): TestResponse
{
    return test()->actingAs(test()->maria)->post(route('bookings.review.store', $booking->code), ['rating' => $rating, 'comment' => $comment]);
}

it('asks for the review by email once the visit is completed', function () {
    Notification::assertSentTo($this->maria, BookingNotification::class, fn (BookingNotification $notification): bool => $notification->notice === BookingNotice::ReviewRequest
        && $notification->toMail($this->maria)->actionText === 'Dejar mi reseña');
});

it('does not ask when the salon keeps reviews hidden or the person switched courtesy mails off', function (Closure $arrange) {
    $arrange();

    reviewVisit('2026-10-08 10:00');

    // Only the one of the first visit, asked before anything changed.
    $requests = Notification::sent($this->maria, BookingNotification::class, fn (BookingNotification $notification): bool => $notification->notice === BookingNotice::ReviewRequest);
    expect($requests)->toHaveCount(1);
})->with([
    'hidden' => [fn () => test()->salon->forceFill(['review_mode' => ReviewMode::Hidden])->save()],
    'opted out' => [fn () => test()->maria->forceFill(['booking_reminders' => false])->save()],
]);

it('takes one review per completed visit, signed with the first name only', function () {
    $this->actingAs($this->maria)->get(route('bookings.show', $this->visit->code))
        ->assertInertia(fn (Assert $page) => $page->where('booking.canReview', true)->where('booking.review', null)->where('booking.reviewIsPublic', false));

    leaveReview($this->visit, 4)->assertSessionHasNoErrors();
    $review = Review::withoutGlobalScope('salon')->sole();

    expect($review->rating)->toBe(4)
        ->and($review->comment)->toBe('Me encantó el corte')
        ->and($review->author_name)->toBe('María')
        ->and($review->staff_id)->toBe($this->staff->id)
        ->and($review->salon_id)->toBe($this->salon->id);

    leaveReview($this->visit, 1)->assertInertiaFlash('toast.type', 'error');
    expect(Review::withoutGlobalScope('salon')->count())->toBe(1)->and($review->refresh()->rating)->toBe(4);

    $this->actingAs($this->maria)->get(route('bookings.show', $this->visit->code))
        ->assertInertia(fn (Assert $page) => $page->where('booking.canReview', false)->where('booking.review.rating', 4));
});

it('refuses a review of a visit that did not happen, is too old, or of a salon that takes none', function (Closure $arrange) {
    $booking = $arrange();

    leaveReview($booking)->assertInertiaFlash('toast.type', 'error');

    expect(Review::withoutGlobalScope('salon')->count())->toBe(0);
})->with([
    'not completed' => [fn () => reviewBooking('2026-10-08 10:00')],
    'cancelled' => [function () {
        $booking = reviewBooking('2026-10-08 11:00');

        return app(ChangeBookingStatus::class)->cancel($booking, test()->owner, byClient: false);
    }],
    'too old' => [function () {
        test()->travelTo(CarbonImmutable::parse('2026-12-10 10:00', 'America/Lima'));

        return test()->visit;
    }],
    'hidden mode' => [function () {
        test()->salon->forceFill(['review_mode' => ReviewMode::Hidden])->save();

        return test()->visit;
    }],
]);

it('rejects ratings and comments that are not what they should be', function (array $payload, string $field) {
    $this->actingAs($this->maria)->post(route('bookings.review.store', $this->visit->code), $payload)->assertSessionHasErrors($field);
})->with([
    'no rating' => [['comment' => 'x'], 'rating'],
    'zero' => [['rating' => 0], 'rating'],
    'six' => [['rating' => 6], 'rating'],
    'half' => [['rating' => '4.5'], 'rating'],
    'a list' => [['rating' => [5]], 'rating'],
    'too long' => [['rating' => 5, 'comment' => str_repeat('a', 601)], 'comment'],
    'comment as list' => [['rating' => 5, 'comment' => ['a']], 'comment'],
]);

it('lets only the person who made the visit review it or take the review back', function () {
    $other = User::factory()->create();
    SalonClient::factory()->for($this->salon)->create(['user_id' => $other->id]);

    $this->actingAs($other)->post(route('bookings.review.store', $this->visit->code), ['rating' => 1])->assertNotFound();
    $this->actingAs($this->owner)->post(route('bookings.review.store', $this->visit->code), ['rating' => 5])->assertNotFound();

    leaveReview($this->visit);
    $this->actingAs($other)->delete(route('bookings.review.destroy', $this->visit->code))->assertNotFound();
    expect(Review::withoutGlobalScope('salon')->count())->toBe(1);

    $this->actingAs($this->maria)->delete(route('bookings.review.destroy', $this->visit->code))->assertSessionHasNoErrors();
    $review = Review::withoutGlobalScope('salon')->sole();
    expect($review->withdrawn_at)->not->toBeNull()->and($review->comment)->toBeNull()->and($review->author_name)->toBe('Cliente');

    // Taken back is final: the visit cannot be reviewed again to dodge a report or the salon's answer.
    leaveReview($this->visit, 5, 'Otra vez')->assertInertiaFlash('toast.type', 'error');
    $this->actingAs($this->maria)->get(route('bookings.show', $this->visit->code))
        ->assertInertia(fn (Assert $page) => $page->where('booking.review', null)->where('booking.canReview', false)->where('booking.reviewWithdrawn', true));
    $this->actingAs($this->owner)->get(route('reviews.index'))->assertInertia(fn (Assert $page) => $page->where('reviews.count', 0)->has('reviews.items', 0));
});

it('shows reviews on the salon\'s page only in public mode, all of them, with the average', function () {
    // Written while private: its author was told only the salon would read it.
    leaveReview($this->visit, 5, 'Excelente');
    $mine = fn () => $this->actingAs($this->maria)->get(route('bookings.show', $this->visit->code));
    $mine()->assertInertia(fn (Assert $page) => $page->where('booking.review.isPublished', false));
    $this->get(route('salons.show', $this->salon))->assertInertia(fn (Assert $page) => $page->where('reviews', null));

    $this->actingAs($this->owner)->put(route('reviews.mode.update'), ['mode' => 'public'])->assertSessionHasNoErrors();
    $mine()->assertInertia(fn (Assert $page) => $page->where('booking.review.isPublished', false));
    $second = reviewVisit('2026-10-08 10:00');
    leaveReview($second, 2, '<script>alert(1)</script> Llegué y esperé 40 minutos');
    $third = reviewVisit('2026-10-09 10:00');
    leaveReview($third, 5, null);
    auth()->logout();

    $this->get(route('salons.show', $this->salon))->assertInertia(fn (Assert $page) => $page
        // The private one stays out of the page and of the average.
        ->where('reviews.count', 2)
        ->where('reviews.average', 3.5)
        ->where('reviews.items.0.rating', 5)
        ->where('reviews.items.0.comment', null)
        ->where('reviews.items.1.rating', 2)
        ->where('reviews.items.1.author', 'María')
        // Stored as written; React shows it as text.
        ->where('reviews.items.1.comment', '<script>alert(1)</script> Llegué y esperé 40 minutos')
        ->missing('reviews.items.0.staffName')
        ->missing('reviews.items.0.isReported'));

    $this->actingAs($this->maria)->get(route('bookings.show', $second->code))
        ->assertInertia(fn (Assert $page) => $page->where('booking.reviewIsPublic', true));
});

it('lets the salon answer and report, but never delete or edit a review', function () {
    leaveReview($this->visit, 1, 'Pésimo');
    $review = Review::withoutGlobalScope('salon')->sole();

    $this->actingAs($this->owner)->put(route('reviews.reply', $review), ['reply' => 'Lamentamos la espera. Te escribimos para compensarlo.'])
        ->assertSessionHasNoErrors();
    expect($review->refresh()->reply)->toBe('Lamentamos la espera. Te escribimos para compensarlo.')
        ->and($review->replied_by)->toBe($this->owner->id);

    $this->actingAs($this->owner)->post(route('reviews.report', $review), ['reason' => 'corto'])->assertSessionHasErrors('reason');
    $this->actingAs($this->owner)->post(route('reviews.report', $review), ['reason' => 'Insulta a una persona del equipo con su nombre.'])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->post(route('reviews.report', $review), ['reason' => 'Otra vez el mismo reporte para insistir.'])
        ->assertInertiaFlash('toast.type', 'error');

    $this->actingAs($this->owner)->get(route('reviews.index'))->assertInertia(fn (Assert $page) => $page
        ->component('owner/reviews/index')
        ->where('mode', 'private')
        ->where('reviews.count', 1)
        ->where('reviews.items.0.isReported', true)
        ->where('reviews.items.0.isHidden', false)
        ->where('reviews.items.0.staffName', 'Ana'));

    // No route deletes or edits it for the salon.
    expect($this->actingAs($this->owner)->delete("/resenas/{$review->id}")->status())->toBeIn([404, 405])
        ->and($this->actingAs($this->owner)->put("/resenas/{$review->id}", ['rating' => 5])->status())->toBeIn([404, 405]);
    expect($review->refresh()->rating)->toBe(1)->and($review->comment)->toBe('Pésimo');

    // Emptying the answer takes it back.
    $this->actingAs($this->owner)->put(route('reviews.reply', $review), ['reply' => ''])->assertSessionHasNoErrors();
    expect($review->refresh()->reply)->toBeNull()->and($review->replied_at)->toBeNull();
});

it('keeps reviews for whoever edits the salon\'s profile, and away from other salons', function () {
    leaveReview($this->visit);
    $review = Review::withoutGlobalScope('salon')->sole();
    $barber = SalonMember::factory()->for($this->salon)->create();
    $editor = SalonMember::factory()->for($this->salon)->withPermissions(SalonPermission::ManageSalonProfile)->create();

    $this->actingAs($barber->user)->get(route('reviews.index'))->assertForbidden();
    $this->actingAs($barber->user)->put(route('reviews.reply', $review), ['reply' => 'x'])->assertForbidden();
    $this->actingAs($barber->user)->put(route('reviews.mode.update'), ['mode' => 'hidden'])->assertForbidden();
    $this->actingAs($editor->user)->get(route('reviews.index'))->assertOk();

    $intruder = User::factory()->create();
    app(CreateSalon::class)->handle($intruder, 'Otro Salón', BusinessType::Barbershop, Clientele::Men);
    $this->actingAs($intruder)->put(route('reviews.reply', $review), ['reply' => 'Hola'])->assertNotFound();
    $this->actingAs($intruder)->post(route('reviews.report', $review), ['reason' => 'No me gusta esta reseña ajena.'])->assertNotFound();
    $this->actingAs($intruder)->get(route('reviews.index'))->assertInertia(fn (Assert $page) => $page->where('reviews.count', 0));
    $this->actingAs($intruder)->put(route('reviews.mode.update'), ['mode' => 'todas'])->assertSessionHasErrors('mode');

    expect($review->refresh()->reply)->toBeNull()->and($review->reported_at)->toBeNull()
        ->and($this->salon->refresh()->review_mode)->toBe(ReviewMode::Private);
});

it('lets only the platform hide a reported review, and takes it out of the page and the average', function () {
    $this->salon->forceFill(['review_mode' => ReviewMode::Public])->save();
    leaveReview($this->visit, 5, 'Excelente');
    $abusive = reviewVisit('2026-10-08 10:00');
    leaveReview($abusive, 1, 'Insulto');
    $review = Review::withoutGlobalScope('salon')->where('booking_id', $abusive->id)->sole();
    $this->actingAs($this->owner)->post(route('reviews.report', $review), ['reason' => 'Insulta a una persona del equipo.']);

    $this->actingAs($this->owner)->get('/plataforma/reviews')->assertForbidden();

    $admin = User::factory()->platformAdmin()->withTwoFactor()->create();
    $this->actingAs($admin)->get('/plataforma/reviews')->assertOk();
    Livewire::actingAs($admin)->test(ListReviews::class)
        ->assertCanSeeTableRecords([$review])
        ->callTableAction('hide', $review);

    expect($review->refresh()->hidden_at)->not->toBeNull()->and($review->report_resolved_at)->not->toBeNull();

    auth()->logout();
    $this->get(route('salons.show', $this->salon))->assertInertia(fn (Assert $page) => $page
        ->where('reviews.count', 1)
        ->where('reviews.average', 5)
        ->has('reviews.items', 1));
    // The salon still sees it, marked as hidden.
    $this->actingAs($this->owner)->get(route('reviews.index'))->assertInertia(fn (Assert $page) => $page
        ->where('reviews.count', 1)
        ->has('reviews.items', 2)
        ->where('reviews.items.0.isHidden', true));

    // Hidden stays hidden: its author cannot take it back and write it again.
    $this->actingAs($this->maria)->delete(route('bookings.review.destroy', $abusive->code));
    $this->actingAs($this->maria)->post(route('bookings.review.store', $abusive->code), ['rating' => 1, 'comment' => 'Insulto'])
        ->assertInertiaFlash('toast.type', 'error');
    expect(Review::withoutGlobalScope('salon')->where('booking_id', $abusive->id)->count())->toBe(1);
});

it('leaves the opinion without the name when the account is deleted', function () {
    leaveReview($this->visit);

    $this->actingAs($this->maria)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');

    expect(Review::withoutGlobalScope('salon')->sole()->author_name)->toBe(Review::ANONYMOUS);
});

it('signs with a first name only, or with nothing that is not a name', function (string $account, string $signature) {
    expect(LeaveReview::firstName($account))->toBe($signature);
})->with([
    ['María Fernanda Quispe', 'María'],
    ["Jean-Luc O'Neill", 'Jean-Luc'],
    ["Ana\tPérez", 'Ana'],
    ["Ana\u{00A0}Pérez", 'Ana'],
    ['juan.perez@example.com', 'Cliente'],
    ['987654321', 'Cliente'],
    ['', 'Cliente'],
]);

it('lets the platform see every salon even when its admin belongs to one', function () {
    leaveReview($this->visit);
    $admin = User::factory()->platformAdmin()->create();
    $own = app(CreateSalon::class)->handle($admin, 'Salón del Admin', BusinessType::Barbershop, Clientele::Men);

    $current = app(CurrentSalon::class);
    $current->set($own);
    expect(Review::query()->count())->toBe(0);

    $current->bypass();
    expect(Review::query()->count())->toBe(1);
});
