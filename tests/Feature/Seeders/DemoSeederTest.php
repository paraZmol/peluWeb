<?php

use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('creates complete demo salons through the real actions', function () {
    $this->seed(DemoSeeder::class);

    $barbershop = Salon::where('slug', 'barberia-don-lucho')->firstOrFail();

    expect(Salon::count())->toBe(4)
        ->and($barbershop->logo_path)->not->toBeNull()
        ->and($barbershop->hasLocation())->toBeTrue()
        ->and($barbershop->members()->count())->toBe(4)
        ->and(Service::withoutGlobalScope('salon')->where('salon_id', $barbershop->id)->where('name', 'Degradado medio')->value('price_cents'))->toBe(3500)
        ->and(User::where('email', 'admin@peluweb.test')->value('is_platform_admin'))->toBeTrue();
});

it('can run twice without duplicating anything', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    expect(Salon::count())->toBe(4);
});

it('serves the demo public pages', function () {
    $this->seed(DemoSeeder::class);

    $this->get('/s/studio-bella')->assertOk();
});

it('uses root-relative image URLs so they load from any host', function () {
    $this->seed(DemoSeeder::class);

    $this->get('/s/barberia-don-lucho')
        ->assertInertia(fn ($page) => $page->where('salon.logoUrl', fn (string $url) => str_starts_with($url, '/storage/')));
});
