<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

/**
 * POST tests that switch APP_ENV to local must disable CSRF: Laravel only
 * skips it while the environment is `testing`.
 */

test('the login screen does not offer quick login outside local', function () {
    User::factory()->create();

    $this->get(route('login'))
        ->assertInertia(fn ($page) => $page
            ->component('auth/login')
            ->where('canQuickLogin', false)
            ->where('quickLoginUsers', [])
        );
});

test('the login screen lists approved users for quick login in local', function () {
    $this->app['env'] = 'local';

    $approved = User::factory()->create(['name' => 'Approved User']);
    User::factory()->pending()->create();

    $this->get(route('login'))
        ->assertInertia(fn ($page) => $page
            ->component('auth/login')
            ->where('canQuickLogin', true)
            ->has('quickLoginUsers', 1)
            ->where('quickLoginUsers.0.id', $approved->id)
            ->where('quickLoginUsers.0.email', $approved->email)
            ->where('quickLoginUsers.0.role', 'End User')
        );
});

test('quick login authenticates the selected user in local', function () {
    $this->app['env'] = 'local';
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->create();

    $this->post(route('dev.login', $user))
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('quick login returns 404 and stays guest outside local', function () {
    $user = User::factory()->create();

    $this->post(route('dev.login', $user))
        ->assertNotFound();

    $this->assertGuest();
});

test('quick login returns 404 for an unapproved user in local', function () {
    $this->app['env'] = 'local';
    $this->withoutMiddleware(PreventRequestForgery::class);

    $user = User::factory()->pending()->create();

    $this->post(route('dev.login', $user))
        ->assertNotFound();

    $this->assertGuest();
});
