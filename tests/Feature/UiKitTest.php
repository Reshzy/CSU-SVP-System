<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('ui-kit'));

    $response->assertRedirect(route('login'));
});

test('renders the ui kit page for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('ui-kit'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('ui-kit'),
    );
});
