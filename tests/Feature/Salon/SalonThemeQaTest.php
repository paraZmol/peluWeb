<?php

use App\Actions\Booking\CreateBooking;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\BusinessType;
use App\Enums\SalonPermission;
use App\Enums\SalonTheme;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Step 4.12 (d): QA of the per-salon templates by role. Complements
| SalonThemeTest (happy paths) with who may NOT choose or preview a
| template, what a visitor can never force, and every screen inheriting it.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create([
        'slug' => 'estudio-luz', 'timezone' => 'America/Lima', 'theme' => SalonTheme::Nocturna, 'brand_color' => null,
    ]);
    $this->stylist = Staff::factory()->for($this->salon)->working([1, 2, 3, 4, 5], '09:00', '18:00')->create(['name' => 'Lucía']);
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'active_minutes' => 30]);
    $this->stylist->services()->attach($this->cut->id);

    // Another salon, owned by somebody else, with its own template.
    $this->rivalOwner = User::factory()->create();
    $this->rival = Salon::factory()->ownedBy($this->rivalOwner)->create(['slug' => 'rival', 'theme' => SalonTheme::Editorial]);
});

describe('who may change the template', function () {
    it('lets a staff member with the profile permission, and only that one', function () {
        $allowed = SalonMember::factory()->for($this->salon)->withPermissions(SalonPermission::ManageSalonProfile)->create();
        $blocked = SalonMember::factory()->for($this->salon)->withPermissions(SalonPermission::ManageServices, SalonPermission::ViewReports)->create();

        $this->actingAs($blocked->user)->put(route('salon.theme.update'), ['theme' => 'luminosa'])->assertForbidden();
        expect($this->salon->refresh()->theme)->toBe(SalonTheme::Nocturna);

        $this->actingAs($allowed->user)->put(route('salon.theme.update'), ['theme' => 'luminosa'])->assertRedirect(route('salon.edit'));
        expect($this->salon->refresh()->theme)->toBe(SalonTheme::Luminosa);
    });

    it('does not show the design section to a staff member without the permission', function () {
        $blocked = SalonMember::factory()->for($this->salon)->create();

        $this->actingAs($blocked->user)->get(route('salon.edit'))->assertForbidden();
    });

    it('saves each of the four templates and reports it to the settings page', function (string $theme) {
        $this->actingAs($this->owner)->put(route('salon.theme.update'), ['theme' => $theme])->assertRedirect(route('salon.edit'));

        $this->actingAs($this->owner)->get(route('salon.edit'))->assertInertia(fn (Assert $page) => $page->where('salon.theme', $theme));
        $this->get('/s/estudio-luz')->assertInertia(fn (Assert $page) => $page->where('salon.theme', $theme));
    })->with(['editorial', 'nocturna', 'luminosa', 'vibrante']);

    it('rejects an empty, array or odd-cased value', function (mixed $value) {
        $this->actingAs($this->owner)->put(route('salon.theme.update'), ['theme' => $value])->assertSessionHasErrors('theme');

        expect($this->salon->refresh()->theme)->toBe(SalonTheme::Nocturna);
    })->with(['', ' ', 'NOCTURNA', 'Nocturna', 'ñandú 💈', [['nocturna']], '../vibrante']);

    it('needs a session', function () {
        $this->put(route('salon.theme.update'), ['theme' => 'vibrante'])->assertRedirect(route('login'));
        expect($this->salon->refresh()->theme)->toBe(SalonTheme::Nocturna);
    });

    it('never lets a client who has booked there choose the template', function () {
        $client = User::factory()->create();

        $this->actingAs($client)->put(route('salon.theme.update'), ['theme' => 'vibrante'])->assertStatus(403);
        expect($this->salon->refresh()->theme)->toBe(SalonTheme::Nocturna);
    });
});

