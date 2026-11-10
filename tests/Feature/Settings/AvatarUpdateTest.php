<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('user without an uploaded photo gets an animated sprouts avatar seeded by id', function () {
    $user = User::factory()->create();

    expect($user->avatar)->toBe(
        "https://api.dicebear.com/10.x/sprouts/svg?seed={$user->id}&tags=animation"
    );
});

test('user can upload an avatar', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('avatar.update'), [
            'avatar' => UploadedFile::fake()->image('me.jpg'),
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->avatar_path)->toStartWith('avatars/');
    Storage::disk('public')->assertExists($user->avatar_path);
    expect($user->avatar)->toBe(Storage::disk('public')->url($user->avatar_path));
});

test('uploading a new avatar deletes the previous file', function () {
    $user = User::factory()->create();
    $previousPath = UploadedFile::fake()->image('old.jpg')->store('avatars', 'public');
    $user->forceFill(['avatar_path' => $previousPath])->save();

    $this
        ->actingAs($user)
        ->post(route('avatar.update'), [
            'avatar' => UploadedFile::fake()->image('new.png'),
        ])
        ->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing($previousPath);
    Storage::disk('public')->assertExists($user->refresh()->avatar_path);
});

test('avatar must be an image', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->post(route('avatar.update'), [
            'avatar' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors(['avatar' => 'The avatar field must be an image.'])
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->avatar_path)->toBeNull();
});

test('avatar may not be larger than 2 MB', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->post(route('avatar.update'), [
            'avatar' => UploadedFile::fake()->image('huge.jpg')->size(2049),
        ])
        ->assertSessionHasErrors(['avatar' => 'The avatar field must not be greater than 2048 kilobytes.']);

    expect($user->refresh()->avatar_path)->toBeNull();
});

test('user can remove their avatar', function () {
    $user = User::factory()->create();
    $path = UploadedFile::fake()->image('me.jpg')->store('avatars', 'public');
    $user->forceFill(['avatar_path' => $path])->save();

    $this
        ->actingAs($user)
        ->delete(route('avatar.destroy'))
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    Storage::disk('public')->assertMissing($path);
    expect($user->avatar_path)->toBeNull();
    expect($user->avatar)->toContain('api.dicebear.com/10.x/sprouts/svg');
});

test('deleting the account deletes the avatar file', function () {
    $user = User::factory()->create();
    $path = UploadedFile::fake()->image('me.jpg')->store('avatars', 'public');
    $user->forceFill(['avatar_path' => $path])->save();

    $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing($path);
});

test('guests cannot upload an avatar', function () {
    $this
        ->post(route('avatar.update'), [
            'avatar' => UploadedFile::fake()->image('me.jpg'),
        ])
        ->assertRedirect(route('login'));

    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});
