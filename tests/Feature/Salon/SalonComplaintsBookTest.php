<?php

use App\Models\Complaint;
use App\Models\Salon;
use App\Models\SalonMember;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Each salon's own Libro de Reclamaciones and who provides the service
| (requirement P7): optional details in "Mi salón", a fixed line at the
| foot of its public page, and a step of the setup checklist.
*/

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create(['slug' => 'salon-norte', 'address' => 'Av. Grau 10']);
});

it('saves the salon’s legal details and the link to its book', function () {
    $this->actingAs($this->owner)
        ->put(route('salon.legal.update'), [
            'legal_name' => ' Servicios Norte E.I.R.L. ',
            'ruc' => '20123456789',
            'contact_email' => 'hola@salon-norte.test',
            'complaints_book_url' => 'https://libro.example.test/salon-norte',
        ])
        ->assertRedirect(route('salon.edit'));

    $salon = $this->salon->fresh();
    expect($salon?->legal_name)->toBe('Servicios Norte E.I.R.L.')
        ->and($salon?->ruc)->toBe('20123456789')
        ->and($salon?->contact_email)->toBe('hola@salon-norte.test')
        ->and($salon?->complaints_book_url)->toBe('https://libro.example.test/salon-norte');
});

it('keeps every legal detail optional, and clears them when emptied', function () {
    $this->salon->forceFill(['ruc' => '20123456789'])->save();

    $this->actingAs($this->owner)
        ->put(route('salon.legal.update'), ['legal_name' => '', 'ruc' => '', 'contact_email' => '', 'complaints_book_url' => ''])
        ->assertSessionHasNoErrors();

    expect($this->salon->fresh()?->ruc)->toBeNull();
});

it('only takes https links and well-formed RUCs', function () {
    $this->actingAs($this->owner)
        ->put(route('salon.legal.update'), ['complaints_book_url' => 'javascript:alert(1)', 'ruc' => '12345'])
        ->assertSessionHasErrors(['complaints_book_url', 'ruc']);
    $this->actingAs($this->owner)
        ->put(route('salon.legal.update'), ['complaints_book_url' => 'http://libro.example.test'])
        ->assertSessionHasErrors('complaints_book_url');

    expect($this->salon->fresh()?->complaints_book_url)->toBeNull();
});

it('only lets people with the profile permission change them, and a foreign salon id in the session changes nothing there', function () {
    $staff = User::factory()->create();
    SalonMember::factory()->for($this->salon)->for($staff)->create();
    $other = Salon::factory()->ownedBy()->create();

    $this->actingAs($staff)->put(route('salon.legal.update'), ['ruc' => '20123456789'])->assertForbidden();
    $this->actingAs($this->owner)
        ->withSession(['current_salon_id' => $other->id])
        ->put(route('salon.legal.update'), ['ruc' => '20999999999']);

    expect($other->fresh()?->ruc)->toBeNull()
        ->and($this->salon->fresh()?->ruc)->toBe('20999999999');
});

it('shows the book on the salon page: its link, or where it is kept', function () {
    $page = fn () => $this->get(route('salons.show', 'salon-norte'));

    $page()->assertInertia(fn (Assert $page) => $page
        ->where('salon.complaintsBook.url', null)
        ->where('salon.address', 'Av. Grau 10'));

    $this->salon->forceFill(['complaints_book_url' => 'https://libro.example.test/norte', 'legal_name' => 'Servicios Norte E.I.R.L.', 'ruc' => '20123456789', 'contact_email' => 'privado@salon-norte.test'])->save();

    $page()
        ->assertInertia(fn (Assert $page) => $page
            ->where('salon.complaintsBook.url', 'https://libro.example.test/norte')
            ->where('salon.complaintsBook.legalName', 'Servicios Norte E.I.R.L.')
            ->where('salon.complaintsBook.ruc', '20123456789'))
        // The salon's email is for the platform, not for the public page.
        ->assertDontSee('privado@salon-norte.test');
});

it('adds the book to the setup checklist until there is a link', function () {
    $step = fn () => collect($this->actingAs($this->owner)->get(route('dashboard'))->inertiaProps('setup'))->firstWhere('key', 'complaints-book');

    expect($step()['done'])->toBeFalse()
        ->and($step()['label'])->toContain('Indecopi');

    $this->actingAs($this->owner)->put(route('salon.legal.update'), ['complaints_book_url' => 'https://libro.example.test/norte']);

    expect($step()['done'])->toBeTrue();
});

it('never shows the platform’s complaint sheets to a salon owner', function () {
    $complaint = Complaint::factory()->create(['consumer_name' => 'Persona Reclamante']);

    $this->actingAs($this->owner)->get('/plataforma/libro-de-reclamaciones')->assertForbidden();
    $this->actingAs($this->owner)->get("/plataforma/libro-de-reclamaciones/{$complaint->id}")->assertForbidden();
    $this->actingAs($this->owner)->get(route('salon.edit'))->assertDontSee('Persona Reclamante');
});
