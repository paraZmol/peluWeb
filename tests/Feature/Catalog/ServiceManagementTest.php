<?php

use App\Enums\SalonPermission;
use App\Models\Salon;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\ServiceTemplate;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create();
});

function servicePayload(array $overrides = []): array
{
    return [
        'name' => 'Corte + barba',
        'category' => 'men_cut',
        'target' => 'men',
        'price' => '45.50',
        'active_minutes' => 60,
        'variants' => [],
        ...$overrides,
    ];
}

it('creates a custom service with the price stored in céntimos', function () {
    $this->actingAs($this->owner)
        ->post(route('services.store'), servicePayload())
        ->assertRedirect(route('services.index'));

    $service = Service::withoutGlobalScopes()->where('salon_id', $this->salon->id)->firstOrFail();
    expect($service->price_cents)->toBe(4550)
        ->and($service->totalMinutes())->toBe(60);
});

it('saves processing time segments and syncs variants', function () {
    $this->actingAs($this->owner)->post(route('services.store'), servicePayload([
        'name' => 'Tinte global',
        'category' => 'color',
        'target' => 'unisex',
        'active_minutes' => 40, 'idle_minutes' => 35, 'finish_minutes' => 30,
        'variants' => [['label' => 'Corto', 'price' => '120'], ['label' => 'Largo', 'price' => '180', 'extra_minutes' => 30]],
    ]));
    $service = Service::withoutGlobalScopes()->with('variants')->firstOrFail();
    $keep = $service->variants->first();

    $this->actingAs($this->owner)->put(route('services.update', $service), servicePayload([
        'name' => 'Tinte global', 'category' => 'color', 'target' => 'unisex',
        'active_minutes' => 40, 'idle_minutes' => 35, 'finish_minutes' => 30,
        'variants' => [['id' => $keep->id, 'label' => 'Corto o medio', 'price' => '130']],
    ]))->assertSessionHasNoErrors();

    $service->refresh()->load('variants');
    expect($service->totalMinutes())->toBe(105)
        ->and($service->variants)->toHaveCount(1)
        ->and($service->variants->first()->id)->toBe($keep->id)
        ->and($service->variants->first()->price_cents)->toBe(13000);
});

it('validates prices, minutes and markup in names', function (array $override, string $field) {
    $this->actingAs($this->owner)
        ->post(route('services.store'), servicePayload($override))
        ->assertSessionHasErrors($field);
})->with([
    'price with letters' => [['price' => 'treinta'], 'price'],
    'negative price' => [['price' => '-10'], 'price'],
    'zero minutes' => [['active_minutes' => 0], 'active_minutes'],
    'html in name' => [['name' => '<script>x</script>'], 'name'],
    'too many variants' => [['variants' => array_fill(0, 7, ['label' => 'X', 'price' => '10'])], 'variants'],
]);

it('lets staff manage services only with that permission', function () {
    $staff = User::factory()->create();
    $member = SalonMember::factory()->for($this->salon)->for($staff)->create();

    $this->actingAs($staff)->get(route('services.index'))->assertForbidden();
    $this->actingAs($staff)->post(route('services.store'), servicePayload())->assertForbidden();

    $member->permissions = collect([SalonPermission::ManageServices]);
    $member->save();

    $this->actingAs($staff->fresh())->get(route('services.index'))->assertOk();
});

it('hides services of another salon from every action', function () {
    $other = Salon::factory()->ownedBy()->create();
    $foreign = Service::factory()->for($other)->create();

    $this->actingAs($this->owner)->put(route('services.update', $foreign), servicePayload())->assertNotFound();
    $this->actingAs($this->owner)->patch(route('services.toggle', $foreign), ['is_active' => false])->assertNotFound();
    $this->actingAs($this->owner)->delete(route('services.destroy', $foreign))->assertNotFound();

    expect($foreign->fresh()?->is_active)->toBeTrue();
});

it('lists only this salon services and offers the rest of the catalog', function () {
    Service::factory()->for(Salon::factory()->create())->create(['name' => 'Servicio ajeno']);
    Service::factory()->for($this->salon)->create(['name' => 'Mi fade']);

    $this->actingAs($this->owner)
        ->get(route('services.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('owner/services/index')
            ->has('services', 1)
            ->where('services.0.name', 'Mi fade')
            ->has('catalog', ServiceTemplate::count()));
});

it('adds services from the catalog in one go', function () {
    $ids = ServiceTemplate::whereIn('code', ['CH06', 'BA01'])->pluck('id')->all();

    $this->actingAs($this->owner)->post(route('services.catalog.store'), ['template_ids' => $ids]);

    expect(Service::withoutGlobalScopes()->where('salon_id', $this->salon->id)->count())->toBe(2);
});

it('pauses and deletes a service', function () {
    $service = Service::factory()->for($this->salon)->create();

    $this->actingAs($this->owner)->patch(route('services.toggle', $service), ['is_active' => false]);
    expect($service->fresh()?->is_active)->toBeFalse();

    $this->actingAs($this->owner)->delete(route('services.destroy', $service));
    expect(Service::withoutGlobalScope('salon')->find($service->id))->toBeNull();
});
