<?php

use App\Actions\Booking\CreateBooking;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Step 4.9: "Repetir cita" and the reference photo ("Mi estilo").
| "Now" is Wednesday 2026-10-07 08:00 in Lima; Pedro works Wed–Fri.
*/

beforeEach(function () {
    Storage::fake('public');
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create(['slug' => 'barberia-lucho', 'timezone' => 'America/Lima']);
    $this->pedro = Staff::factory()->for($this->salon)->working([3, 4, 5], '09:00', '18:00')->create(['name' => 'Pedro']);
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'active_minutes' => 30]);
    $this->beard = Service::factory()->for($this->salon)->create(['name' => 'Barba', 'active_minutes' => 15]);
    $this->pedro->services()->attach([$this->cut->id, $this->beard->id]);
    $this->customer = User::factory()->create(['name' => 'Ana Cliente']);
});

/**
 * A booking of the given account with both services, Thursday at $time.
 */
function extraBooking(User $user, string $time = '10:00'): Booking
{
    $test = test();
    $client = SalonClient::withoutGlobalScopes()->where('salon_id', $test->salon->id)->where('user_id', $user->id)->first()
        ?? SalonClient::factory()->for($test->salon)->forUser($user)->create();

    return app(CreateBooking::class)->handle(new NewBooking(
        salon: $test->salon->refresh(),
        client: $client,
        selections: [['service_id' => $test->cut->id, 'variant_id' => null], ['service_id' => $test->beard->id, 'variant_id' => null]],
        staff: $test->pedro,
        startsAt: CarbonImmutable::parse("2026-10-08 {$time}", 'America/Lima')->utc(),
        source: BookingSource::Client,
    ));
}

function repeatUrl(string $code): string
{
    return route('salons.booking.create', ['salon' => 'barberia-lucho', 'repetir' => $code]);
}

describe('repeating a booking', function () {
    it('starts the wizard with the same services and professional', function () {
        $booking = extraBooking($this->customer);

        $this->actingAs($this->customer)->get(repeatUrl($booking->code))
            ->assertInertia(fn (Assert $page) => $page->where('repeat', ['selections' => [['serviceId' => $this->cut->id, 'variantId' => null], ['serviceId' => $this->beard->id, 'variantId' => null]], 'staffId' => $this->pedro->id]));
    });

    it('leaves out what no longer exists', function () {
        $booking = extraBooking($this->customer);
        $this->beard->forceFill(['is_active' => false])->save();
        $this->pedro->forceFill(['is_active' => false])->save();

        $this->actingAs($this->customer)->get(repeatUrl($booking->code))
            ->assertInertia(fn (Assert $page) => $page->where('repeat', ['selections' => [['serviceId' => $this->cut->id, 'variantId' => null]], 'staffId' => null]));

        $this->cut->delete();
        $this->actingAs($this->customer)->get(repeatUrl($booking->code))
            ->assertInertia(fn (Assert $page) => $page->where('repeat', null));
    });

    it('tells nothing about bookings of someone else, of another salon, or to guests', function () {
        $booking = extraBooking($this->customer);
        $stranger = User::factory()->create();
        Salon::factory()->create(['slug' => 'otro-salon']);

        $this->actingAs($stranger)->get(repeatUrl($booking->code))->assertInertia(fn (Assert $page) => $page->where('repeat', null));
        $this->actingAs($this->customer)->get(route('salons.booking.create', ['salon' => 'otro-salon', 'repetir' => $booking->code]))
            ->assertInertia(fn (Assert $page) => $page->where('repeat', null));
        $this->actingAs($this->customer)->get(repeatUrl('NOEXISTE'))->assertInertia(fn (Assert $page) => $page->where('repeat', null));
        auth()->logout();
        $this->get(repeatUrl($booking->code))->assertInertia(fn (Assert $page) => $page->where('repeat', null));
    });
});

