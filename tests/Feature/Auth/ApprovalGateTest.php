<?php

use App\Models\User;

test('approved users can log in', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('pending users cannot log in', function () {
    $user = User::factory()->pending()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('rejected users cannot log in', function () {
    $user = User::factory()->rejected()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('approved but deactivated users cannot log in', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('the wrong password is still refused for an approved user', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'not-the-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

/**
 * Passkey sign-in never runs the Fortify login pipeline, and a session can
 * outlive a CEO deactivation, so the middleware has to hold the line too.
 */
test('a pending user with a session cannot reach the dashboard', function () {
    $user = User::factory()->pending()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('a pending user with a session cannot reach settings', function () {
    $user = User::factory()->pending()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('a deactivated user is signed out mid-session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $user->forceFill(['is_active' => false])->save();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});
