<?php

use App\Actions\Complaints\RespondToComplaint;
use App\Exceptions\ComplaintException;
use App\Filament\Resources\Complaints\Pages\ListComplaints;
use App\Filament\Resources\Complaints\Pages\ViewComplaint;
use App\Models\Complaint;
use App\Models\Salon;
use App\Models\User;
use App\Notifications\ComplaintAnsweredNotification;
use App\Notifications\ComplaintReceivedNotification;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

/*
| The platform's Libro de Reclamaciones (docs/legal/libro-de-reclamaciones.md
| §2, requirement P6). Friday 16 October 2026, 10:00 in Lima.
*/

beforeEach(function () {
    Notification::fake();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-16 10:00', 'America/Lima'));
    config()->set('legal.provider', ['name' => 'Empresa de prueba S.A.C.', 'ruc' => '20000000001', 'address' => 'Av. de Prueba 123, Lima']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function complaintSheet(array $overrides = []): array
{
    return [
        'kind' => 'claim',
        'consumer_name' => 'Alex Quispe',
        'consumer_address' => 'Jr. Las Flores 456, Lima',
        'document_type' => 'dni',
        'document_number' => '12345678',
        'phone' => '999888777',
        'email' => 'alex@example.test',
        'item_type' => 'service',
        'item_description' => 'Suscripción al plan mensual',
        'claimed_amount' => '49.90',
        'detail' => 'Pagué el plan y el salón siguió en solo lectura.',
        'request' => 'Que activen el plan pagado.',
        'consent' => '1',
        ...$overrides,
    ];
}

it('opens the form without an account and shows the provider', function () {
    $this->get(route('complaints.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/complaints/create')
            ->where('provider.ruc', '20000000001')
            ->where('responseDays', 15)
            ->has('kinds', 2));
});

it('files a sheet, shows its receipt and emails a full copy', function () {
    $response = $this->post(route('complaints.store'), complaintSheet());

    $complaint = Complaint::query()->sole();
    expect($complaint->code())->toBe('000001-2026')
        ->and($complaint->claimed_amount_cents)->toBe(4990)
        ->and($complaint->provider_name)->toBe('Empresa de prueba S.A.C.')
        ->and($complaint->guardian_name)->toBeNull()
        // Friday 16 Oct + 15 business days.
        ->and($complaint->due_on->format('Y-m-d'))->toBe('2026-11-06');
    $this->get((string) $response->headers->get('Location'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/complaints/show')
            ->where('receipt.code', '000001-2026')
            ->where('receipt.kind', 'reclamo')
            ->where('receipt.email', 'alex@example.test')
            ->where('receipt.dueOn', '06/11/2026'));
    Notification::assertSentTo(
        new AnonymousNotifiable,
        ComplaintReceivedNotification::class,
        fn (ComplaintReceivedNotification $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'alex@example.test'
            && str_contains(implode(' ', $notification->toMail($notifiable)->introLines), 'Que activen el plan pagado'),
    );
});

it('never shows a receipt without its signature', function () {
    $complaint = Complaint::factory()->create();

    $this->get(route('complaints.show', $complaint))->assertForbidden();
});

it('numbers sheets one after another and starts again each year', function () {
    $this->post(route('complaints.store'), complaintSheet());
    $this->post(route('complaints.store'), complaintSheet(['kind' => 'grievance']));
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-04 09:00', 'America/Lima'));
    $this->post(route('complaints.store'), complaintSheet());

    expect(Complaint::query()->orderBy('id')->get()->map->code()->all())
        ->toBe(['000001-2026', '000002-2026', '000001-2027']);
});

it('asks the name of a parent or guardian when the person is a minor', function () {
    $this->post(route('complaints.store'), complaintSheet(['is_minor' => '1']))
        ->assertSessionHasErrors('guardian_name');

    $this->post(route('complaints.store'), complaintSheet(['is_minor' => '1', 'guardian_name' => 'Rosa Quispe']));

    expect(Complaint::query()->sole()->guardian_name)->toBe('Rosa Quispe');
});

it('refuses a sheet without the declaration, or with a malformed DNI', function () {
    $this->post(route('complaints.store'), complaintSheet(['consent' => null, 'document_number' => '1234']))
        ->assertSessionHasErrors(['consent', 'document_number']);

    expect(Complaint::query()->count())->toBe(0);
});

it('limits how many sheets one connection can send in an hour', function () {
    foreach (range(1, 10) as $attempt) {
        $this->post(route('complaints.store'), complaintSheet(['email' => "persona{$attempt}@example.test"]))->assertRedirect();
    }

    $this->post(route('complaints.store'), complaintSheet())->assertTooManyRequests();
    expect(Complaint::query()->count())->toBe(10);
});

it('takes at most 3 sheets a day for one address, so the book cannot mail strangers', function () {
    foreach (range(1, 3) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$attempt}"])
            ->post(route('complaints.store'), complaintSheet(['email' => 'Victima@Example.test']))->assertSessionHasNoErrors();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
        ->post(route('complaints.store'), complaintSheet(['email' => 'victima@example.test']))->assertSessionHasErrors('email');

    expect(Complaint::query()->count())->toBe(3);
});

it('ignores a sheet whose hidden trap field was filled', function () {
    $this->post(route('complaints.store'), complaintSheet(['website' => 'https://spam.example']))->assertSessionHasErrors('website');

    expect(Complaint::query()->count())->toBe(0);
});

it('never lets a sheet be edited or deleted', function () {
    $complaint = Complaint::factory()->create();

    expect(fn () => $complaint->update(['detail' => 'Otra cosa']))->toThrow(ComplaintException::class)
        ->and(fn () => $complaint->delete())->toThrow(ComplaintException::class);
    expect($complaint->fresh()?->detail)->not->toBe('Otra cosa');
});

it('records the answer once, dated, and emails it', function () {
    $admin = User::factory()->platformAdmin()->withTwoFactor()->create();
    $complaint = Complaint::factory()->create(['email' => 'alex@example.test']);

    app(RespondToComplaint::class)->handle($complaint, $admin, 'Activamos tu plan y te devolvimos los días.');

    $fresh = $complaint->fresh();
    expect($fresh?->response)->toBe('Activamos tu plan y te devolvimos los días.')
        ->and($fresh?->responded_at?->isSameDay(CarbonImmutable::now()))->toBeTrue()
        ->and($fresh?->responded_by)->toBe($admin->id);
    expect(fn () => app(RespondToComplaint::class)->handle($complaint, $admin, 'Otra respuesta'))->toThrow(ComplaintException::class)
        ->and(fn () => $fresh?->forceFill(['response' => 'Cambiada'])->save())->toThrow(ComplaintException::class);
    Notification::assertSentOnDemandTimes(ComplaintAnsweredNotification::class, 1);
});

it('opens the inbox only to the platform admin with two-factor', function () {
    $complaint = Complaint::factory()->create();
    $owner = User::factory()->withTwoFactor()->create();
    Salon::factory()->ownedBy($owner)->create();
    $index = '/plataforma/libro-de-reclamaciones';

    $this->get($index)->assertRedirect();
    $this->actingAs($owner)->get($index)->assertForbidden();
    $this->actingAs($owner)->get("{$index}/{$complaint->id}")->assertForbidden();
    $this->actingAs(User::factory()->platformAdmin()->create())->get($index)->assertRedirect(route('security.edit'));
    $this->actingAs(User::factory()->platformAdmin()->withTwoFactor()->create())
        ->get($index)
        ->assertOk()
        ->assertSee($complaint->code());
});

it('warns in the inbox when 5 and 2 business days are left', function () {
    $admin = User::factory()->platformAdmin()->withTwoFactor()->create();
    $calm = Complaint::factory()->dueOn(CarbonImmutable::parse('2026-11-06'))->create();
    $soon = Complaint::factory()->dueOn(CarbonImmutable::parse('2026-10-23'))->create();
    $urgent = Complaint::factory()->dueOn(CarbonImmutable::parse('2026-10-20'))->create();
    $late = Complaint::factory()->dueOn(CarbonImmutable::parse('2026-10-15'))->create();
    $this->actingAs($admin);
    Filament::setCurrentPanel('plataforma');

    Livewire::test(ListComplaints::class)
        ->assertTableColumnStateSet('deadline', 'Faltan 15 días hábiles', $calm)
        ->assertTableColumnStateSet('deadline', 'Faltan 5 días hábiles', $soon)
        ->assertTableColumnStateSet('deadline', 'Faltan 2 días hábiles', $urgent)
        ->assertTableColumnStateSet('deadline', 'Vencida', $late);
});

it('lets the platform admin answer from the inbox, once', function () {
    $admin = User::factory()->platformAdmin()->withTwoFactor()->create();
    $complaint = Complaint::factory()->create();
    $this->actingAs($admin);
    Filament::setCurrentPanel('plataforma');

    Livewire::test(ViewComplaint::class, ['record' => $complaint->getRouteKey()])
        ->callAction('respond', ['response' => 'Revisamos tu cuenta y ya está activa.'])
        ->assertHasNoActionErrors()
        ->assertActionHidden('respond');

    expect($complaint->fresh()?->response)->toBe('Revisamos tu cuenta y ya está activa.');
    Notification::assertSentOnDemandTimes(ComplaintAnsweredNotification::class, 1);
});

it('exports the whole book as CSV, oldest first', function () {
    $admin = User::factory()->platformAdmin()->withTwoFactor()->create();
    Complaint::factory()->create(['number' => 2, 'consumer_name' => '=HYPERLINK("x")']);
    Complaint::factory()->answered($admin)->create(['number' => 1]);
    $this->actingAs($admin);
    Filament::setCurrentPanel('plataforma');

    $download = Livewire::test(ListComplaints::class)->callAction('export');

    $download->assertFileDownloaded('libro-de-reclamaciones-2026-10-16.csv');
    $csv = (string) base64_decode((string) data_get($download->effects, 'download.content'), true);
    expect(strpos($csv, '000001-2026'))->toBeLessThan(strpos($csv, '000002-2026'))
        ->and($csv)->toContain("'=HYPERLINK")
        ->and($csv)->toContain('Revisamos tu cuenta y la reserva ya aparece.');
});
