<?php

use App\Actions\Booking\CreateBooking;
use App\Actions\Salon\CreateSalon;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\ConsentAction;
use App\Enums\ConsentKind;
use App\Enums\ConsentMethod;
use App\Models\Booking;
use App\Models\ClientConsent;
use App\Models\ClientPhoto;
use App\Models\SalonClient;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/*
| Step 6.1 (d): what a salon keeps under an authorization runs out. Elena's
| file at Estudio Luz has her allergies and a picture, both from Wednesday
| 2026-10-07, the day of her only visit.
*/

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Estudio Luz', BusinessType::Unisex, Clientele::Everyone);
    $this->salon->forceFill(['timezone' => 'America/Lima', 'deposit_percent' => 0])->save();
    $this->staff = Staff::factory()->for($this->salon)->working([3, 4, 5], '09:00', '18:00')->create();
    $this->cut = Service::factory()->for($this->salon)->create(['price_cents' => 3500, 'active_minutes' => 30]);
    $this->staff->services()->attach($this->cut->id);

    $this->elena = SalonClient::factory()->for($this->salon)->create();
    purgeVisit('2026-10-07 10:00');
    $this->actingAs($this->owner)->put(route('clients.notes.update', $this->elena), ['care_notes' => 'Alergia a la PPD', 'care_consent' => true])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->post(route('clients.photos.store', $this->elena), [
        'photo' => UploadedFile::fake()->image('antes.jpg', 800, 600), 'kind' => 'before', 'consent' => true,
    ])->assertSessionHasNoErrors();
});

function purgeVisit(string $at): void
{
    app(CreateBooking::class)->handle(new NewBooking(
        salon: test()->salon->refresh(),
        client: test()->elena,
        selections: [['service_id' => test()->cut->id, 'variant_id' => null]],
        staff: test()->staff,
        startsAt: CarbonImmutable::parse($at, 'America/Lima'),
        source: BookingSource::Staff,
    ));
}

function purgeAt(string $day): void
{
    test()->travelTo(CarbonImmutable::parse("{$day} 03:30", 'America/Lima'));
    test()->artisan('clients:purge-expired-data')->assertSuccessful();
}

function photosKept(): int
{
    return ClientPhoto::withoutGlobalScope('salon')->where('salon_client_id', test()->elena->id)->count();
}

it('erases the pictures 12 months after the last visit and the allergies after 24', function () {
    purgeAt('2027-10-07');
    expect(photosKept())->toBe(1)->and($this->elena->refresh()->care_notes)->toBe('Alergia a la PPD');

    purgeAt('2027-10-09');
    expect(photosKept())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([])
        ->and($this->elena->refresh()->care_notes)->toBe('Alergia a la PPD');

    purgeAt('2028-10-06');
    expect($this->elena->refresh()->care_notes)->toBe('Alergia a la PPD');

    purgeAt('2028-10-09');
    $last = ClientConsent::latest($this->elena, ConsentKind::Care);
    expect($this->elena->refresh()->care_notes)->toBeNull()
        ->and($this->elena->care_consent_at)->toBeNull()
        ->and($last?->action)->toBe(ConsentAction::Withdrawn)
        ->and($last?->method)->toBe(ConsentMethod::System);

    // Running again finds nothing left to record.
    purgeAt('2028-10-10');
    expect(ClientConsent::withoutGlobalScope('salon')->where('salon_client_id', $this->elena->id)->where('action', ConsentAction::Withdrawn)->count())->toBe(2);
});

it('keeps both while the person keeps coming, or has a visit booked', function () {
    purgeVisit('2026-10-07 11:00');
    // That second visit, moved to September 2027.
    Booking::withoutGlobalScope('salon')->where('salon_client_id', $this->elena->id)->latest('id')->firstOrFail()
        ->forceFill(['starts_at' => CarbonImmutable::parse('2027-09-01 10:00', 'America/Lima')->utc()])->save();

    purgeAt('2028-08-30');

    expect(photosKept())->toBe(1)->and($this->elena->refresh()->care_notes)->toBe('Alergia a la PPD');
});

it('does not count as the person taking it back: the salon may ask again', function () {
    purgeAt('2028-10-09');

    expect(ClientConsent::latest($this->elena, ConsentKind::Care)?->isWithdrawnByThePerson())->toBeFalse()
        ->and(ClientConsent::latest($this->elena, ConsentKind::Photos)?->isWithdrawnByThePerson())->toBeFalse();
});

it('erases what a closed salon kept once its 30 days to take it out are over', function () {
    $this->salon->delete();

    purgeAt('2026-11-05');
    expect(photosKept())->toBe(1)->and($this->elena->refresh()->care_notes)->toBe('Alergia a la PPD');

    purgeAt('2026-11-07');
    expect(photosKept())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([])
        ->and($this->elena->refresh()->care_notes)->toBeNull();
});
