<?php

use App\Actions\Booking\CreateBooking;
use App\Actions\Salon\CreateSalon;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\ConsentKind;
use App\Enums\ConsentMethod;
use App\Enums\SalonPermission;
use App\Models\ClientConsent;
use App\Models\ClientPhoto;
use App\Models\SalonClient;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Step 6.1 (b): before/after pictures in the client's file. "Now" is
| Wednesday 2026-10-07 08:00 in Lima. Ana has the client booked; Rita runs
| everyone's agenda from the front desk and attends nobody.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Estudio Luz', BusinessType::Unisex, Clientele::Everyone);
    $this->salon->forceFill(['timezone' => 'America/Lima', 'deposit_percent' => 0])->save();
    $this->ana = SalonMember::factory()->for($this->salon)->create();
    $this->rita = SalonMember::factory()->for($this->salon)->withPermissions(SalonPermission::ViewAllSchedules)->create();
    $staff = Staff::factory()->for($this->salon)->working([3, 4], '09:00', '18:00')->create(['user_id' => $this->ana->user_id]);
    $cut = Service::factory()->for($this->salon)->create(['price_cents' => 3500, 'active_minutes' => 30]);
    $staff->services()->attach($cut->id);
    $this->client = SalonClient::factory()->for($this->salon)->create();

    app(CreateBooking::class)->handle(new NewBooking(
        salon: $this->salon->refresh(),
        client: $this->client,
        selections: [['service_id' => $cut->id, 'variant_id' => null]],
        staff: $staff,
        startsAt: CarbonImmutable::parse('2026-10-07 10:00', 'America/Lima'),
        source: BookingSource::Staff,
    ));
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function photoPayload(array $overrides = []): array
{
    return ['photo' => UploadedFile::fake()->image('antes.jpg', 1200, 900), 'kind' => 'before', 'consent' => true, ...$overrides];
}

it('keeps a picture on the private disk, with the authorization on record', function () {
    $this->actingAs($this->ana->user)->post(route('clients.photos.store', $this->client), photoPayload())->assertSessionHasNoErrors();

    $photo = ClientPhoto::withoutGlobalScope('salon')->sole();
    $consent = ClientConsent::withoutGlobalScope('salon')->sole();

    expect($photo->salon_client_id)->toBe($this->client->id)
        ->and($photo->taken_on->format('Y-m-d'))->toBe('2026-10-07')
        ->and($photo->path)->toEndWith('.webp')
        ->and(Storage::disk('local')->exists($photo->path))->toBeTrue()
        ->and(Storage::disk('public')->exists($photo->path))->toBeFalse()
        ->and($consent->kind)->toBe(ConsentKind::Photos)
        ->and($consent->method)->toBe(ConsentMethod::StaffDeclared)
        ->and($consent->recorded_by)->toBe($this->ana->user_id);

    $this->actingAs($this->ana->user)->get(route('clients.show', $this->client))
        ->assertInertia(fn (Assert $page) => $page
            ->has('photos', 1)
            ->where('photos.0.kindLabel', 'Antes')
            ->where('photos.0.date', '2026-10-07')
            ->where('photos.0.url', route('clients.photos.show', [$this->client, $photo])));

    $this->actingAs($this->ana->user)->get(route('clients.photos.show', [$this->client, $photo]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('refuses a picture without the authorization, of the wrong kind or that is not an image', function (array $overrides, string $field) {
    $this->actingAs($this->owner)->post(route('clients.photos.store', $this->client), photoPayload($overrides))->assertSessionHasErrors($field);

    expect(ClientPhoto::withoutGlobalScope('salon')->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'no consent' => [['consent' => false], 'consent'],
    'unknown kind' => [['kind' => 'during'], 'kind'],
    'a list as kind' => [['kind' => ['before']], 'kind'],
    'an svg' => [['photo' => UploadedFile::fake()->create('x.svg', 10, 'image/svg+xml')], 'photo'],
    'a pdf' => [['photo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')], 'photo'],
]);

it('stops at the limit per client', function () {
    foreach (range(1, ClientPhoto::MAX_PER_CLIENT) as $index) {
        $photo = new ClientPhoto;
        $photo->forceFill(['salon_id' => $this->salon->id, 'salon_client_id' => $this->client->id, 'kind' => 'after', 'path' => "x/{$index}.webp", 'taken_on' => '2026-10-01'])->save();
    }

    $this->actingAs($this->owner)->post(route('clients.photos.store', $this->client), photoPayload())->assertInertiaFlash('toast.type', 'error');

    expect(ClientPhoto::withoutGlobalScope('salon')->count())->toBe(ClientPhoto::MAX_PER_CLIENT)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('removes the picture and its file', function () {
    $this->actingAs($this->owner)->post(route('clients.photos.store', $this->client), photoPayload());
    $photo = ClientPhoto::withoutGlobalScope('salon')->sole();

    $this->actingAs($this->owner)->delete(route('clients.photos.destroy', [$this->client, $photo]))->assertSessionHasNoErrors();

    expect(ClientPhoto::withoutGlobalScope('salon')->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([])
        // The proof that it was authorized stays.
        ->and(ClientConsent::withoutGlobalScope('salon')->count())->toBe(1);
});

it('keeps the pictures from who does not attend the person, from other clients\' files and from other salons', function () {
    $this->actingAs($this->owner)->post(route('clients.photos.store', $this->client), photoPayload());
    $photo = ClientPhoto::withoutGlobalScope('salon')->sole();
    $other = SalonClient::factory()->for($this->salon)->create();

    // The front desk opens the file but not the pictures.
    $this->actingAs($this->rita->user)->get(route('clients.show', $this->client))
        ->assertInertia(fn (Assert $page) => $page->where('photos', []));
    $this->actingAs($this->rita->user)->get(route('clients.photos.show', [$this->client, $photo]))->assertForbidden();
    $this->actingAs($this->rita->user)->post(route('clients.photos.store', $this->client), photoPayload())->assertForbidden();
    $this->actingAs($this->rita->user)->delete(route('clients.photos.destroy', [$this->client, $photo]))->assertForbidden();

    // Another client's file does not serve this picture.
    $this->actingAs($this->owner)->get(route('clients.photos.show', [$other, $photo]))->assertNotFound();
    $this->actingAs($this->owner)->delete(route('clients.photos.destroy', [$other, $photo]))->assertNotFound();

    $intruder = User::factory()->create();
    app(CreateSalon::class)->handle($intruder, 'Otro Salón', BusinessType::Barbershop, Clientele::Men);
    $this->actingAs($intruder)->get(route('clients.photos.show', [$this->client, $photo]))->assertNotFound();
    $this->actingAs($intruder)->delete(route('clients.photos.destroy', [$this->client, $photo]))->assertNotFound();

    auth()->logout();
    $this->get(route('clients.photos.show', [$this->client, $photo]))->assertRedirect(route('login'));

    expect(ClientPhoto::withoutGlobalScope('salon')->count())->toBe(1);
});
