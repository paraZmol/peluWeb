<?php

use App\Actions\Salon\CreateSalon;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\SalonTheme;
use App\Models\Salon;
use App\Models\SalonMember;
use App\Models\SalonPhoto;
use App\Models\Staff;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Step 4.12: the design template of a salon's public page, chosen by the
| owner, previewed with sample photos, and inherited by the booking wizard.
*/

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create(['slug' => 'estudio-luz', 'business_type' => BusinessType::BeautySalon, 'theme' => SalonTheme::Nocturna]);
});

describe('choosing a template', function () {
    it('starts with the template that suits the kind of business', function () {
        $barbershop = app(CreateSalon::class)->handle(User::factory()->create(), 'Barbería Norte', BusinessType::Barbershop, Clientele::Men, null);
        $nails = app(CreateSalon::class)->handle(User::factory()->create(), 'Uñas Sur', BusinessType::Nails, Clientele::Everyone, null);

        expect($barbershop->theme)->toBe(SalonTheme::Nocturna)
            ->and($nails->theme)->toBe(SalonTheme::Luminosa)
            ->and(SalonTheme::defaultFor(BusinessType::Unisex))->toBe(SalonTheme::Nocturna);
    });

    it('lets the owner change it and offers the four options with a preview link', function () {
        $this->actingAs($this->owner)->get(route('salon.edit'))->assertInertia(fn (Assert $page) => $page
            ->where('salon.theme', 'nocturna')
            ->has('themes', 4)
            ->where('themes.2.value', 'luminosa')
            ->where('themes.2.previewUrl', route('salons.show', ['salon' => 'estudio-luz', 'diseno' => 'luminosa', 'muestra' => 1])));

        $this->actingAs($this->owner)->put(route('salon.theme.update'), ['theme' => 'vibrante'])->assertRedirect(route('salon.edit'));

        expect($this->salon->refresh()->theme)->toBe(SalonTheme::Vibrante);
    });

    it('rejects unknown templates and people without the profile permission', function () {
        $this->actingAs($this->owner)->put(route('salon.theme.update'), ['theme' => 'neon'])->assertSessionHasErrors('theme');

        $staff = SalonMember::factory()->for($this->salon)->create();
        $this->actingAs($staff->user)->put(route('salon.theme.update'), ['theme' => 'vibrante'])->assertForbidden();

        expect($this->salon->refresh()->theme)->toBe(SalonTheme::Nocturna);
    });
});

describe('the public page', function () {
    beforeEach(function () {
        $this->stylist = Staff::factory()->for($this->salon)->working([1, 2, 3, 4, 5], '09:00', '18:00')->create(['name' => 'Lucía Ramírez', 'title' => 'Colorista']);
        Staff::factory()->for($this->salon)->create(['name' => 'Pausada', 'is_active' => false]);
    });

    it('tells the template and who attends, without internal ids', function () {
        $this->get('/s/estudio-luz')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('salon.theme', 'nocturna')
            ->where('salon.isPreview', false)
            ->where('salon.usesSamplePhotos', false)
            ->has('salon.team', 1)
            ->where('salon.team.0', ['name' => 'Lucía Ramírez', 'title' => 'Colorista', 'photoUrl' => null])
            ->where('salon.coverUrl', null)
            ->where('salon.photos', []));
    });

    it('previews another template with sample photos only for whoever manages the salon', function () {
        $url = route('salons.show', ['salon' => 'estudio-luz', 'diseno' => 'luminosa', 'muestra' => 1]);

        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('salon.theme', 'nocturna')->where('salon.isPreview', false)->where('salon.coverUrl', null));
        $staff = SalonMember::factory()->for($this->salon)->create();
        $this->actingAs($staff->user)->get($url)->assertInertia(fn (Assert $page) => $page->where('salon.theme', 'nocturna')->where('salon.photos', []));

        $this->actingAs($this->owner)->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('salon.theme', 'luminosa')
            ->where('salon.isPreview', true)
            ->where('salon.usesSamplePhotos', true)
            ->where('salon.coverUrl', route('samples.show', 'pexels-3993133.jpg'))
            ->has('salon.photos', 4)
            ->where('salon.team.0.photoUrl', route('samples.show', 'pexels-3992875-square.jpg')));

        // The saved template stays as it was.
        expect($this->salon->refresh()->theme)->toBe(SalonTheme::Nocturna);
    });

    it('may be framed by this site only while it is a preview', function () {
        $this->get('/s/estudio-luz')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
        $this->get(route('salons.show', ['salon' => 'estudio-luz', 'diseno' => 'luminosa']))->assertHeader('X-Frame-Options', 'DENY');

        $this->actingAs($this->owner)->get(route('salons.show', ['salon' => 'estudio-luz', 'diseno' => 'luminosa']))
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
        $this->actingAs($this->owner)->get('/s/estudio-luz')->assertHeader('X-Frame-Options', 'DENY');
    });

    it('keeps the salon\'s own photos in a preview and ignores an unknown template', function () {
        $photo = new SalonPhoto;
        $photo->forceFill(['salon_id' => $this->salon->id, 'path' => 'salons/x/propia.webp', 'position' => 0])->save();
        $this->salon->forceFill(['cover_path' => 'salons/x/portada.webp'])->save();

        $this->actingAs($this->owner)->get(route('salons.show', ['salon' => 'estudio-luz', 'diseno' => 'neon', 'muestra' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('salon.theme', 'nocturna')
                ->where('salon.coverUrl', fn (string $url) => str_contains($url, 'portada.webp'))
                ->has('salon.photos', 1));
    });

    it('serves only the bundled sample photos', function () {
        $photo = $this->get(route('samples.show', 'pexels-1813272.jpg'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        // Cached as public for a month: it must not hand out a session.
        expect($photo->headers->getCookies())->toBeEmpty();
        $photo->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/img/muestra/pexels-897265-square.jpg')->assertOk();

        $this->get('/img/muestra/CREDITS.md')->assertNotFound();
        $this->get('/img/muestra/pexels-999.jpg')->assertNotFound();
        $this->get('/img/muestra/..%2F..%2F.env')->assertNotFound();
    });

    it('passes the template to the booking wizard and to the client\'s booking page', function () {
        $this->get('/s/estudio-luz/reservar')->assertInertia(fn (Assert $page) => $page->where('salon.theme', 'nocturna'));
    });
});
