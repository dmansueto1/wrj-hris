<?php

use App\Models\User;

test('registration page does not exist', function () {
    $this->get('/register')->assertNotFound();
});

test('guests visiting the home page are redirected to login', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('signed-in users visiting the home page are redirected to clock', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/')->assertRedirect('/clock');
});
