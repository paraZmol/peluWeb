<?php

use App\Enums\SalonPermission;
use App\Models\Salon;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\ServiceTemplate;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create();
    $this->staff = Staff::factory()->for($this->salon)->create();
    $this->fade = Service::factory()->for($this->salon)->create(['name' => 'Fade', 'price_cents' => 3500, 'active_minutes' => 45]);
    $this->beard = Service::factory()->for($this->salon)->create(['name' => 'Barba', 'price_cents' => 2000, 'active_minutes' => 20]);
});

/**
 * @return Collection<int, stdClass>
 */
function pivotsOf(Staff $staff): Collection
{
    return DB::table('service_staff')->where('staff_id', $staff->id)->get()->keyBy('service_id');
}

it('sets which services a professional performs with their own time and price', function () {
    $this->actingAs($this->owner)
        ->put(route('staff.services.update', $this->staff), ['services' => [
            ['service_id' => $this->fade->id, 'active_minutes' => 30, 'price' => '40.50'],
            ['service_id' => $this->beard->id],
        ]])
        ->assertSessionHasNoErrors();

    $pivots = pivotsOf($this->staff);
    expect($pivots)->toHaveCount(2)
        ->and($pivots[$this->fade->id]->active_minutes)->toBe(30)
        ->and($pivots[$this->fade->id]->price_cents)->toBe(4050)
        ->and($pivots[$this->beard->id]->active_minutes)->toBeNull()
        ->and($pivots[$this->beard->id]->price_cents)->toBeNull();
});

it('removes the services left unticked', function () {
    $this->staff->services()->sync([$this->fade->id, $this->beard->id]);

    $this->actingAs($this->owner)
        ->put(route('staff.services.update', $this->staff), ['services' => [['service_id' => $this->beard->id]]]);

    expect(pivotsOf($this->staff)->keys()->all())->toBe([$this->beard->id]);
});

it('shows every salon service with what this professional overrides', function () {
    $this->staff->services()->sync([$this->fade->id => ['active_minutes' => 30, 'price_cents' => 4000]]);

    $this->actingAs($this->owner)
        ->get(route('staff.schedule.show', $this->staff))
        ->assertInertia(fn (Assert $page) => $page
            ->has('staff.services', 2)
            ->where('staff.services.0.name', 'Fade')
            ->where('staff.services.0.assigned', true)
            ->where('staff.services.0.activeMinutes', 30)
            ->where('staff.services.0.price', '40.00')
            ->where('staff.services.0.basePrice', '35.00')
            ->where('staff.services.1.assigned', false)
            ->where('staff.services.1.price', null));
});

it('lets professionals set their own time but never their price or which services they do', function () {
    $member = SalonMember::factory()->for($this->salon)->create();
    $own = Staff::factory()->for($this->salon)->linkedTo($member->user)->create();
    $own->services()->sync([$this->fade->id => ['price_cents' => 4000]]);

    $this->actingAs($member->user)
        ->put(route('staff.services.update', $own), ['services' => [
            ['service_id' => $this->fade->id, 'active_minutes' => 25, 'price' => '1.00'],
            ['service_id' => $this->beard->id, 'price' => '1.00'],
        ]])
        ->assertSessionHasNoErrors();

    $pivots = pivotsOf($own);
    expect($pivots[$this->fade->id]->active_minutes)->toBe(25)
        ->and($pivots[$this->fade->id]->price_cents)->toBe(4000)
        ->and($pivots)->not->toHaveKey($this->beard->id);

    // Leaving a service out does not remove it either.
    $this->actingAs($member->user)->put(route('staff.services.update', $own), ['services' => []]);
    expect(pivotsOf($own))->toHaveKey($this->fade->id);

    $this->actingAs($member->user)
        ->get(route('staff.schedule.show', $own))
        ->assertInertia(fn (Assert $page) => $page->where('canSetPrices', false)->where('canManageTeam', false));
});

it('lets someone with the services permission price their own work', function () {
    $member = SalonMember::factory()->for($this->salon)->withPermissions(SalonPermission::ManageServices)->create();
    $own = Staff::factory()->for($this->salon)->linkedTo($member->user)->create();

    $this->actingAs($member->user)
        ->put(route('staff.services.update', $own), ['services' => [['service_id' => $this->fade->id, 'price' => '50']]]);

    expect(pivotsOf($own)[$this->fade->id]->price_cents)->toBe(5000);
});

it('refuses to edit a colleague\'s services', function () {
    $member = SalonMember::factory()->for($this->salon)->withPermissions(SalonPermission::ManageServices)->create();

    $this->actingAs($member->user)
        ->put(route('staff.services.update', $this->staff), ['services' => []])
        ->assertForbidden();
});

it('ignores services of another salon', function () {
    $foreign = Service::factory()->for(Salon::factory()->create())->create();

    $this->actingAs($this->owner)
        ->put(route('staff.services.update', $this->staff), ['services' => [
            ['service_id' => $foreign->id, 'price' => '1'],
            ['service_id' => $this->fade->id],
        ]])
        ->assertSessionHasNoErrors();

    expect(pivotsOf($this->staff)->keys()->all())->toBe([$this->fade->id]);
});

it('validates times and prices', function (array $entry, string $field) {
    $this->actingAs($this->owner)
        ->put(route('staff.services.update', $this->staff), ['services' => [['service_id' => $this->fade->id, ...$entry]]])
        ->assertSessionHasErrors($field);
})->with([
    'zero minutes' => [['active_minutes' => 0], 'services.0.active_minutes'],
    'a whole day' => [['active_minutes' => 600], 'services.0.active_minutes'],
    'negative price' => [['price' => '-5'], 'services.0.price'],
    'price with letters' => [['price' => 'gratis'], 'services.0.price'],
]);

it('refuses the same service twice', function () {
    $this->actingAs($this->owner)
        ->put(route('staff.services.update', $this->staff), ['services' => [
            ['service_id' => $this->fade->id], ['service_id' => $this->fade->id],
        ]])
        ->assertSessionHasErrors('services.0.service_id');
});

it('assigns a new service to the whole team', function () {
    $second = Staff::factory()->for($this->salon)->create();
    $foreignStaff = Staff::factory()->for(Salon::factory()->create())->create();

    $this->actingAs($this->owner)->post(route('services.store'), [
        'name' => 'Corte infantil', 'category' => 'men_cut', 'target' => 'men', 'price' => '25', 'active_minutes' => 30, 'variants' => [],
    ]);
    $this->actingAs($this->owner)->post(route('services.catalog.store'), [
        'template_ids' => [ServiceTemplate::query()->value('id')],
    ]);

    expect(pivotsOf($this->staff))->toHaveCount(2)
        ->and(pivotsOf($second))->toHaveCount(2)
        ->and(pivotsOf($foreignStaff))->toHaveCount(0);
});
