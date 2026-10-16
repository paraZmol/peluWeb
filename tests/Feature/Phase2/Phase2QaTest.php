<?php

use App\Enums\SalonPermission;
use App\Enums\SalonStatus;
use App\Models\Salon;
use App\Models\SalonMember;
use App\Models\SalonPhoto;
use App\Models\Service;
use App\Models\ServiceTemplate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    $this->owner = User::factory()->create();
    $this->salon = Salon::factory()->ownedBy($this->owner)->create(['slug' => 'qa-salon']);
});

function qaService(array $o = []): array
{
    return ['name' => 'Corte', 'category' => 'men_cut', 'target' => 'men', 'price' => '35', 'active_minutes' => 30, 'variants' => [], ...$o];
}

function qaStaff(Salon $salon, array $perms = []): User
{
    $user = User::factory()->create();
    $member = SalonMember::factory()->for($salon)->for($user)->create();
    if ($perms) {
        $member->permissions = collect($perms);
        $member->save();
    }

    return $user;
}

function qaCount(Salon $salon): int
{
    return Service::withoutGlobalScope('salon')->where('salon_id', $salon->id)->count();
}

function qaSetup($test): array
{
    return collect($test->actingAs($test->owner)->get(route('dashboard'))->viewData('page')['props']['setup'])->pluck('done', 'key')->all();
}

// ---- Roles over every Phase 2 route -----------------------------------

dataset('phase2Routes', [
    'mi-salon' => ['get', 'salon.edit'],
    'datos' => ['put', 'salon.profile.update'],
    'ubicacion' => ['put', 'salon.location.update'],
    'color' => ['put', 'salon.color.update'],
    'logo' => ['post', 'salon.logo.store'],
    'portada' => ['post', 'salon.cover.store'],
    'foto' => ['post', 'salon.photos.store'],
    'orden' => ['put', 'salon.photos.reorder'],
    'servicios' => ['get', 'services.index'],
    'servicio nuevo' => ['post', 'services.store'],
    'catalogo' => ['post', 'services.catalog.store'],
]);

it('sends visitors to login on every Phase 2 owner route', function (string $method, string $route) {
    $this->{$method}(route($route))->assertRedirect(route('login'));
})->with('phase2Routes');

it('sends a client without salon to create one (GET) or forbids (writes)', function (string $method, string $route) {
    $client = User::factory()->create();
    $response = $this->actingAs($client)->{$method}(route($route));
    $method === 'get' ? $response->assertRedirect(route('salons.create')) : $response->assertForbidden();
})->with('phase2Routes');

it('forbids plain staff on every Phase 2 owner route', function (string $method, string $route) {
    $staff = qaStaff($this->salon);
    $payload = [
        'salon.profile.update' => ['name' => 'X Salón', 'business_type' => 'unisex', 'clientele' => 'everyone'],
        'salon.logo.store' => ['image' => UploadedFile::fake()->image('a.png')],
        'salon.cover.store' => ['image' => UploadedFile::fake()->image('a.png')],
        'salon.photos.store' => ['image' => UploadedFile::fake()->image('a.png')],
        'salon.photos.reorder' => ['ids' => [1]],
        'services.store' => qaService(),
        'services.catalog.store' => ['template_ids' => [ServiceTemplate::firstOrFail()->id]],
        'salon.color.update' => ['brand_color' => '#112233'],
        'salon.location.update' => ['latitude' => 1, 'longitude' => 1],
    ][$route] ?? [];
    $this->actingAs($staff)->{$method}(route($route), $payload)->assertForbidden();
})->with('phase2Routes');

it('separates services permission from profile permission', function () {
    $services = qaStaff($this->salon, [SalonPermission::ManageServices]);
    $profile = qaStaff($this->salon, [SalonPermission::ManageSalonProfile]);

    $this->actingAs($services)->get(route('services.index'))->assertOk();
    $this->actingAs($services)->get(route('salon.edit'))->assertForbidden();
    $this->actingAs($services)->post(route('services.store'), qaService())->assertRedirect();
    $this->actingAs($services)->post(route('salon.photos.store'), ['image' => UploadedFile::fake()->image('a.jpg')])->assertForbidden();

    $this->actingAs($profile)->get(route('salon.edit'))->assertOk();
    $this->actingAs($profile)->get(route('services.index'))->assertForbidden();
    $this->actingAs($profile)->post(route('services.store'), qaService())->assertForbidden();
    $this->actingAs($profile)->put(route('salon.color.update'), ['brand_color' => '#112233'])->assertRedirect();
});

