<?php

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

function seedVehicleAssignableDriversPermissions(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'create-vehicles', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'edit-vehicles', 'guard_name' => 'web']);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeAssignableDriver(string $username, array $overrides = []): User
{
    return User::create(array_merge([
        'email_verified_at' => now(),
        'name' => 'Driver '.$username,
        'username' => $username,
        'email' => $username.'@example.com',
        'user_type' => 'driver',
        'status' => 'active',
        'driver_status' => 'available',
        'password' => Hash::make('password'),
    ], $overrides));
}

it('excludes drivers with an expired license or unavailable status from the create form', function () {
    seedVehicleAssignableDriversPermissions();
    $manager = makeAssignableDriver('manager-1', ['user_type' => 'employee']);
    $manager->givePermissionTo('create-vehicles');

    $eligible = makeAssignableDriver('eligible-1', ['license_expiry_date' => now()->addYear()]);
    $expired = makeAssignableDriver('expired-1', ['license_expiry_date' => now()->subDay()]);
    $onTrip = makeAssignableDriver('on-trip-1', ['driver_status' => 'on_trip']);
    $suspended = makeAssignableDriver('suspended-1', ['status' => 'suspended']);

    $response = $this->actingAs($manager)->get('/vehicles/create');
    $response->assertOk();

    $response->assertInertia(function ($page) use ($eligible, $expired, $onTrip, $suspended) {
        $ids = collect($page->toArray()['props']['drivers'])->pluck('id');

        expect($ids)->toContain($eligible->id)
            ->and($ids)->not->toContain($expired->id)
            ->and($ids)->not->toContain($onTrip->id)
            ->and($ids)->not->toContain($suspended->id);

        return $page->component('vehicles/create');
    });
});

it('keeps the vehicle\'s currently-assigned driver in the edit form even if no longer eligible', function () {
    seedVehicleAssignableDriversPermissions();
    $manager = makeAssignableDriver('manager-2', ['user_type' => 'employee']);
    $manager->givePermissionTo('edit-vehicles');

    $noLongerEligible = makeAssignableDriver('was-eligible-1', ['license_expiry_date' => now()->subDay()]);

    $vehicle = Vehicle::create([
        'brand' => 'Toyota',
        'model' => 'Hiace',
        'registration_number' => 'VEH-'.uniqid(),
        'driver_id' => $noLongerEligible->id,
        'is_active' => true,
    ]);

    $response = $this->actingAs($manager)->get("/vehicles/{$vehicle->id}/edit");
    $response->assertOk();

    $response->assertInertia(function ($page) use ($noLongerEligible) {
        $ids = collect($page->toArray()['props']['drivers'])->pluck('id');

        expect($ids)->toContain($noLongerEligible->id);

        return $page->component('vehicles/edit');
    });
});
