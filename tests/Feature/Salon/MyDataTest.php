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
use App\Models\ClientConsent;
use App\Models\ClientPhoto;
use App\Models\SalonClient;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\ConsentReceiptNotification;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Step 6.1 (c): "Mis datos". Carla booked with her own account at Estudio
| Luz, so that file is hers; Dana is another client of the same salon.
| "Now" is Wednesday 2026-10-07 08:00 in Lima.
*/

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Estudio Luz', BusinessType::Unisex, Clientele::Everyone);
    $this->salon->forceFill(['timezone' => 'America/Lima'])->save();

    $this->carla = User::factory()->create();
    $this->file = SalonClient::factory()->for($this->salon)->create(['user_id' => $this->carla->id]);
    $this->dana = User::factory()->create();
    $this->danaFile = SalonClient::factory()->for($this->salon)->create(['user_id' => $this->dana->id]);
});

/**
 * @return list<ConsentAction>
 */
function consentTrail(SalonClient $client, ConsentKind $kind): array
{
    return ClientConsent::withoutGlobalScope('salon')->where('salon_client_id', $client->id)->where('kind', $kind)
        ->orderBy('id')->pluck('action')->all();
}

function ownerUploadsPhoto(SalonClient $client): ClientPhoto
{
    test()->actingAs(test()->owner)->post(route('clients.photos.store', $client), [
        'photo' => UploadedFile::fake()->image('antes.jpg', 800, 600), 'kind' => 'before', 'consent' => true,
    ])->assertSessionHasNoErrors();

    return ClientPhoto::withoutGlobalScope('salon')->where('salon_client_id', $client->id)->latest('id')->firstOrFail();
}

it('lets the person authorize their allergies from their account, with proof and a receipt', function () {
    $this->actingAs($this->carla)->post(route('my-data.grant', [$this->file->id, 'care']))->assertSessionHasErrors('accept');
    expect($this->file->refresh()->care_consent_at)->toBeNull();

    $this->actingAs($this->carla)
        ->withServerVariables(['REMOTE_ADDR' => '190.1.2.3', 'HTTP_USER_AGENT' => 'Navegador de Carla'])
        ->post(route('my-data.grant', [$this->file->id, 'care']), ['accept' => true])
        ->assertSessionHasNoErrors();

    $consent = ClientConsent::withoutGlobalScope('salon')->sole();
    $this->file->refresh();

    expect($this->file->care_consent_at)->not->toBeNull()
        ->and($this->file->care_consent_by)->toBeNull()
        ->and($consent->method)->toBe(ConsentMethod::Account)
        ->and($consent->account_id)->toBe($this->carla->id)
        ->and($consent->account_number)->toBe($this->carla->id)
        ->and($consent->recorded_by)->toBeNull()
        ->and($consent->ip)->toBe('190.1.2.3')
        ->and($consent->user_agent)->toBe('Navegador de Carla')
        ->and($consent->text_hash)->toBe(hash('sha256', ConsentKind::Care->accountText('Estudio Luz')));

    Notification::assertSentTo($this->carla, ConsentReceiptNotification::class, fn (ConsentReceiptNotification $receipt): bool => $receipt->salonName === 'Estudio Luz'
        && $receipt->text === ConsentKind::Care->text('Estudio Luz')
        && str_contains((string) $receipt->toMail($this->carla)->render(), 'Autorizo a Estudio Luz'));

    // The salon can now write the allergies without paper, and sees how it was given.
    $this->actingAs($this->owner)->put(route('clients.notes.update', $this->file), ['care_notes' => 'Alergia a la PPD', 'care_consent' => true])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->get(route('clients.show', $this->file))
        ->assertInertia(fn (Assert $page) => $page->where('client.careConsent.method', 'account')->where('client.careNotes', 'Alergia a la PPD'));
});

it('asks for a verified address before taking an authorization', function () {
    $this->carla->forceFill(['email_verified_at' => null])->save();

    $this->actingAs($this->carla)->post(route('my-data.grant', [$this->file->id, 'care']), ['accept' => true])
        ->assertRedirect(route('verification.notice'));

    expect($this->file->refresh()->care_consent_at)->toBeNull();
    Notification::assertNothingSent();
});