it('keeps the owner of another salon out of this salon', function () {
    $otherOwner = User::factory()->create();
    Salon::factory()->ownedBy($otherOwner)->create();
    $mine = Service::factory()->for($this->salon)->create(['name' => 'Mío']);
    $photo = (new SalonPhoto)->forceFill(['salon_id' => $this->salon->id, 'path' => 'p.webp', 'position' => 0]);
    $photo->save();

    $this->actingAs($otherOwner);
    $this->put(route('services.update', $mine), qaService(['name' => 'Hackeado']))->assertNotFound();
    $this->patch(route('services.toggle', $mine), ['is_active' => false])->assertNotFound();
    $this->delete(route('services.destroy', $mine))->assertNotFound();
    $this->delete(route('salon.photos.destroy', $photo))->assertNotFound();

    expect($mine->fresh()?->name)->toBe('Mío')->and(SalonPhoto::withoutGlobalScopes()->find($photo->id))->not->toBeNull();
    $this->get(route('services.index'))->assertInertia(fn (Assert $p) => $p->where('services', fn ($s) => collect($s)->doesntContain('name', 'Mío')));
});

// ---- Prices ------------------------------------------------------------

it('stores prices typed in different ways', function (string $input, int $cents) {
    $this->actingAs($this->owner)->post(route('services.store'), qaService(['price' => $input]))->assertSessionHasNoErrors();
    expect(Service::withoutGlobalScope('salon')->where('salon_id', $this->salon->id)->latest('id')->value('price_cents'))->toBe($cents);
})->with([
    '35' => ['35', 3500], '35.5' => ['35.5', 3550], '35,50' => ['35,50', 3550], '0' => ['0', 0],
    'max' => ['99999.99', 9999999], '0,05' => ['0,05', 5],
]);

it('rejects absurd or malformed prices without a 500', function (string $input) {
    $this->actingAs($this->owner)->post(route('services.store'), qaService(['price' => $input]))->assertSessionHasErrors('price');
    expect(qaCount($this->salon))->toBe(0);
})->with(['999999999999', '35.555', '1e3', '-0', '35 soles', 'S/35', '１２']);

it('rejects an array as price instead of crashing', function () {
    $this->actingAs($this->owner)->post(route('services.store'), qaService(['price' => ['1']]))->assertSessionHasErrors('price');
});

it('shows a zero price service publicly as 0.00 without breaking', function () {
    $this->actingAs($this->owner)->post(route('services.store'), qaService(['name' => 'Cortesía', 'price' => '0']));
    $this->get('/s/qa-salon')->assertOk()->assertInertia(fn (Assert $p) => $p->where('salon.serviceGroups.0.services.0.priceFrom', '0.00'));
});

// ---- Names -------------------------------------------------------------

it('keeps tildes, ñ and emojis in service names end to end', function () {
    $name = 'Peinado ñandú ✨💇‍♀️ canción';
    $this->actingAs($this->owner)->post(route('services.store'), qaService(['name' => $name, 'description' => 'Reflejos & más: ¡ñoño!']))->assertSessionHasNoErrors();

    expect(Service::withoutGlobalScope('salon')->where('salon_id', $this->salon->id)->value('name'))->toBe($name);
    $this->get('/s/qa-salon')->assertInertia(fn (Assert $p) => $p->where('salon.serviceGroups.0.services.0.name', $name));
});

it('enforces the name bounds of a service', function (string $name, bool $valid) {
    $r = $this->actingAs($this->owner)->post(route('services.store'), qaService(['name' => $name]));
    $valid ? $r->assertSessionHasNoErrors() : $r->assertSessionHasErrors('name');
})->with([
    'one char' => ['A', false], 'blank' => ['    ', false], '120' => [str_repeat('ñ', 120), true], '121' => [str_repeat('ñ', 121), false],
    'two emojis' => ['💈💈', true], 'braces' => ['Corte {{x}}', false],
]);

