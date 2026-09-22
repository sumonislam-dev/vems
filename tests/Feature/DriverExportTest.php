<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

function seedDriverExportPermissions(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'view-drivers', 'guard_name' => 'web']);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeDriverExportViewer(): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Driver Export Viewer',
        'username' => 'driver_export_'.uniqid(),
        'email' => 'driver_export_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $user->givePermissionTo('view-drivers');

    return $user;
}

function makeDriverExportDriver(array $overrides = []): User
{
    $username = 'driver_export_row_'.uniqid();

    return User::create(array_merge([
        'email_verified_at' => now(),
        'name' => 'Export Test Driver',
        'username' => $username,
        'email' => $username.'@example.com',
        'user_type' => 'driver',
        'status' => 'active',
        'driver_status' => 'available',
        'password' => Hash::make('password'),
    ], $overrides));
}

it('exports drivers as csv containing the driver rows', function () {
    seedDriverExportPermissions();
    $viewer = makeDriverExportViewer();
    makeDriverExportDriver(['name' => 'Csv Export Driver']);

    $response = $this->actingAs($viewer)->get('/drivers-export?format=csv');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->getContent())->toContain('Csv Export Driver');
});

it('exports drivers as excel without error', function () {
    seedDriverExportPermissions();
    $viewer = makeDriverExportViewer();
    makeDriverExportDriver();

    $response = $this->actingAs($viewer)->get('/drivers-export?format=excel');

    $response->assertOk();
});

it('exports drivers as pdf without error', function () {
    seedDriverExportPermissions();
    $viewer = makeDriverExportViewer();
    makeDriverExportDriver();

    $response = $this->actingAs($viewer)->get('/drivers-export?format=pdf');

    $response->assertOk();
});

it('respects the driver_status filter when exporting drivers', function () {
    seedDriverExportPermissions();
    $viewer = makeDriverExportViewer();

    makeDriverExportDriver(['name' => 'Available Driver', 'driver_status' => 'available']);
    makeDriverExportDriver(['name' => 'OnTrip Driver', 'driver_status' => 'on_trip']);

    $response = $this->actingAs($viewer)->get('/drivers-export?'.http_build_query([
        'filters' => ['driver_status' => ['on_trip']],
        'format' => 'csv',
    ]));

    $response->assertOk();
    expect($response->getContent())->toContain('OnTrip Driver')->not->toContain('Available Driver');
});

it('denies driver export to a user without view-drivers', function () {
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'No Perm',
        'username' => 'no_perm_'.uniqid(),
        'email' => 'no_perm_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $response = $this->actingAs($user)->get('/drivers-export');

    $response->assertForbidden();
});