it('shows the person what each salon keeps of them', function () {
    $this->file->forceFill(['care_notes' => 'Alergia a la PPD', 'care_consent_at' => now(), 'notes' => 'Cliente difícil'])->save();
    $photo = ownerUploadsPhoto($this->file);

    $response = $this->actingAs($this->carla)->get(route('my-data.index'))
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/my-data')
            ->has('files', 1)
            ->where('files.0.salon.name', 'Estudio Luz')
            ->where('files.0.care.isAuthorized', true)
            ->where('files.0.care.notes', 'Alergia a la PPD')
            ->has('files.0.photos.items', 1)
            ->where('files.0.photos.items.0.url', route('my-data.photos.show', [$this->file->id, $photo->id]))
            ->where('files.0.photos.isBlocked', false));

    // The team's private notes and who uploaded are not part of this page.
    expect($response->getContent())->not->toContain('Cliente difícil')->not->toContain($this->owner->name);
    $this->actingAs($this->carla)->get(route('my-data.photos.show', [$this->file->id, $photo->id]))
        ->assertOk()->assertHeader('Content-Type', 'image/webp')->assertHeader('Cache-Control', 'no-store, private');
});

it('erases the allergies when the person takes the authorization back, and tells the salon', function () {
    $this->file->forceFill(['care_notes' => 'Alergia a la PPD', 'care_consent_at' => now(), 'care_consent_by' => $this->owner->id])->save();

    $this->actingAs($this->carla)->post(route('my-data.withdraw', [$this->file->id, 'care']))->assertSessionHasNoErrors();
    $this->file->refresh();
    $withdrawal = ClientConsent::withoutGlobalScope('salon')->sole();

    expect($this->file->care_notes)->toBeNull()
        ->and($this->file->care_consent_at)->toBeNull()
        ->and($withdrawal->action)->toBe(ConsentAction::Withdrawn)
        ->and($withdrawal->method)->toBe(ConsentMethod::Account)
        ->and($withdrawal->details)->toBe(['had_text' => true]);

    $this->actingAs($this->owner)->get(route('clients.show', $this->file))
        ->assertInertia(fn (Assert $page) => $page
            ->where('client.careNotes', null)
            ->where('client.careWithdrawal.date', '2026-10-07')
            ->where('client.careWithdrawal.byThePerson', true));

    // Nothing left to take back: a second click records nothing.
    $this->actingAs($this->carla)->post(route('my-data.withdraw', [$this->file->id, 'care']));
    expect(consentTrail($this->file, ConsentKind::Care))->toBe([ConsentAction::Withdrawn]);
});