it('rejects invalid time segments and variants', function (array $o, string $field) {
    $this->actingAs($this->owner)->post(route('services.store'), qaService($o))->assertSessionHasErrors($field);
})->with([
    'idle 241' => [['idle_minutes' => 241], 'idle_minutes'],
    'finish negative' => [['finish_minutes' => -1], 'finish_minutes'],
    'active 481' => [['active_minutes' => 481], 'active_minutes'],
    'active 4' => [['active_minutes' => 4], 'active_minutes'],
    'invalid category' => [['category' => 'nope'], 'category'],
    'variant price 35.555' => [['variants' => [['label' => 'X', 'price' => '35.555']]], 'variants.0.price'],
    'variant no label' => [['variants' => [['label' => '', 'price' => '10']]], 'variants.0.label'],
]);

it('sums the three time segments and never hijacks a variant of another service', function () {
    $other = Service::factory()->for($this->salon)->create();
    $this->actingAs($this->owner)->post(route('services.store'), qaService(['active_minutes' => 40, 'idle_minutes' => 30, 'finish_minutes' => 10]));
    $svc = Service::withoutGlobalScope('salon')->where('salon_id', $this->salon->id)->latest('id')->firstOrFail();
    expect($svc->totalMinutes())->toBe(80);

    $otherVariant = $other->variants()->create(['label' => 'Ajeno', 'price_cents' => 100, 'extra_minutes' => 0, 'position' => 0]);
    $this->actingAs($this->owner)->put(route('services.update', $svc), qaService(['variants' => [['id' => $otherVariant->id, 'label' => 'Robado', 'price' => '5']]]));
    expect($otherVariant->fresh()?->label)->toBe('Ajeno')->and($otherVariant->fresh()?->service_id)->toBe($other->id);
});

// ---- Public page -------------------------------------------------------

it('hides paused and add-on services on the public page and shows them again when resumed', function () {
    Service::factory()->for($this->salon)->create(['name' => 'Visible']);
    $paused = Service::factory()->for($this->salon)->create(['name' => 'Pausado']);
    Service::factory()->for($this->salon)->create(['name' => 'Adicional', 'is_addon' => true]);

    $this->actingAs($this->owner)->patch(route('services.toggle', $paused), ['is_active' => '0']);
    $this->get('/s/qa-salon')->assertInertia(fn (Assert $p) => $p->where('salon.serviceGroups', fn ($g) => collect($g)->flatMap(fn ($x) => $x['services'])->pluck('name')->all() === ['Visible']));

    $this->actingAs($this->owner)->patch(route('services.toggle', $paused), ['is_active' => '1']);
    $this->get('/s/qa-salon')->assertInertia(fn (Assert $p) => $p->where('salon.serviceGroups', fn ($g) => collect($g)->flatMap(fn ($x) => $x['services'])->pluck('name')->sort()->values()->all() === ['Pausado', 'Visible']));
});

it('shows the public page for expired trial and past due; 404 only for suspended', function (array $state, int $status) {
    $this->salon->forceFill($state)->save();
    $this->get('/s/qa-salon')->assertStatus($status);
})->with([
    'trial expired' => [['status' => SalonStatus::Trial, 'trial_ends_at' => now()->subDays(3)], 200],
    'past due' => [['status' => SalonStatus::PastDue], 200],
    'suspended' => [['status' => SalonStatus::Suspended], 404],
]);

it('returns 404 for an unknown slug', function () {
    $this->get('/s/no-existe')->assertNotFound();
    $this->get('/s/qa-salon/extra')->assertNotFound();
});

it('escapes quotes and markup of the salon in the server-rendered meta tags', function () {
    $this->salon->forceFill(['name' => 'Ñandú "Comillas" & Co', 'description' => 'Desc "q" & ok'])->save();
    $html = $this->get('/s/qa-salon')->assertOk()->getContent();
    expect($html)->not->toContain('content="Ñandú "Comillas"')->and($html)->toContain('og:title');
});

it('does not expose a location when only latitude is stored, and rejects a lone coordinate', function () {
    $this->salon->forceFill(['latitude' => -12.1, 'longitude' => null])->save();
    $this->get('/s/qa-salon')->assertOk()->assertInertia(fn (Assert $p) => $p->where('salon.location', null));
    $this->actingAs($this->owner)->put(route('salon.location.update'), ['latitude' => -12.1, 'longitude' => ''])->assertSessionHasErrors('longitude');
    $this->actingAs($this->owner)->put(route('salon.location.update'), ['latitude' => '', 'longitude' => -77])->assertSessionHasErrors('latitude');
});

