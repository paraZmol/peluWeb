<?php

use App\Models\Salon;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| Step 6.4: the site installs as an app. From anywhere it is the platform;
| from a salon's page it is that salon, with its name and its logo.
*/

it('describes the platform as an installable app', function () {
    $this->get('/manifest.webmanifest')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('name', config('app.name'))
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('scope', '/')
        ->assertJsonPath('icons.0.sizes', '192x192')
        ->assertJsonPath('icons.2.purpose', 'maskable');

    foreach (['icon-192.png', 'icon-512.png', 'icon-maskable-512.png'] as $icon) {
        expect(file_exists(public_path("icons/{$icon}")))->toBeTrue();
    }
    expect(file_exists(public_path('sw.js')))->toBeTrue()
        ->and(file_exists(public_path('offline.html')))->toBeTrue();
});

it('installs a salon under its own name, colour and logo', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $salon = Salon::factory()->ownedBy($owner)->create(['name' => 'Barbería Ñandú del Centro', 'slug' => 'nandu', 'brand_color' => '#1d4ed8']);

    $this->get(route('salons.manifest', $salon))
        ->assertOk()
        ->assertJsonPath('name', 'Barbería Ñandú del Centro')
        ->assertJsonPath('short_name', 'Barbería Ñan')
        ->assertJsonPath('start_url', '/s/nandu')
        ->assertJsonPath('id', '/s/nandu')
        ->assertJsonPath('icons.0.src', '/s/nandu/icono-192.png')
        ->assertJsonPath('icons.1.src', '/s/nandu/icono-512.png');

    // Without a logo, the platform's icon.
    $this->get(route('salons.icon', [$salon, 192]))->assertRedirect('/icons/icon-192.png');

    $this->actingAs($owner)->post(route('salon.logo.store'), ['image' => UploadedFile::fake()->image('logo.png', 900, 600)]);
    auth()->logout();

    $icon = $this->get(route('salons.icon', [$salon, 192]))->assertOk()->assertHeader('Content-Type', 'image/png');
    $size = getimagesizefromstring((string) $icon->getContent());
    expect([$size[0], $size[1], $size[2]])->toBe([192, 192, IMAGETYPE_PNG]);

    // Only the sizes a manifest asks for: no resizing service for anyone.
    $this->get(route('salons.icon', [$salon, 4000]))->assertNotFound();
    $this->get('/s/no-existe/manifest.webmanifest')->assertNotFound();
});

it('points each page to the manifest it installs', function () {
    $salon = Salon::factory()->create(['name' => 'Estudio Luz', 'slug' => 'estudio-luz']);

    $this->get(route('salons.show', $salon))->assertOk()
        ->assertSee('<link rel="manifest" href="/s/estudio-luz/manifest.webmanifest">', false)
        ->assertSee('<link rel="apple-touch-icon" href="/s/estudio-luz/icono-192.png">', false);
    $this->get(route('salons.booking.create', $salon))->assertOk()
        ->assertSee('<link rel="manifest" href="/s/estudio-luz/manifest.webmanifest">', false);
    $this->get(route('login'))->assertOk()
        ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false);
});
