<?php

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

function seedVehicleExportPermissions(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'view-vehicles', 'guard_name' => 'web']);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeVehicleExportViewer(): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Vehicle Export Viewer',
        'username' => 'vehicle_export_'.uniqid(),
        'email' => 'vehicle_export_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $user->givePermissionTo('view-vehicles');

    return $user;
}

it('exports vehicles as csv containing the vehicle rows', function () {
    seedVehicleExportPermissions();
    $viewer = makeVehicleExportViewer();

    Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Camry',
        'registration_number' => 'EXP-'.uniqid(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($viewer)->get('/vehicles-export?format=csv');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->getContent())->toContain('Toyota')->toContain('Camry');
});

it('exports vehicles as excel without error', function () {
    seedVehicleExportPermissions();
    $viewer = makeVehicleExportViewer();

    Vehicle::create([
        'brand' => 'Honda', 'model' => 'Civic',
        'registration_number' => 'EXP-'.uniqid(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($viewer)->get('/vehicles-export?format=excel');

    $response->assertOk();
});

it('exports vehicles as pdf without error', function () {
    seedVehicleExportPermissions();
    $viewer = makeVehicleExportViewer();

    Vehicle::create([
        'brand' => 'Ford', 'model' => 'F-150',
        'registration_number' => 'EXP-'.uniqid(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($viewer)->get('/vehicles-export?format=pdf');

    $response->assertOk();
});

it('respects filters when exporting vehicles', function () {
    seedVehicleExportPermissions();
    $viewer = makeVehicleExportViewer();

    Vehicle::create(['brand' => 'ActiveBrand', 'model' => 'M1', 'registration_number' => 'EXP-'.uniqid(), 'is_active' => true]);
    Vehicle::create(['brand' => 'InactiveBrand', 'model' => 'M2', 'registration_number' => 'EXP-'.uniqid(), 'is_active' => false]);

    $response = $this->actingAs($viewer)->get('/vehicles-export?'.http_build_query([
        'filters' => ['is_active' => ['1']],
        'format' => 'csv',
    ]));

    $response->assertOk();
    expect($response->getContent())->toContain('ActiveBrand')->not->toContain('InactiveBrand');
});

it('denies vehicle export to a user without view-vehicles', function () {
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'No Perm',
        'username' => 'no_perm_'.uniqid(),
        'email' => 'no_perm_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $response = $this->actingAs($user)->get('/vehicles-export');

    $response->assertForbidden();
});