it('validates coordinate ranges and clears the location with empty values', function () {
    $this->actingAs($this->owner)->put(route('salon.location.update'), ['latitude' => 91, 'longitude' => 0])->assertSessionHasErrors('latitude');
    $this->actingAs($this->owner)->put(route('salon.location.update'), ['latitude' => 0, 'longitude' => 181])->assertSessionHasErrors('longitude');
    $this->actingAs($this->owner)->put(route('salon.location.update'), ['latitude' => 'abc', 'longitude' => 'x'])->assertSessionHasErrors('latitude');
    $this->actingAs($this->owner)->put(route('salon.location.update'), ['latitude' => -12.1, 'longitude' => -77.0]);
    expect($this->salon->fresh()?->hasLocation())->toBeTrue();
    $this->actingAs($this->owner)->put(route('salon.location.update'), ['latitude' => null, 'longitude' => null])->assertSessionHasNoErrors();
    expect($this->salon->fresh()?->hasLocation())->toBeFalse();
});

// ---- Brand color -------------------------------------------------------

it('rejects invalid brand colors', function (string $color) {
    $this->actingAs($this->owner)->put(route('salon.color.update'), ['brand_color' => $color])->assertSessionHasErrors('brand_color');
    expect($this->salon->fresh()?->brand_color)->toBeNull();
})->with(['rojo', '#12345', '#GGGGGG', '#1234567', 'rgb(1,2,3)', '#fff;}body{']);

it('accepts short hex without hash and clears with null', function () {
    $this->actingAs($this->owner)->put(route('salon.color.update'), ['brand_color' => 'abc'])->assertSessionHasNoErrors();
    expect($this->salon->fresh()?->brand_color)->not->toBeNull();
    $this->actingAs($this->owner)->put(route('salon.color.update'), ['brand_color' => null])->assertSessionHasNoErrors();
    expect($this->salon->fresh()?->brand_color)->toBeNull();
});

// ---- Images / photos ---------------------------------------------------

it('rejects svg, php disguised as image, pdf and oversize uploads', function (string $kind) {
    $file = match ($kind) {
        'svg' => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        'php' => UploadedFile::fake()->createWithContent('x.jpg', '<?php echo 1;'),
        'pdf' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        'huge' => UploadedFile::fake()->create('big.jpg', 60000, 'image/jpeg'),
    };
    $this->actingAs($this->owner)->post(route('salon.logo.store'), ['image' => $file])->assertSessionHasErrors('image');
    expect($this->salon->fresh()?->logo_path)->toBeNull();
})->with(['svg', 'php', 'pdf', 'huge']);

it('requires an image', function () {
    $this->actingAs($this->owner)->post(route('salon.logo.store'), [])->assertSessionHasErrors('image');
    $this->actingAs($this->owner)->post(route('salon.photos.store'), ['image' => 'texto'])->assertSessionHasErrors('image');
});

it('fits gallery photos in 1600 without cropping and saves them as webp', function () {
    $this->actingAs($this->owner)->post(route('salon.photos.store'), ['image' => UploadedFile::fake()->image('w.jpg', 4000, 1000), 'caption' => 'Corte ñandú ✨']);
    $photo = SalonPhoto::withoutGlobalScopes()->where('salon_id', $this->salon->id)->firstOrFail();
    [$w, $h, $type] = getimagesizefromstring(Storage::disk('public')->get($photo->path));
    expect([$w, $h, $type])->toBe([1600, 400, IMAGETYPE_WEBP])->and($photo->caption)->toBe('Corte ñandú ✨');
});

it('keeps 30 photos at most and frees room after deleting one', function () {
    foreach (range(1, 30) as $i) {
        (new SalonPhoto)->forceFill(['salon_id' => $this->salon->id, 'path' => "x{$i}.webp", 'position' => $i])->save();
    }
    $r = $this->actingAs($this->owner)->post(route('salon.photos.store'), ['image' => UploadedFile::fake()->image('extra.jpg')]);
    expect($r->status())->not->toBe(500);
    expect(SalonPhoto::withoutGlobalScopes()->where('salon_id', $this->salon->id)->count())->toBe(30);

    $first = SalonPhoto::withoutGlobalScopes()->where('salon_id', $this->salon->id)->first();
    $this->actingAs($this->owner)->delete(route('salon.photos.destroy', $first));
    $this->actingAs($this->owner)->post(route('salon.photos.store'), ['image' => UploadedFile::fake()->image('ok.jpg')]);
    expect(SalonPhoto::withoutGlobalScopes()->where('salon_id', $this->salon->id)->count())->toBe(30);
});

