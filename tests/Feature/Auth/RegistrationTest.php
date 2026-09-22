<?php

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

test('registration is disabled', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
});

test('RegisteredUserController still works correctly if the route is re-enabled', function () {
    // The route itself is disabled (see routes/auth.php); this exercises the
    // controller directly so the username-generation fix stays covered.
    Route::post('/test-only-register', [RegisteredUserController::class, 'store']);

    $response = $this->post('/test-only-register', [
        'name' => 'Test User',
        'email' => 'test.user@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'test.user@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->username)->toBe('test.user');
});