describe('reference photo', function () {
    beforeEach(function () {
        $this->booking = extraBooking($this->customer);
    });

    it('stores the picture re-encoded as WebP and shows it to the client and to the team', function () {
        $this->actingAs($this->customer)
            ->post(route('bookings.photo.store', $this->booking->code), ['image' => UploadedFile::fake()->image('corte.jpg', 2400, 1600)])
            ->assertRedirect(route('bookings.show', $this->booking->code));

        $path = $this->booking->refresh()->reference_photo_path;
        expect($path)->toStartWith("salons/{$this->salon->id}/bookings/")->toEndWith('.webp');
        Storage::disk('public')->assertExists($path);
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));
        expect([$width, $height])->toBe([1200, 800]);

        $this->actingAs($this->customer)->get(route('bookings.show', $this->booking->code))
            ->assertInertia(fn (Assert $page) => $page->where('booking.referencePhotoUrl', Storage::disk('public')->url($path))->where('booking.canAttachPhoto', true));
        $this->actingAs($this->owner)->get(route('agenda.index', ['fecha' => '2026-10-08']))
            ->assertInertia(fn (Assert $page) => $page->where('bookings.0.referencePhotoUrl', Storage::disk('public')->url($path)));
    });

    it('replaces the previous picture and deletes its file', function () {
        $this->actingAs($this->customer)->post(route('bookings.photo.store', $this->booking->code), ['image' => UploadedFile::fake()->image('a.jpg')]);
        $first = $this->booking->refresh()->reference_photo_path;

        $this->actingAs($this->customer)->post(route('bookings.photo.store', $this->booking->code), ['image' => UploadedFile::fake()->image('b.png')]);
        $second = $this->booking->refresh()->reference_photo_path;

        expect($second)->not->toBe($first);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->actingAs($this->customer)->delete(route('bookings.photo.destroy', $this->booking->code));
        expect($this->booking->refresh()->reference_photo_path)->toBeNull();
        Storage::disk('public')->assertMissing($second);
    });

    it('rejects what is not a safe image', function (UploadedFile $file) {
        $this->actingAs($this->customer)->post(route('bookings.photo.store', $this->booking->code), ['image' => $file])
            ->assertSessionHasErrors('image');

        expect($this->booking->refresh()->reference_photo_path)->toBeNull();
    })->with([
        'svg' => [fn () => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
        'pdf' => [fn () => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')],
        'php named jpg' => [fn () => UploadedFile::fake()->createWithContent('x.jpg', '<?php echo 1;')],
        'too heavy' => [fn () => UploadedFile::fake()->image('x.jpg')->size(6000)],
    ]);

    it('does not touch the booking itself', function () {
        $before = $this->booking->only(['status', 'starts_at', 'price_cents', 'staff_id']);

        $this->actingAs($this->customer)->post(route('bookings.photo.store', $this->booking->code), ['image' => UploadedFile::fake()->image('a.jpg'), 'status' => 'completed', 'price_cents' => 1]);

        expect($this->booking->refresh()->only(['status', 'starts_at', 'price_cents', 'staff_id']))->toEqual($before);
    });

    it('only accepts a picture while the visit is still to come', function () {
        $this->booking->forceFill(['status' => BookingStatus::Cancelled])->save();

        $this->actingAs($this->customer)->post(route('bookings.photo.store', $this->booking->code), ['image' => UploadedFile::fake()->image('a.jpg')])
            ->assertInertiaFlash('toast.type', 'error');

        expect($this->booking->refresh()->reference_photo_path)->toBeNull();
        $this->actingAs($this->customer)->get(route('bookings.show', $this->booking->code))
            ->assertInertia(fn (Assert $page) => $page->where('booking.canAttachPhoto', false));
    });

    it('deletes the picture when the visit is called off or over', function () {
        $this->actingAs($this->customer)->post(route('bookings.photo.store', $this->booking->code), ['image' => UploadedFile::fake()->image('a.jpg')]);
        $path = $this->booking->refresh()->reference_photo_path;

        $this->actingAs($this->customer)->post(route('bookings.cancel', $this->booking->code));

        expect($this->booking->refresh()->reference_photo_path)->toBeNull();
        Storage::disk('public')->assertMissing($path);
    });

    it('refuses a picture for a visit that already started or in a suspended salon', function () {
        $this->travelTo(CarbonImmutable::parse('2026-10-08 10:05', 'America/Lima'));
        $this->actingAs($this->customer)->post(route('bookings.photo.store', $this->booking->code), ['image' => UploadedFile::fake()->image('a.jpg')]);

        $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));
        $this->salon->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($this->customer)->post(route('bookings.photo.store', $this->booking->code), ['image' => UploadedFile::fake()->image('a.jpg')]);

        expect($this->booking->refresh()->reference_photo_path)->toBeNull();
    });

    it('is only for the client of the booking', function () {
        $stranger = User::factory()->create();

        $this->post(route('bookings.photo.store', $this->booking->code), ['image' => UploadedFile::fake()->image('a.jpg')])->assertRedirect(route('login'));
        $this->actingAs($stranger)->post(route('bookings.photo.store', $this->booking->code), ['image' => UploadedFile::fake()->image('a.jpg')])->assertNotFound();
        $this->actingAs($this->owner)->post(route('bookings.photo.store', $this->booking->code), ['image' => UploadedFile::fake()->image('a.jpg')])->assertNotFound();
        $this->actingAs($stranger)->delete(route('bookings.photo.destroy', $this->booking->code))->assertNotFound();

        expect($this->booking->refresh()->reference_photo_path)->toBeNull();
    });
});