it('deletes the stored file when a photo is deleted', function () {
    $this->actingAs($this->owner)->post(route('salon.photos.store'), ['image' => UploadedFile::fake()->image('a.jpg')]);
    $photo = SalonPhoto::withoutGlobalScopes()->firstOrFail();
    Storage::disk('public')->assertExists($photo->path);
    $this->actingAs($this->owner)->delete(route('salon.photos.destroy', $photo));
    Storage::disk('public')->assertMissing($photo->path);
});

it('renders the public gallery in the saved order', function () {
    foreach (['a', 'b', 'c'] as $n) {
        $this->actingAs($this->owner)->post(route('salon.photos.store'), ['image' => UploadedFile::fake()->image("$n.jpg"), 'caption' => $n]);
    }
    $ids = SalonPhoto::withoutGlobalScopes()->orderBy('position')->pluck('id')->all();
    $this->actingAs($this->owner)->put(route('salon.photos.reorder'), ['ids' => array_reverse($ids)])->assertSessionHasNoErrors();
    $this->get('/s/qa-salon')->assertInertia(fn (Assert $p) => $p->where('salon.photos', fn ($ph) => collect($ph)->pluck('caption')->all() === ['c', 'b', 'a']));
});

it('validates reorder payloads', function (array $payload) {
    $this->actingAs($this->owner)->put(route('salon.photos.reorder'), $payload)->assertSessionHasErrors();
})->with([[[]], [['ids' => 'x']], [['ids' => ['a']]], [['ids' => range(1, 31)]]]);