describe('another salon', function () {
    it('is never touched by an owner of a different salon', function () {
        $this->actingAs($this->rivalOwner)->put(route('salon.theme.update'), ['theme' => 'vibrante'])->assertRedirect();

        expect($this->rival->refresh()->theme)->toBe(SalonTheme::Vibrante)
            ->and($this->salon->refresh()->theme)->toBe(SalonTheme::Nocturna);
    });

    it('cannot be selected as the current salon by forging the session', function () {
        $this->actingAs($this->rivalOwner)->withSession(['current_salon_id' => $this->salon->id])
            ->put(route('salon.theme.update'), ['theme' => 'vibrante']);

        expect($this->salon->refresh()->theme)->toBe(SalonTheme::Nocturna);
        $this->actingAs($this->rivalOwner)->put(route('current-salon.update', $this->salon))->assertForbidden();
    });

    it('gets no preview of a salon that is not theirs', function () {
        $url = route('salons.show', ['salon' => 'estudio-luz', 'diseno' => 'vibrante', 'muestra' => 1]);

        $this->actingAs($this->rivalOwner)->get($url)
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertInertia(fn (Assert $page) => $page
                ->where('salon.theme', 'nocturna')
                ->where('salon.isPreview', false)
                ->where('salon.usesSamplePhotos', false)
                ->where('salon.coverUrl', null)
                ->where('salon.photos', []));
    });

    it('keeps each salon on its own template in the public pages', function () {
        $this->get('/s/rival')->assertInertia(fn (Assert $page) => $page->where('salon.theme', 'editorial'));
        $this->get('/s/estudio-luz')->assertInertia(fn (Assert $page) => $page->where('salon.theme', 'nocturna'));
    });
});

