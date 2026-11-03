<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

/**
 * GeocodingController: proxies free-text location search to Nominatim
 * server-side (see MapStopPicker.tsx), so the browser never calls
 * nominatim.openstreetmap.org directly.
 */
function geocodingTestUser(): User
{
    return User::create([
        'email_verified_at' => now(),
        'name' => 'Geocoding Test User',
        'username' => 'geocodinguser_'.uniqid(),
        'email' => 'geocodinguser_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
}

it('proxies a search query to nominatim and returns its results as-is', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            ['display_name' => 'Gulshan, Dhaka', 'lat' => '23.78', 'lon' => '90.41', 'place_id' => '123'],
        ], 200),
    ]);

    $response = $this->actingAs(geocodingTestUser())->getJson('/api/geocoding/search?q=Gulshan&countrycodes=bd');

    $response->assertOk()->assertExactJson([
        ['display_name' => 'Gulshan, Dhaka', 'lat' => '23.78', 'lon' => '90.41', 'place_id' => '123'],
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'nominatim.openstreetmap.org/search')
            && $request['q'] === 'Gulshan'
            && $request->hasHeader('User-Agent');
    });
});

it('returns an empty array instead of an error when nominatim is unreachable', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response(null, 500),
    ]);

    $this->actingAs(geocodingTestUser())
        ->getJson('/api/geocoding/search?q=Gulshan')
        ->assertOk()
        ->assertExactJson([]);
});

it('rejects a query shorter than 2 characters', function () {
    $this->actingAs(geocodingTestUser())
        ->getJson('/api/geocoding/search?q=a')
        ->assertUnprocessable();
});

it('requires authentication', function () {
    $this->getJson('/api/geocoding/search?q=Gulshan')->assertUnauthorized();
});
