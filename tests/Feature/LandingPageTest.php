<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the landing page loads the CagSU SVP Portal', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('welcome'));
});

test('supplier public pages stay unavailable', function () {
    $this->get('/suppliers/register')->assertNotFound();
    $this->post('/suppliers/register')->assertNotFound();
    $this->get('/suppliers/quotations/submit')->assertNotFound();
    $this->post('/suppliers/quotations/submit')->assertNotFound();
    $this->get('/suppliers/po-status')->assertNotFound();
    $this->get('/suppliers/contact')->assertNotFound();
    $this->post('/suppliers/contact')->assertNotFound();
});
