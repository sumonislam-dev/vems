<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;

it('refuses an inactive or suspended account with the right password', function (string $status) {
    User::factory()->create(['username' => 'switched.off', 'password' => bcrypt('password'), 'status' => $status]);

    $this->post('/login', ['login' => 'switched.off', 'password' => 'password'])
        ->assertSessionHasErrors(['login' => EnsureUserIsActive::MESSAGE]);

    $this->assertGuest();
})->with(['inactive', 'suspended']);

it('gives an inactive account with the wrong password the usual message', function () {
    User::factory()->create(['username' => 'switched.off', 'password' => bcrypt('password'), 'status' => 'inactive']);

    $this->post('/login', ['login' => 'switched.off', 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['login' => 'These credentials do not match our records.']);

    $this->assertGuest();
});

it('still logs in an active account', function () {
    User::factory()->create(['username' => 'switched.on', 'password' => bcrypt('password'), 'status' => 'active']);

    $this->post('/login', ['login' => 'switched.on', 'password' => 'password'])->assertRedirect('/dashboard');

    $this->assertAuthenticated();
});

it('signs out a logged-in user once their account is deactivated', function () {
    $user = User::factory()->create(['status' => 'active']);

    $this->actingAs($user)->get('/dashboard')->assertOk();

    $user->update(['status' => 'inactive']);

    $this->actingAs($user)->get('/dashboard')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['login' => EnsureUserIsActive::MESSAGE]);

    $this->assertGuest();
});

it('shows the too-many-attempts message on the login field', function () {
    User::factory()->create(['username' => 'someone', 'password' => bcrypt('password')]);

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['login' => 'someone', 'password' => 'wrong-password']);
    }

    $this->post('/login', ['login' => 'someone', 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['login' => fn ($message) => str_contains($message, 'Too many login attempts')]);
});
