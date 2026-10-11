<?php

use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\CreateBooking;
use App\Actions\Salon\CreateSalon;
use App\Data\Booking\NewBooking;
use App\Domain\Accounting\Checkout;
use App\Enums\BookingSource;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\ConsentAction;
use App\Enums\SalonPermission;
use App\Models\Booking;
use App\Models\ClientConsent;
use App\Models\SalonClient;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Step 6.1: the client's file. "Now" is Wednesday 2026-10-07 08:00 in Lima.
| Ana attends; Beto is a colleague who never attended this client.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Estudio Luz', BusinessType::Unisex, Clientele::Everyone);
    $this->salon->forceFill(['timezone' => 'America/Lima', 'deposit_percent' => 0])->save();
    $this->ana = SalonMember::factory()->for($this->salon)->create();
    $this->beto = SalonMember::factory()->for($this->salon)->create();
    $this->anaStaff = Staff::factory()->for($this->salon)->working([3, 4], '09:00', '18:00')->create(['name' => 'Ana', 'user_id' => $this->ana->user_id]);
    Staff::factory()->for($this->salon)->working([3, 4], '09:00', '18:00')->create(['name' => 'Beto', 'user_id' => $this->beto->user_id]);
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'price_cents' => 3500, 'active_minutes' => 30]);
    $this->anaStaff->services()->attach($this->cut->id);
    $this->client = SalonClient::factory()->for($this->salon)->create(['name' => 'Carla Cliente']);
});

function fileBooking(string $at): Booking
{
    return app(CreateBooking::class)->handle(new NewBooking(
        salon: test()->salon->refresh(),
        client: test()->client,
        selections: [['service_id' => test()->cut->id, 'variant_id' => null]],
        staff: test()->anaStaff,
        startsAt: CarbonImmutable::parse($at, 'America/Lima'),
        source: BookingSource::Staff,
    ));
}

function fileCompletedVisit(): Booking
{
    $booking = fileBooking('2026-10-07 10:00');
    test()->travelTo(CarbonImmutable::parse('2026-10-07 10:00', 'America/Lima'));
    $status = app(ChangeBookingStatus::class);
    $status->start($booking, test()->owner);

    return $status->complete($booking, test()->owner, new Checkout(parts: ['cash' => 3500]));
}

it('shows what is coming, what was done and how much, to the owner', function () {
    fileCompletedVisit();
    fileBooking('2026-10-08 11:00');

    $this->actingAs($this->owner)->get(route('clients.show', $this->client))
        // Not kept by the browser: "Back" after signing out finds nothing.
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertInertia(fn (Assert $page) => $page
            ->component('staff/client')
            ->where('client.name', 'Carla Cliente')
            ->where('stats.visits', 1)
            ->where('stats.lastVisit', '2026-10-07')
            ->where('stats.spent', '35.00')
            ->has('upcoming', 1)
            ->where('upcoming.0.date', '2026-10-08')
            ->where('upcoming.0.time', '11:00')
            ->has('history', 1)
            ->where('history.0.services', ['Corte'])
            ->where('history.0.staffName', 'Ana')
            ->where('history.0.charged', '35.00'));
});

it('lets a professional open only the files of people they attend, and never shows them money', function () {
    fileCompletedVisit();

    $this->actingAs($this->ana->user)->get(route('clients.show', $this->client))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.spent', null)
            ->where('history.0.charged', null)
            ->where('canManageVip', false));

    $this->actingAs($this->beto->user)->get(route('clients.show', $this->client))->assertForbidden();
    $this->actingAs($this->beto->user)->put(route('clients.notes.update', $this->client), ['notes' => 'x'])->assertForbidden();
});

it('opens every file to whoever runs everyone\'s agenda or the client list', function (SalonPermission $permission) {
    $member = SalonMember::factory()->for($this->salon)->withPermissions($permission)->create();

    $this->actingAs($member->user)->get(route('clients.show', $this->client))->assertOk();
})->with([SalonPermission::ViewAllSchedules, SalonPermission::ManageVipClients]);

