<?php

use App\Enums\SalonPermission;
use App\Enums\SalonStatus;
use App\Models\Salon;
use App\Models\SalonMember;
use App\Models\SalonPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create(['slug' => 'barberia-lucho']);
});

function profilePayload(array $overrides = []): array
{
    return [
        'name' => 'Barbería Lucho',
        'business_type' => 'barbershop',
        'clientele' => 'men',
        'description' => 'Cortes clásicos y modernos desde 1998.',
        'phone' => '999 888 777',
        'instagram' => '@barberialucho',
        ...$overrides,
    ];
}

it('updates the salon profile without touching slug or status', function () {
    $this->actingAs($this->owner)
        ->put(route('salon.profile.update'), profilePayload(['slug' => 'otro', 'status' => 'active']))
        ->assertRedirect(route('salon.edit'));

    $salon = $this->salon->fresh();
    expect($salon?->instagram)->toBe('barberialucho')
        ->and($salon?->phone)->toBe('999888777')
        ->and($salon?->slug)->toBe('barberia-lucho')
        ->and($salon?->status)->toBe(SalonStatus::Trial);
});

it('only lets people with the profile permission edit the salon', function () {
    $staff = User::factory()->create();
    $member = SalonMember::factory()->for($this->salon)->for($staff)->create();

    $this->actingAs($staff)->get(route('salon.edit'))->assertForbidden();
    $this->actingAs($staff)->put(route('salon.profile.update'), profilePayload())->assertForbidden();

    $member->permissions = collect([SalonPermission::ManageSalonProfile]);
    $member->save();
    $this->actingAs($staff->fresh())->get(route('salon.edit'))->assertOk();
});

it('stores logo and cover as WebP of the right size', function () {
    $this->actingAs($this->owner)->post(route('salon.logo.store'), ['image' => UploadedFile::fake()->image('logo.png', 900, 900)]);
    $this->actingAs($this->owner)->post(route('salon.cover.store'), ['image' => UploadedFile::fake()->image('local.jpg', 3000, 2000)]);

    $salon = $this->salon->fresh();
    $logo = getimagesizefromstring(Storage::disk('public')->get((string) $salon?->logo_path));
    $cover = getimagesizefromstring(Storage::disk('public')->get((string) $salon?->cover_path));

    expect([$logo[0], $logo[1], $logo[2]])->toBe([512, 512, IMAGETYPE_WEBP])
        ->and([$cover[0], $cover[1]])->toBe([1600, 900]);
});

it('saves the brand color and warns when it had to adjust it', function () {
    $this->actingAs($this->owner)->put(route('salon.color.update'), ['brand_color' => '#ffeb3b']);

    expect($this->salon->fresh()?->brand_color)->toBe('#ffeb3b')
        ->and($this->salon->fresh()?->brandTokens()['adjusted'])->toBeTrue();

    $this->actingAs($this->owner)->put(route('salon.color.update'), ['brand_color' => 'url(javascript:1)'])
        ->assertSessionHasErrors('brand_color');
});

it('saves the location only with both coordinates', function () {
    $this->actingAs($this->owner)
        ->put(route('salon.location.update'), ['latitude' => -12.1211])
        ->assertSessionHasErrors('longitude');

    $this->actingAs($this->owner)->put(route('salon.location.update'), [
        'address' => 'Av. Larco 345', 'district' => 'Miraflores', 'city' => 'Lima',
        'latitude' => -12.1211, 'longitude' => -77.0297,
    ])->assertSessionHasNoErrors();

    expect($this->salon->fresh()?->hasLocation())->toBeTrue();
});

it('caps the gallery size', function () {
    foreach (range(1, SalonPhoto::MAX_PER_SALON) as $position) {
        (new SalonPhoto)->forceFill(['salon_id' => $this->salon->id, 'path' => "x{$position}.webp", 'position' => $position])->save();
    }

    $this->actingAs($this->owner)
        ->post(route('salon.photos.store'), ['image' => UploadedFile::fake()->image('extra.jpg')]);

    expect(SalonPhoto::withoutGlobalScopes()->where('salon_id', $this->salon->id)->count())->toBe(SalonPhoto::MAX_PER_SALON);
});

it('reorders photos and ignores ids from another salon', function () {
    $this->actingAs($this->owner)->post(route('salon.photos.store'), ['image' => UploadedFile::fake()->image('a.jpg')]);
    $this->actingAs($this->owner)->post(route('salon.photos.store'), ['image' => UploadedFile::fake()->image('b.jpg')]);
    [$first, $second] = SalonPhoto::withoutGlobalScopes()->orderBy('position')->get()->all();
    $foreign = (new SalonPhoto)->forceFill(['salon_id' => Salon::factory()->create()->id, 'path' => 'z.webp', 'position' => 7]);
    $foreign->save();

    $this->actingAs($this->owner)->put(route('salon.photos.reorder'), ['ids' => [$second->id, $foreign->id, $first->id]]);

    expect($second->fresh()?->position)->toBe(0)
        ->and($first->fresh()?->position)->toBe(2)
        ->and($foreign->fresh()?->position)->toBe(7);
});

it('cannot delete a photo of another salon', function () {
    $foreign = (new SalonPhoto)->forceFill(['salon_id' => Salon::factory()->create()->id, 'path' => 'z.webp']);
    $foreign->save();

    $this->actingAs($this->owner)->delete(route('salon.photos.destroy', $foreign))->assertNotFound();
});

it('guides the owner with a setup checklist on the dashboard', function () {
    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('setup', 8)
            ->where('setup.0.key', 'logo')
            ->where('setup.0.done', false)
            ->where('publicUrl', route('salons.show', 'barberia-lucho')));
});

it('hides the checklist from staff without the profile permission', function () {
    $staff = User::factory()->create();
    SalonMember::factory()->for($this->salon)->for($staff)->create();

    $this->actingAs($staff)->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('setup', null));
});

it('rejects markup and link syntax in free texts', function (string $field, string $route) {
    $payload = $route === 'salon.profile.update' ? profilePayload([$field => 'Paga [aquí](https://evil.tld)']) : [$field => '<b>Lima</b>'];

    $this->actingAs($this->owner)->put(route($route), $payload)->assertSessionHasErrors($field);
})->with([
    'description' => ['description', 'salon.profile.update'],
    'district' => ['district', 'salon.location.update'],
]);
