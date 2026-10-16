<?php

use App\Actions\Platform\SupportVisits;
use App\Actions\Salon\CreateSalon;
use App\Enums\AcceptanceChannel;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\LegalDocumentType;
use App\Http\Middleware\EnsureLegalTermsAccepted;
use App\Models\LegalAcceptance;
use App\Models\Salon;
use App\Models\SupportVisit;
use App\Models\User;
use App\Support\Legal\LegalLibrary;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

/*
| Requisitos P1 to P4 (docs/legal/requisitos-de-producto.md): published
| texts with version and hash, casilla 1 at sign up, the acceptance screen
| nobody skips, and casilla 5 when opening a salon.
*/

/**
 * The published texts plus a version 2 of the client terms, in a folder of
 * its own: the repository copies stay untouched.
 */
function publishNewClientTermsVersion(): void
{
    $folder = storage_path('framework/testing/legal-'.uniqid());
    File::copyDirectory(resource_path('legal'), $folder);
    File::put("{$folder}/terminos-clientes/2.md", "---\ntitle: Términos y condiciones para quienes reservan\neffective: 2026-11-01\nchanges: Ahora explicamos mejor las señas.\n---\n## 1. Lo nuevo\n\nTexto nuevo.\n");
    config(['legal.documents_path' => $folder]);
    app()->forgetScopedInstances();
}

function registrationPayload(array $overrides = []): array
{
    return [
        'name' => 'Ale Ríos',
        'email' => 'ale@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        ...$overrides,
    ];
}

it('publishes every legal text with its version, date and hash', function (LegalDocumentType $type) {
    $document = app(LegalLibrary::class)->current($type);

    $this->get(route('legal.show', $type))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/legal')
            ->where('document.version', '1')
            ->where('document.effectiveOn', '2026-10-16')
            ->where('document.hash', $document->hash())
            ->where('document.isCurrent', true));
})->with(LegalDocumentType::cases());

it('fills the gaps from configuration and never shows the notes for reviewers', function () {
    config(['legal.provider.name' => 'Empresa de Prueba S.A.C.', 'legal.provider.ruc' => '20000000001']);

    $markdown = app(LegalLibrary::class)->current(LegalDocumentType::ClientTerms)->markdown;

    expect($markdown)->toContain('Empresa de Prueba S.A.C., RUC 20000000001')
        ->toContain('«por completar: correo de contacto»')
        ->not->toContain('{{')
        ->not->toContain('Borrador')
        ->not->toContain('PENDIENTE')
        ->not->toContain('CONFIRMAR');
});

it('keeps earlier versions readable and refuses made-up ones', function () {
    publishNewClientTermsVersion();

    $this->get(route('legal.version', [LegalDocumentType::ClientTerms, '1']))
        ->assertInertia(fn (Assert $page) => $page->where('document.version', '1')->where('document.isCurrent', false));
    $this->get(route('legal.show', LegalDocumentType::ClientTerms))
        ->assertInertia(fn (Assert $page) => $page->where('document.version', '2')->where('versions', ['2', '1']));
    $this->get(route('legal.version', [LegalDocumentType::ClientTerms, '9']))->assertNotFound();
    $this->get('/legal/no-existe')->assertNotFound();
});