it('removes the logo file', function () {
    $this->actingAs($this->owner)->post(route('salon.logo.store'), ['image' => UploadedFile::fake()->image('l.png', 600, 600)]);
    $path = $this->salon->fresh()->logo_path;
    $this->actingAs($this->owner)->delete(route('salon.logo.destroy'));
    expect($this->salon->fresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

// ---- Read-only salons --------------------------------------------------

it('blocks every Phase 2 write on a suspended or expired salon but keeps reading', function (array $state) {
    $this->salon->forceFill($state)->save();
    $svc = Service::factory()->for($this->salon)->create(['name' => 'Original']);
    $photo = (new SalonPhoto)->forceFill(['salon_id' => $this->salon->id, 'path' => 'p.webp', 'position' => 0]);
    $photo->save();
    $template = ServiceTemplate::firstOrFail();
    $before = qaCount($this->salon);

    $this->actingAs($this->owner);
    $this->get(route('salon.edit'))->assertOk();
    $this->get(route('services.index'))->assertOk();

    $responses = [
        $this->put(route('salon.profile.update'), ['name' => 'Cambiado', 'business_type' => 'unisex', 'clientele' => 'everyone']),
        $this->put(route('salon.location.update'), ['latitude' => 1, 'longitude' => 1]),
        $this->put(route('salon.color.update'), ['brand_color' => '#112233']),
        $this->post(route('salon.logo.store'), ['image' => UploadedFile::fake()->image('l.png')]),
        $this->post(route('salon.cover.store'), ['image' => UploadedFile::fake()->image('c.png')]),
        $this->post(route('salon.photos.store'), ['image' => UploadedFile::fake()->image('p.png')]),
        $this->put(route('salon.photos.reorder'), ['ids' => [$photo->id]]),
        $this->delete(route('salon.photos.destroy', $photo)),
        $this->post(route('services.store'), qaService()),
        $this->post(route('services.catalog.store'), ['template_ids' => [$template->id]]),
        $this->put(route('services.update', $svc), qaService(['name' => 'Cambiado'])),
        $this->patch(route('services.toggle', $svc), ['is_active' => '0']),
        $this->delete(route('services.destroy', $svc)),
    ];

    foreach ($responses as $i => $response) {
        expect($response->status())->toBe(303, "write #$i");
    }

    expect($this->salon->fresh()->name)->not->toBe('Cambiado')
        ->and($svc->fresh()?->name)->toBe('Original')->and($svc->fresh()->is_active)->toBeTrue()
        ->and(qaCount($this->salon))->toBe($before)
        ->and(SalonPhoto::withoutGlobalScopes()->find($photo->id))->not->toBeNull();
})->with([
    'suspended' => [['status' => SalonStatus::Suspended]],
    'trial expired' => [['status' => SalonStatus::Trial, 'trial_ends_at' => now()->subDays(10)]], // past its 7 days of grace
]);

// ---- Checklist ---------------------------------------------------------

it('completes the checklist step by step and un-completes on delete', function () {
    expect(array_filter(qaSetup($this)))->toBe([]);

    $this->actingAs($this->owner)->post(route('salon.logo.store'), ['image' => UploadedFile::fake()->image('l.png', 600, 600)]);
    expect(qaSetup($this)['logo'])->toBeTrue()->and(qaSetup($this)['cover'])->toBeFalse();

    $this->actingAs($this->owner)->post(route('salon.cover.store'), ['image' => UploadedFile::fake()->image('c.png', 1700, 900)]);
    $this->actingAs($this->owner)->put(route('salon.profile.update'), ['name' => 'Salón QA', 'business_type' => 'unisex', 'clientele' => 'everyone', 'description' => 'Hola']);
    $this->actingAs($this->owner)->put(route('salon.location.update'), ['latitude' => -12.1, 'longitude' => -77.0]);
    foreach (['a', 'b', 'c'] as $n) {
        $this->actingAs($this->owner)->post(route('salon.photos.store'), ['image' => UploadedFile::fake()->image("$n.jpg")]);
    }
    expect(qaSetup($this))->toMatchArray(['logo' => true, 'cover' => true, 'description' => true, 'location' => true, 'photos' => true, 'services' => false]);

    $this->actingAs($this->owner)->delete(route('salon.photos.destroy', SalonPhoto::withoutGlobalScopes()->first()));
    $this->actingAs($this->owner)->delete(route('salon.logo.destroy'));
    expect(qaSetup($this)['photos'])->toBeFalse()->and(qaSetup($this)['logo'])->toBeFalse();
});

it('counts the services step only after the owner edited an active service', function () {
    $svc = Service::factory()->for($this->salon)->create();
    expect(qaSetup($this)['services'])->toBeFalse();
    Carbon::setTestNow(now()->addMinute());
    $this->actingAs($this->owner)->put(route('services.update', $svc), qaService(['name' => 'Revisado', 'price' => '40']));
    expect(qaSetup($this)['services'])->toBeTrue();
    Carbon::setTestNow();
});

// ---- Catalog -----------------------------------------------------------

it('rejects bad catalog payloads', function (array $payload) {
    $this->actingAs($this->owner)->post(route('services.catalog.store'), $payload)->assertSessionHasErrors();
})->with([[[]], [['template_ids' => []]], [['template_ids' => [999999]]], [['template_ids' => 'x']], [['template_ids' => array_fill(0, 121, 1)]]]);

it('adds the whole catalog of 116 once without duplicating on a second submit', function () {
    $ids = ServiceTemplate::pluck('id')->all();
    expect($ids)->toHaveCount(116);
    $this->actingAs($this->owner)->post(route('services.catalog.store'), ['template_ids' => $ids])->assertSessionHasNoErrors();
    expect(qaCount($this->salon))->toBe(116);
    $this->actingAs($this->owner)->post(route('services.catalog.store'), ['template_ids' => $ids]);
    expect(qaCount($this->salon))->toBe(116);
    $this->actingAs($this->owner)->get(route('services.index'))->assertInertia(fn (Assert $p) => $p->has('services', 116)->has('catalog', 0));
});

it('seeds starter services only for the new salon', function () {
    $u = User::factory()->create();
    $this->actingAs($u)->post(route('salons.store'), ['name' => 'Spa Ñu', 'business_type' => 'spa', 'clientele' => 'everyone', 'salon_terms' => '1']);
    $new = Salon::where('name', 'Spa Ñu')->firstOrFail();
    expect(qaCount($new))->toBeGreaterThan(0)->and(qaCount($this->salon))->toBe(0);
});