it('deletes the pictures when the person takes them back, and only they can allow more', function () {
    $photo = ownerUploadsPhoto($this->file);
    $path = $photo->path;

    $this->actingAs($this->carla)->post(route('my-data.withdraw', [$this->file->id, 'photos']))->assertSessionHasNoErrors();

    expect(ClientPhoto::withoutGlobalScope('salon')->count())->toBe(0)
        ->and(Storage::disk('local')->exists($path))->toBeFalse()
        ->and(consentTrail($this->file, ConsentKind::Photos))->toBe([ConsentAction::Granted, ConsentAction::Withdrawn])
        ->and(ClientConsent::latest($this->file, ConsentKind::Photos)?->details)->toBe(['photos' => 1]);

    // The team's word is no longer enough.
    $this->actingAs($this->owner)->post(route('clients.photos.store', $this->file), [
        'photo' => UploadedFile::fake()->image('otra.jpg', 800, 600), 'kind' => 'after', 'consent' => true,
    ])->assertInertiaFlash('toast.type', 'error');
    expect(ClientPhoto::withoutGlobalScope('salon')->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
    $this->actingAs($this->owner)->get(route('clients.show', $this->file))
        ->assertInertia(fn (Assert $page) => $page
            ->where('client.photosWithdrawal.byThePerson', true)
            ->where('client.photosWithdrawal.count', 1));

    // Until they say so again, from their account.
    $this->actingAs($this->carla)->post(route('my-data.grant', [$this->file->id, 'photos']), ['accept' => true])->assertSessionHasNoErrors();
    // In force before the first picture: they see it and can take it back.
    $this->actingAs($this->carla)->get(route('my-data.index'))->assertInertia(fn (Assert $page) => $page
        ->where('files.0.photos.isAuthorized', true)
        ->where('files.0.photos.isBlocked', false)
        ->has('files.0.photos.items', 0));
    ownerUploadsPhoto($this->file);
    expect(ClientPhoto::withoutGlobalScope('salon')->count())->toBe(1);
});

it('keeps everyone else away from a person\'s data', function () {
    $this->file->forceFill(['care_notes' => 'Alergia a la PPD', 'care_consent_at' => now()])->save();
    $photo = ownerUploadsPhoto($this->file);

    // Another client of the same salon, attacking with the ids.
    $this->actingAs($this->dana)->get(route('my-data.index'))
        ->assertInertia(fn (Assert $page) => $page->has('files', 1)->where('files.0.clientId', $this->danaFile->id)->where('files.0.care.notes', null));
    $this->actingAs($this->dana)->post(route('my-data.grant', [$this->file->id, 'care']), ['accept' => true])->assertNotFound();
    $this->actingAs($this->dana)->post(route('my-data.withdraw', [$this->file->id, 'care']))->assertNotFound();
    $this->actingAs($this->dana)->post(route('my-data.withdraw', [$this->file->id, 'photos']))->assertNotFound();
    $this->actingAs($this->dana)->get(route('my-data.photos.show', [$this->file->id, $photo->id]))->assertNotFound();
    // Her own file does not serve someone else's picture.
    $this->actingAs($this->dana)->get(route('my-data.photos.show', [$this->danaFile->id, $photo->id]))->assertNotFound();

    // A file the salon created by name and phone belongs to no account.
    $walkIn = SalonClient::factory()->for($this->salon)->create(['phone' => $this->dana->phone ?? '999111222']);
    $this->actingAs($this->dana)->post(route('my-data.withdraw', [$walkIn->id, 'care']))->assertNotFound();

    // The salon's owner is not the person either.
    $this->actingAs($this->owner)->post(route('my-data.withdraw', [$this->file->id, 'care']))->assertNotFound();

    auth()->logout();
    $this->get(route('my-data.index'))->assertRedirect(route('login'));
    $this->post(route('my-data.withdraw', [$this->file->id, 'care']))->assertRedirect(route('login'));

    expect($this->file->refresh()->care_notes)->toBe('Alergia a la PPD')
        ->and(ClientPhoto::withoutGlobalScope('salon')->count())->toBe(1);
});

it('rejects a kind of authorization that does not exist', function () {
    $this->actingAs($this->carla)->post(route('my-data.grant', [$this->file->id, 'todo']), ['accept' => true])->assertNotFound();
});

it('takes back everything, in every salon, when the account is deleted', function () {
    $other = app(CreateSalon::class)->handle(User::factory()->create(), 'Otro Salón', BusinessType::Barbershop, Clientele::Men);
    $otherFile = SalonClient::factory()->for($other)->create(['user_id' => $this->carla->id, 'care_notes' => 'Asma', 'care_consent_at' => now()]);
    $this->file->forceFill(['care_notes' => 'Alergia a la PPD', 'care_consent_at' => now()])->save();
    $path = ownerUploadsPhoto($this->file)->path;

    $this->actingAs($this->carla)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');

    expect(User::query()->whereKey($this->carla->id)->exists())->toBeFalse()
        ->and($this->file->refresh()->care_notes)->toBeNull()
        ->and($otherFile->refresh()->care_notes)->toBeNull()
        ->and(ClientPhoto::withoutGlobalScope('salon')->count())->toBe(0)
        ->and(Storage::disk('local')->exists($path))->toBeFalse();

    // The proof survives the account: who it was stays as a number.
    $rows = ClientConsent::withoutGlobalScope('salon')->where('action', ConsentAction::Withdrawn)->get();
    expect($rows)->toHaveCount(3)
        ->and($rows->pluck('method')->unique()->all())->toBe([ConsentMethod::System])
        ->and($rows->pluck('account_number')->unique()->all())->toBe([$this->carla->id])
        ->and($rows->pluck('account_id')->unique()->all())->toBe([null]);
});

it('does not let the salon bring back the allergies the person took back', function () {
    $this->file->forceFill(['care_notes' => 'Alergia a la PPD', 'care_consent_at' => now(), 'care_consent_by' => $this->owner->id])->save();
    $this->actingAs($this->carla)->post(route('my-data.withdraw', [$this->file->id, 'care']));

    // A form the team had open, or the same text typed again.
    $this->actingAs($this->owner)->put(route('clients.notes.update', $this->file), ['notes' => 'Prefiere las mañanas', 'care_notes' => 'Alergia a la PPD', 'care_consent' => true])
        ->assertInertiaFlash('toast.type', 'error');
    $this->file->refresh();

    expect($this->file->care_notes)->toBeNull()
        ->and($this->file->care_consent_at)->toBeNull()
        ->and($this->file->notes)->toBeNull()
        ->and(consentTrail($this->file, ConsentKind::Care))->toBe([ConsentAction::Withdrawn]);

    // Until she says so again, from her account.
    $this->actingAs($this->carla)->post(route('my-data.grant', [$this->file->id, 'care']), ['accept' => true]);
    $this->actingAs($this->owner)->put(route('clients.notes.update', $this->file), ['care_notes' => 'Alergia a la PPD', 'care_consent' => true])
        ->assertSessionHasNoErrors();
    expect($this->file->refresh()->care_notes)->toBe('Alergia a la PPD');
});

it('adds the person\'s own word to a signed sheet, once, and sends one receipt', function () {
    $this->file->forceFill(['care_notes' => 'Alergia a la PPD', 'care_consent_at' => now()->subDays(3)])->save();
    $since = $this->file->refresh()->care_consent_at;

    $this->actingAs($this->carla)->post(route('my-data.grant', [$this->file->id, 'care']), ['accept' => true]);
    $this->actingAs($this->carla)->post(route('my-data.grant', [$this->file->id, 'care']), ['accept' => true]);

    expect(consentTrail($this->file, ConsentKind::Care))->toBe([ConsentAction::Granted])
        ->and($this->file->refresh()->care_consent_at?->equalTo($since))->toBeTrue()
        ->and($this->file->care_notes)->toBe('Alergia a la PPD');
    Notification::assertSentToTimes($this->carla, ConsentReceiptNotification::class, 1);
});

it('still shows what a closed salon kept, to take it back', function () {
    $this->file->forceFill(['care_notes' => 'Alergia a la PPD', 'care_consent_at' => now()])->save();
    $this->salon->delete();

    $this->actingAs($this->carla)->get(route('my-data.index'))->assertInertia(fn (Assert $page) => $page
        ->has('files', 1)
        ->where('files.0.salon.isClosed', true)
        ->where('files.0.care.notes', 'Alergia a la PPD'));

    $this->actingAs($this->carla)->post(route('my-data.grant', [$this->file->id, 'photos']), ['accept' => true])->assertNotFound();
    $this->actingAs($this->carla)->post(route('my-data.withdraw', [$this->file->id, 'care']))->assertSessionHasNoErrors();

    expect($this->file->refresh()->care_notes)->toBeNull();
});

it('lets the salon take back, on the person\'s word, what they authorized from their account', function () {
    $this->actingAs($this->carla)->post(route('my-data.grant', [$this->file->id, 'care']), ['accept' => true]);
    $this->actingAs($this->owner)->put(route('clients.notes.update', $this->file), ['care_notes' => 'Alergia a la PPD', 'care_consent' => true])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->owner)->put(route('clients.notes.update', $this->file), ['care_consent' => false])->assertSessionHasNoErrors();

    $latest = ClientConsent::latest($this->file, ConsentKind::Care);
    expect($this->file->refresh()->care_notes)->toBeNull()
        ->and($this->file->care_consent_at)->toBeNull()
        ->and($latest?->action)->toBe(ConsentAction::Withdrawn)
        ->and($latest?->method)->toBe(ConsentMethod::StaffDeclared);
});

