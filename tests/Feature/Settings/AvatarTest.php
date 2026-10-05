<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('stores the photo as a square 512px WebP with a random name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('avatar.store'), ['avatar' => UploadedFile::fake()->image('mi-foto.jpg', 1600, 900)])
        ->assertRedirect(route('profile.edit'));

    $path = $user->fresh()?->avatar_path;
    expect($path)->toStartWith("avatars/{$user->id}/")
        ->and($path)->toEndWith('.webp')
        ->and($path)->not->toContain('mi-foto');
    Storage::disk('public')->assertExists($path);

    [$width, $height, $type] = getimagesizefromstring(Storage::disk('public')->get($path));
    expect([$width, $height, $type])->toBe([512, 512, IMAGETYPE_WEBP]);
});

it('deletes the previous photo when uploading a new one', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('avatar.store'), ['avatar' => UploadedFile::fake()->image('a.png', 600, 600)]);
    $first = $user->fresh()?->avatar_path;
    $this->actingAs($user)->post(route('avatar.store'), ['avatar' => UploadedFile::fake()->image('b.png', 600, 600)]);

    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($user->fresh()?->avatar_path);
});

it('rejects files that are not safe raster images', function (UploadedFile $file) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('avatar.store'), ['avatar' => $file])
        ->assertSessionHasErrors('avatar');

    expect($user->fresh()?->avatar_path)->toBeNull();
})->with([
    'svg (can carry scripts)' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
    'pdf renamed as jpg' => fn () => UploadedFile::fake()->create('foto.jpg', 100, 'application/pdf'),
    'too heavy' => fn () => UploadedFile::fake()->image('enorme.jpg')->size(6000),
]);

it('removes the photo', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('avatar.store'), ['avatar' => UploadedFile::fake()->image('a.jpg')]);
    $path = $user->fresh()?->avatar_path;

    $this->actingAs($user)->delete(route('avatar.destroy'))->assertRedirect(route('profile.edit'));

    expect($user->fresh()?->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('exposes the public avatar URL to the frontend', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('avatar.store'), ['avatar' => UploadedFile::fake()->image('a.jpg')]);

    $this->actingAs($user->fresh())
        ->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page->where('auth.user.avatar', fn (?string $url) => str_contains((string) $url, '/storage/avatars/')));
});