it('refuses a sign up without the terms box ticked', function () {
    $this->post(route('register.store'), registrationPayload())
        ->assertSessionHasErrors('terms');

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('records the accepted version and hash of each text at sign up', function () {
    $this->withHeader('User-Agent', 'Navegador de prueba')
        ->post(route('register.store'), registrationPayload(['terms' => 'on']))
        ->assertRedirect(route('dashboard'));

    $user = User::where('email', 'ale@example.com')->sole();
    $library = app(LegalLibrary::class);
    $acceptances = LegalAcceptance::where('user_id', $user->id)->orderBy('document')->get();

    expect($acceptances)->toHaveCount(2)
        ->and($acceptances->pluck('document')->all())->toEqualCanonicalizing(LegalDocumentType::forAccounts());

    foreach ($acceptances as $acceptance) {
        expect($acceptance)
            ->version->toBe('1')
            ->text_hash->toBe($library->current($acceptance->document)->hash())
            ->channel->toBe(AcceptanceChannel::Registration)
            ->account_number->toBe($user->id)
            ->ip->toBe('127.0.0.1')
            ->user_agent->toBe('Navegador de prueba');
    }
});

it('never lets an acceptance be changed or removed', function () {
    $acceptance = LegalAcceptance::where('user_id', User::factory()->create()->id)->firstOrFail();

    expect(fn () => $acceptance->forceFill(['version' => '0'])->save())->toThrow(LogicException::class)
        ->and(fn () => $acceptance->delete())->toThrow(LogicException::class);
});

it('stops an account without the current terms at the acceptance screen', function () {
    $user = User::factory()->withoutAcceptedTerms()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('legal.acceptance.show'));
    $this->actingAs($user)->get(route('bookings.index'))->assertRedirect(route('legal.acceptance.show'));
    $this->actingAs($user)->put(route('salon.profile.update'))->assertRedirect(route('legal.acceptance.show'));
    $this->actingAs($user)->getJson(route('bookings.index'))
        ->assertForbidden()
        ->assertJsonPath('code', EnsureLegalTermsAccepted::API_CODE);

    $this->actingAs($user)->get(route('legal.acceptance.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/accept-terms')
            ->where('mode', 'first')
            ->has('documents', 2));
});

it('still lets that account read the texts, sign out and delete itself', function () {
    $user = User::factory()->withoutAcceptedTerms()->create();

    $this->actingAs($user)->get(route('legal.show', LegalDocumentType::Privacy))->assertOk();
    $this->actingAs($user)->post(route('logout'))->assertRedirect();
    $this->assertGuest();

    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');
    expect(User::find($user->id))->toBeNull();
});

it('accepts the terms on the screen and goes back where the person was', function () {
    $user = User::factory()->withoutAcceptedTerms()->create();

    $this->actingAs($user)->get(route('bookings.index'));
    $this->actingAs($user)->post(route('legal.acceptance.store'))->assertSessionHasErrors('terms');
    expect(LegalAcceptance::count())->toBe(0);

    $this->actingAs($user)->post(route('legal.acceptance.store'), ['terms' => '1'])
        ->assertRedirect(route('bookings.index'));

    expect(LegalAcceptance::where('user_id', $user->id)->pluck('channel')->unique()->all())->toBe([AcceptanceChannel::Screen]);
    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

it('asks again when a text gets a new version, showing only what changed', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    publishNewClientTermsVersion();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('legal.acceptance.show'));
    $this->actingAs($user)->get(route('legal.acceptance.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('mode', 'update')
            ->has('documents', 1)
            ->where('documents.0.version', '2')
            ->where('documents.0.changes', 'Ahora explicamos mejor las señas.'));

    $this->actingAs($user)->post(route('legal.acceptance.store'), ['terms' => '1']);

    expect(LegalAcceptance::where('user_id', $user->id)->where('version', '2')->sole()->document)->toBe(LegalDocumentType::ClientTerms);
    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

it('answers the app with a clear 403 until the terms are accepted', function () {
    Sanctum::actingAs(User::factory()->withoutAcceptedTerms()->create());

    $this->getJson(route('api.v1.bookings.index'))
        ->assertForbidden()
        ->assertJsonPath('code', EnsureLegalTermsAccepted::API_CODE);
    $this->getJson(route('api.v1.me'))->assertOk();
});

it('lets the platform admin in support mode accept for themselves, not for the salon', function () {
    $admin = User::factory()->platformAdmin()->withTwoFactor()->withoutAcceptedTerms()->create();
    $salon = app(CreateSalon::class)->handle(User::factory()->create(), 'Barbería Central', BusinessType::Barbershop, Clientele::Men);
    $visit = new SupportVisit;
    $visit->admin_id = $admin->id;
    $visit->salon_id = $salon->id;
    $visit->reason = 'Revisar reservas';
    $visit->started_at = now();
    $visit->save();

    $this->actingAs($admin)->withSession([SupportVisits::SESSION_KEY => $visit->id]);
    $this->get(route('agenda.index'))->assertRedirect(route('legal.acceptance.show'));
    $this->post(route('legal.acceptance.store'), ['terms' => '1'])->assertRedirect(route('agenda.index'));

    expect(LegalAcceptance::where('user_id', $admin->id)->count())->toBe(2)
        ->and(LegalAcceptance::whereNotNull('salon_id')->exists())->toBeFalse();
    $this->get(route('agenda.index'))->assertOk();
});

it('refuses to open a salon without the salon terms box (casilla 5)', function () {
    $user = User::factory()->create();
    $payload = ['name' => 'Barbería El Turco', 'business_type' => BusinessType::Barbershop->value, 'clientele' => Clientele::Men->value];

    $this->actingAs($user)->post(route('salons.store'), $payload)->assertSessionHasErrors('salon_terms');
    expect(Salon::count())->toBe(0);

    $this->actingAs($user)->post(route('salons.store'), [...$payload, 'salon_terms' => '1'])->assertRedirect(route('dashboard'));

    $acceptance = LegalAcceptance::where('document', LegalDocumentType::SalonTerms)->sole();
    expect($acceptance)
        ->user_id->toBe($user->id)
        ->salon_id->toBe(Salon::sole()->id)
        ->channel->toBe(AcceptanceChannel::SalonSignup)
        ->text_hash->toBe(app(LegalLibrary::class)->current(LegalDocumentType::SalonTerms)->hash());
});