it('lets the person download what is theirs, and only theirs', function () {
    $this->file->forceFill(['care_notes' => 'Alergia a la PPD', 'care_consent_at' => now(), 'notes' => 'Prefiere las mañanas', 'formula_notes' => '6.1 + 20 vol'])->save();
    $this->danaFile->forceFill(['care_notes' => 'Asma', 'care_consent_at' => now()])->save();
    $this->actingAs($this->carla)->post(route('my-data.grant', [$this->file->id, 'photos']), ['accept' => true]);

    $response = $this->actingAs($this->carla)->get(route('my-data.download'))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="mis-datos.json"');

    $response->assertJsonPath('cuenta.correo', $this->carla->email)
        ->assertJsonCount(1, 'salones')
        ->assertJsonPath('salones.0.salon', 'Estudio Luz')
        ->assertJsonPath('salones.0.alergias_y_cuidado', 'Alergia a la PPD')
        ->assertJsonPath('salones.0.autorizaciones.0.accion', 'granted');
    // Neither another person's data nor what the salon wrote on its own.
    expect($response->getContent())->not->toContain('Asma')
        ->not->toContain('Prefiere las mañanas')
        ->not->toContain('6.1 + 20 vol')
        ->not->toContain('password');

    auth()->logout();
    $this->get(route('my-data.download'))->assertRedirect(route('login'));
});

it('keeps the team\'s note on a booking out of the download', function () {
    $staff = Staff::factory()->for($this->salon)->working([3, 4, 5], '09:00', '18:00')->create();
    $cut = Service::factory()->for($this->salon)->create(['price_cents' => 3500, 'active_minutes' => 30]);
    $staff->services()->attach($cut->id);
    $this->salon->forceFill(['deposit_percent' => 0])->save();

    $book = fn (string $at, BookingSource $source, string $note) => app(CreateBooking::class)->handle(new NewBooking(
        salon: $this->salon->refresh(),
        client: $this->file,
        selections: [['service_id' => $cut->id, 'variant_id' => null]],
        staff: $staff,
        startsAt: CarbonImmutable::parse($at, 'America/Lima'),
        source: $source,
        note: $note,
    ));
    $book('2026-10-07 10:00', BookingSource::Staff, 'Llega tarde, cobrar seña');
    $book('2026-10-07 11:00', BookingSource::Client, 'Quiero el mismo corte');

    $content = $this->actingAs($this->carla)->get(route('my-data.download'))->assertOk()->getContent();

    expect($content)->toContain('Quiero el mismo corte')->not->toContain('Llega tarde');
});