describe('visitors and previews', function () {
    it('ignores ?diseno and ?muestra in every public page for a visitor', function (string $query) {
        $this->get("/s/estudio-luz?{$query}")->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertInertia(fn (Assert $page) => $page
                ->where('salon.theme', 'nocturna')
                ->where('salon.isPreview', false)
                ->where('salon.usesSamplePhotos', false)
                ->where('salon.coverUrl', null)
                ->where('salon.photos', [])
                ->where('salon.team.0.photoUrl', null));

        $this->get("/s/estudio-luz/reservar?{$query}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('salon.theme', 'nocturna'));
    })->with(['diseno=vibrante', 'muestra=1', 'diseno=luminosa&muestra=1', 'diseno[]=x&muestra[]=1', 'diseno=%F0%9F%92%88&muestra=%C3%B1']);

    it('shows no sample photo for a salon without photos when nobody asks for samples', function () {
        $this->actingAs($this->owner)->get('/s/estudio-luz')->assertInertia(fn (Assert $page) => $page
            ->where('salon.usesSamplePhotos', false)
            ->where('salon.coverUrl', null)
            ->where('salon.photos', [])
            ->where('salon.team.0.photoUrl', null));

        // Changing only the template does not bring samples either.
        $this->actingAs($this->owner)->get('/s/estudio-luz?diseno=vibrante')->assertInertia(fn (Assert $page) => $page
            ->where('salon.theme', 'vibrante')
            ->where('salon.isPreview', true)
            ->where('salon.usesSamplePhotos', false)
            ->where('salon.coverUrl', null));
    });

    it('previews every template for the owner without saving any of them', function (string $theme) {
        $this->actingAs($this->owner)->get(route('salons.show', ['salon' => 'estudio-luz', 'diseno' => $theme]))
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertInertia(fn (Assert $page) => $page->where('salon.theme', $theme)->where('salon.isPreview', true));

        expect($this->salon->refresh()->theme)->toBe(SalonTheme::Nocturna);
    })->with(['editorial', 'nocturna', 'luminosa', 'vibrante']);

    it('previews for a staff member with the profile permission but not for one without it', function () {
        $allowed = SalonMember::factory()->for($this->salon)->withPermissions(SalonPermission::ManageSalonProfile)->create();
        $blocked = SalonMember::factory()->for($this->salon)->create();
        $url = route('salons.show', ['salon' => 'estudio-luz', 'diseno' => 'vibrante']);

        $this->actingAs($blocked->user)->get($url)->assertHeader('X-Frame-Options', 'DENY')
            ->assertInertia(fn (Assert $page) => $page->where('salon.theme', 'nocturna')->where('salon.isPreview', false));
        $this->actingAs($allowed->user)->get($url)->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertInertia(fn (Assert $page) => $page->where('salon.theme', 'vibrante')->where('salon.isPreview', true));
    });

    it('lets the booking wizard and its data ignore previews even for the owner', function () {
        $this->actingAs($this->owner)->get('/s/estudio-luz/reservar?diseno=vibrante&muestra=1')
            ->assertInertia(fn (Assert $page) => $page->where('salon.theme', 'nocturna'));
    });
});

describe('every screen inherits the saved template', function () {
    it('tells the template to the wizard and to Mis reservas (list and detail)', function (string $theme) {
        $this->salon->forceFill(['theme' => $theme])->save();
        $customer = User::factory()->create();
        $client = SalonClient::factory()->for($this->salon)->forUser($customer)->create();
        $booking = app(CreateBooking::class)->handle(new NewBooking(
            salon: $this->salon->refresh(),
            client: $client,
            selections: [['service_id' => $this->cut->id, 'variant_id' => null]],
            staff: $this->stylist,
            startsAt: CarbonImmutable::parse('2026-10-08 10:00', 'America/Lima')->utc(),
            source: BookingSource::Client,
        ));

        $this->actingAs($customer)->get('/s/estudio-luz/reservar')->assertInertia(fn (Assert $page) => $page->where('salon.theme', $theme));
        $this->actingAs($customer)->get(route('bookings.index'))->assertInertia(fn (Assert $page) => $page->where('bookings.0.salon.theme', $theme));
        $this->actingAs($customer)->get(route('bookings.show', $booking->code))->assertInertia(fn (Assert $page) => $page->where('booking.salon.theme', $theme));
        // A forced template in the query never reaches the client's own pages.
        $this->actingAs($customer)->get(route('bookings.index', ['diseno' => 'vibrante']))->assertInertia(fn (Assert $page) => $page->where('bookings.0.salon.theme', $theme));
    })->with(['editorial', 'nocturna', 'luminosa', 'vibrante']);
});

describe('the template a new salon starts with', function () {
    it('follows the kind of business when the salon is created through the form', function (BusinessType $type, SalonTheme $expected) {
        $owner = User::factory()->create();

        $this->actingAs($owner)->post(route('salons.store'), [
            'name' => 'Ñandú & Hijos',
            'business_type' => $type->value,
            'clientele' => 'everyone', 'salon_terms' => '1',
        ])->assertRedirect(route('dashboard'));

        $created = Salon::query()->where('name', 'Ñandú & Hijos')->firstOrFail();
        expect($created->theme)->toBe($expected);
    })->with([
        'barbershop' => [BusinessType::Barbershop, SalonTheme::Nocturna],
        'unisex' => [BusinessType::Unisex, SalonTheme::Nocturna],
        'beauty salon' => [BusinessType::BeautySalon, SalonTheme::Luminosa],
        'nails' => [BusinessType::Nails, SalonTheme::Luminosa],
        'spa' => [BusinessType::Spa, SalonTheme::Luminosa],
    ]);

    it('has an answer for every kind of business', function () {
        foreach (BusinessType::cases() as $type) {
            expect(SalonTheme::defaultFor($type))->toBeInstanceOf(SalonTheme::class);
        }
    });
});

describe('a dark neutral brand color', function () {
    it('reaches the public page as a light button with dark text for dark mode', function (string $hex) {
        $this->salon->forceFill(['brand_color' => $hex, 'theme' => SalonTheme::Nocturna])->save();

        $this->get('/s/estudio-luz')->assertInertia(fn (Assert $page) => $page
            ->where('salon.brand.dark.foreground', '#1f1b18')
            ->where('salon.brand.dark.primary', fn (string $primary) => $primary !== $hex && $primary > '#a0a0a0')
            ->where('salon.brand.light.primary', strtolower($hex)));
    })->with(['#1f1b18', '#1F2937', '#111111']);

    it('keeps the saturated brand colors as they were', function () {
        $this->salon->forceFill(['brand_color' => '#E11D48'])->save();

        $this->get('/s/estudio-luz')->assertInertia(fn (Assert $page) => $page
            ->where('salon.brand.dark.primary', '#e11d48')
            ->where('salon.brand.dark.foreground', '#ffffff'));
    });
});