it('keeps care notes only with the person\'s signed authorization on record', function () {
    $url = route('clients.notes.update', $this->client);

    $this->actingAs($this->owner)->put($url, ['notes' => 'Le gusta el café', 'care_notes' => 'Alergia a la PPD'])
        ->assertSessionHasErrors('care_consent');
    expect($this->client->refresh()->care_notes)->toBeNull();

    $this->actingAs($this->owner)->put($url, ['notes' => 'Le gusta el café', 'formula_notes' => '6.1 + 20 vol, 35 min', 'care_notes' => 'Alergia a la PPD', 'care_consent' => true])
        ->assertSessionHasNoErrors();
    $this->client->refresh();

    expect($this->client->notes)->toBe('Le gusta el café')
        ->and($this->client->formula_notes)->toBe('6.1 + 20 vol, 35 min')
        ->and(ClientConsent::withoutGlobalScope('salon')->where('salon_client_id', $this->client->id)->pluck('action')->all())->toBe([ConsentAction::Granted])
        ->and($this->client->care_notes)->toBe('Alergia a la PPD')
        ->and($this->client->care_consent_by)->toBe($this->owner->id)
        ->and($this->client->care_consent_at?->toDateTimeString())->toBe(now()->toDateTimeString());

    $this->actingAs($this->owner)->get(route('clients.show', $this->client))
        ->assertInertia(fn (Assert $page) => $page
            ->where('client.careNotes', 'Alergia a la PPD')
            ->where('client.careConsent.date', '2026-10-07')
            ->where('client.careConsent.by', $this->owner->name));
});

it('keeps the first authorization when the notes are edited, and erases them when it is withdrawn', function () {
    $url = route('clients.notes.update', $this->client);
    $this->actingAs($this->owner)->put($url, ['care_notes' => 'Alergia a la PPD', 'care_consent' => true]);
    $givenAt = $this->client->refresh()->care_consent_at;

    $this->travel(3)->days();
    fileBooking('2026-10-14 10:00');
    $this->actingAs($this->ana->user)->put($url, ['care_notes' => 'Alergia a la PPD y al látex', 'care_consent' => true]);
    $this->client->refresh();

    expect($this->client->care_notes)->toBe('Alergia a la PPD y al látex')
        ->and($this->client->care_consent_at?->equalTo($givenAt))->toBeTrue()
        ->and($this->client->care_consent_by)->toBe($this->owner->id);

    $this->actingAs($this->owner)->put($url, ['notes' => 'Sigue viniendo', 'care_notes' => 'Alergia a la PPD', 'care_consent' => false])
        ->assertSessionHasErrors('care_consent');
    $this->actingAs($this->owner)->put($url, ['notes' => 'Sigue viniendo', 'care_consent' => false]);
    $this->client->refresh();

    expect($this->client->notes)->toBe('Sigue viniendo')
        ->and($this->client->care_notes)->toBeNull()
        ->and($this->client->care_consent_at)->toBeNull()
        ->and($this->client->care_consent_by)->toBeNull()
        // The proof stays: given once, withdrawn once, nothing rewritten.
        ->and(ClientConsent::withoutGlobalScope('salon')->where('salon_client_id', $this->client->id)->orderBy('id')->pluck('action')->all())
        ->toBe([ConsentAction::Granted, ConsentAction::Withdrawn]);
});

it('hides the care notes from who looks clients up without attending them, and their edits leave them alone', function () {
    $this->client->forceFill(['care_notes' => 'Alergia a la PPD', 'care_consent_at' => now(), 'care_consent_by' => $this->owner->id])->save();
    $reception = SalonMember::factory()->for($this->salon)->withPermissions(SalonPermission::ViewAllSchedules)->create();

    $this->actingAs($reception->user)->get(route('clients.show', $this->client))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canSeeCare', false)
            ->where('careConsentText', null)
            ->where('client.careNotes', null)
            ->where('client.careConsent', null));

    $this->actingAs($reception->user)->put(route('clients.notes.update', $this->client), ['notes' => 'Prefiere las mañanas'])->assertSessionHasNoErrors();
    $this->client->refresh();

    expect($this->client->notes)->toBe('Prefiere las mañanas')
        ->and($this->client->care_notes)->toBe('Alergia a la PPD')
        ->and($this->client->care_consent_at)->not->toBeNull();
});

