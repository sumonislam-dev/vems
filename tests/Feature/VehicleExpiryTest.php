<?php

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

function seedVehicleExpiryPermissions(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'view-vehicles', 'guard_name' => 'web']);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeVehicleExpiryViewer(): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Vehicle Viewer',
        'username' => 'vehicle_viewer_'.uniqid(),
        'email' => 'vehicle_viewer_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $user->givePermissionTo('view-vehicles');

    return $user;
}

it('does not flag a tax token expiring 60 days from now when the alert window is 30 days', function () {
    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Camry',
        'registration_number' => 'VEH-'.uniqid(),
        'tax_token_last_date' => now()->addDays(60),
        'tax_token_alert_enabled' => true,
        'alert_days_before' => 30,
    ]);

    expect($vehicle->isTaxTokenExpiring())->toBeFalse();
});

it('flags a tax token expiring 10 days from now when the alert window is 30 days', function () {
    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Camry',
        'registration_number' => 'VEH-'.uniqid(),
        'tax_token_last_date' => now()->addDays(10),
        'tax_token_alert_enabled' => true,
        'alert_days_before' => 30,
    ]);

    expect($vehicle->isTaxTokenExpiring())->toBeTrue();
});

it('flags a tax token that already expired', function () {
    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Camry',
        'registration_number' => 'VEH-'.uniqid(),
        'tax_token_last_date' => now()->subDays(5),
        'tax_token_alert_enabled' => true,
        'alert_days_before' => 30,
    ]);

    expect($vehicle->isTaxTokenExpiring())->toBeTrue();
});

it('does not flag any document when alerts are disabled', function () {
    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Camry',
        'registration_number' => 'VEH-'.uniqid(),
        'tax_token_last_date' => now()->subDays(5),
        'tax_token_alert_enabled' => false,
        'fitness_certificate_last_date' => now()->subDays(5),
        'fitness_alert_enabled' => false,
        'insurance_last_date' => now()->subDays(5),
        'insurance_alert_enabled' => false,
    ]);

    expect($vehicle->getExpiringDocuments())->toBe([]);
});

it('reports a positive days_left for a document expiring soon and a negative days_left once expired', function () {
    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Camry',
        'registration_number' => 'VEH-'.uniqid(),
        'tax_token_last_date' => now()->addDays(10),
        'tax_token_alert_enabled' => true,
        'alert_days_before' => 30,
    ]);

    $documents = $vehicle->getExpiringDocuments();

    expect($documents)->toHaveCount(1)
        ->and($documents[0]['days_left'])->toBeGreaterThan(0);

    $vehicle->update(['tax_token_last_date' => now()->subDays(5)]);
    $vehicle->refresh();

    $documents = $vehicle->getExpiringDocuments();

    expect($documents[0]['days_left'])->toBeLessThan(0);
});

it('includes expiring_documents in the vehicle index payload', function () {
    seedVehicleExpiryPermissions();
    $viewer = makeVehicleExpiryViewer();

    Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Camry',
        'registration_number' => 'VEH-'.uniqid(),
        'tax_token_last_date' => now()->addDays(5),
        'tax_token_alert_enabled' => true,
        'alert_days_before' => 30,
    ]);

    $response = $this->actingAs($viewer)->get('/vehicles');
    $response->assertOk();

    $response->assertInertia(function ($page) {
        $vehicles = $page->toArray()['props']['vehicles']['data'];

        expect($vehicles[0]['expiring_documents'])->toHaveCount(1)
            ->and($vehicles[0]['expiring_documents'][0]['type'])->toBe('tax_token');

        return $page->component('vehicles/index');
    });
});
