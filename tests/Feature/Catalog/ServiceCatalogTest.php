<?php

use App\Actions\Catalog\AddServicesFromTemplates;
use App\Enums\BusinessType;
use App\Models\Salon;
use App\Models\Service;
use App\Models\ServiceTemplate;
use App\Models\User;

function servicesOf(Salon $salon)
{
    return Service::withoutGlobalScope('salon')->where('salon_id', $salon->id);
}

it('ships the full platform catalog with every environment', function () {
    expect(ServiceTemplate::count())->toBe(116)
        ->and(ServiceTemplate::where('code', 'CH06')->value('name'))->toBe('Degradado medio');
});

it('gives a new salon the starter services of its type', function (string $type, int $expected, string $mustHave, string $mustNotHave) {
    $owner = User::factory()->create();

    $this->actingAs($owner)->post(route('salons.store'), [
        'name' => 'Salón de prueba', 'business_type' => $type, 'clientele' => 'everyone',
    ]);

    $salon = Salon::firstOrFail();
    $names = servicesOf($salon)->pluck('name');

    expect($names)->toHaveCount($expected)
        ->and($names)->toContain($mustHave)
        ->and($names)->not->toContain($mustNotHave);
})->with([
    'barbería' => [BusinessType::Barbershop->value, 36, 'Degradado medio', 'Balayage'],
    'uñas' => [BusinessType::Nails->value, 14, 'Manicure clásica', 'Degradado medio'],
]);

it('creates hair length options for services priced by length', function () {
    $salon = Salon::factory()->create();
    $template = ServiceTemplate::where('code', 'CO02')->firstOrFail(); // tinte global, "+ largo"

    app(AddServicesFromTemplates::class)->handle($salon, collect([$template]));

    $service = servicesOf($salon)->with('variants')->firstOrFail();
    expect($service->variants->pluck('label')->all())->toBe(['Cabello corto', 'Cabello medio', 'Cabello largo'])
        ->and($service->variants->last()->price_cents)->toBeGreaterThan($service->variants->first()->price_cents)
        ->and($service->idle_minutes)->toBeGreaterThan(0);
});

it('restores a removed service instead of duplicating it', function () {
    $salon = Salon::factory()->create();
    $template = ServiceTemplate::where('code', 'CH06')->firstOrFail();
    $add = app(AddServicesFromTemplates::class);

    $add->handle($salon, collect([$template]));
    servicesOf($salon)->firstOrFail()->delete();
    $add->handle($salon, collect([$template]));

    expect(servicesOf($salon)->count())->toBe(1);
});

it('suggests a price in whole soles inside the Lima range', function () {
    $template = ServiceTemplate::where('code', 'CH06')->firstOrFail();

    expect($template->suggestedPriceCents() % 100)->toBe(0)
        ->and($template->suggestedPriceCents())->toBeBetween($template->price_min_cents, $template->price_max_cents);
});