it('rejects notes that are too long or are not text', function (array $payload, string $field) {
    $this->actingAs($this->owner)->put(route('clients.notes.update', $this->client), $payload)->assertSessionHasErrors($field);
})->with([
    'too long' => [['notes' => str_repeat('a', 1001)], 'notes'],
    'a list' => [['notes' => ['a']], 'notes'],
    'care too long' => [['care_notes' => str_repeat('a', 1001), 'care_consent' => true], 'care_notes'],
]);

it('never lists the care notes with the clients', function () {
    $this->client->forceFill(['care_notes' => 'Alergia a la PPD', 'care_consent_at' => now()])->save();

    $response = $this->actingAs($this->owner)->get(route('clients.index'));

    expect($response->getContent())->not->toContain('Alergia a la PPD');
});

it('keeps another salon, clients and visitors away from the file', function () {
    $intruder = User::factory()->create();
    app(CreateSalon::class)->handle($intruder, 'Otro Salón', BusinessType::Barbershop, Clientele::Men);

    $this->actingAs($intruder)->get(route('clients.show', $this->client))->assertNotFound();
    $this->actingAs($intruder)->put(route('clients.notes.update', $this->client), ['notes' => 'x'])->assertNotFound();
    expect($this->client->refresh()->notes)->toBeNull();

    auth()->logout();
    $this->get(route('clients.show', $this->client))->assertRedirect(route('login'));
});

it('does not read a form without the health part as a withdrawal', function () {
    $this->client->forceFill(['care_notes' => 'Alergia a la PPD', 'care_consent_at' => now(), 'care_consent_by' => $this->owner->id])->save();

    $this->actingAs($this->owner)->put(route('clients.notes.update', $this->client), ['notes' => 'Prefiere las mañanas'])->assertSessionHasNoErrors();
    $this->client->refresh();

    expect($this->client->notes)->toBe('Prefiere las mañanas')
        ->and($this->client->care_notes)->toBe('Alergia a la PPD')
        ->and($this->client->care_consent_at)->not->toBeNull()
        ->and(ClientConsent::withoutGlobalScope('salon')->count())->toBe(0);
});

it('gives no reason to read health data to a visit that never happened', function () {
    $this->client->forceFill(['care_notes' => 'Alergia a la PPD', 'care_consent_at' => now(), 'care_consent_by' => $this->owner->id])->save();
    $booking = fileBooking('2026-10-08 11:00');

    $this->actingAs($this->ana->user)->get(route('clients.show', $this->client))
        ->assertInertia(fn (Assert $page) => $page->where('canSeeCare', true)->where('client.careNotes', 'Alergia a la PPD'));

    app(ChangeBookingStatus::class)->cancel($booking, $this->owner, byClient: false);

    $this->actingAs($this->ana->user)->get(route('clients.show', $this->client))
        ->assertInertia(fn (Assert $page) => $page->where('canSeeCare', false)->where('client.careNotes', null));
});

it('stores the care notes encrypted and never serializes them with the client', function () {
    $this->client->forceFill(['care_notes' => 'Alergia a la PPD', 'care_consent_at' => now()])->save();

    $stored = DB::table('salon_clients')->where('id', $this->client->id)->value('care_notes');

    expect($stored)->not->toContain('Alergia')
        ->and($this->client->refresh()->toArray())->not->toHaveKeys(['care_notes', 'care_consent_at', 'care_consent_by']);
});

it('keeps the consent records from being rewritten', function () {
    $this->actingAs($this->owner)->put(route('clients.notes.update', $this->client), ['care_notes' => 'Alergia', 'care_consent' => true]);
    $consent = ClientConsent::withoutGlobalScope('salon')->sole();

    expect(fn () => $consent->delete())->toThrow(LogicException::class)
        ->and(fn () => $consent->forceFill(['text_version' => '9'])->save())->toThrow(LogicException::class);
});
