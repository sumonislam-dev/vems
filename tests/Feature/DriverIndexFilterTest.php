<?php

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

/**
 * UserIndexRequest (shared by DriverController::index()) previously had no
 * validation rules for filters.driver_status or filters.vendor_id, so
 * FormRequest::validated() silently stripped those keys before the
 * controller's !empty($filters[...]) checks ever saw them — both filters
 * looked wired up but never actually narrowed the query.
 */
function seedDriverIndexFilterPermissions(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'view-drivers', 'guard_name' => 'web']);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeDriverIndexFilterViewer(): User
{
    $username = 'driver-filter-viewer-'.uniqid();

    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Driver Filter Viewer',
        'username' => $username,
        'email' => $username.'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $user->givePermissionTo('view-drivers');

    return $user;
}

function makeDriverIndexFilterDriver(array $overrides = []): User
{
    $username = 'driver-filter-'.uniqid();

    return User::create(array_merge([
        'email_verified_at' => now(),
        'name' => 'Filter Test Driver',
        'username' => $username,
        'email' => $username.'@example.com',
        'user_type' => 'driver',
        'status' => 'active',
        'driver_status' => 'available',
        'password' => Hash::make('password'),
    ], $overrides));
}

it('filters the driver index by driver_status using the filters[] array format the table sends', function () {
    seedDriverIndexFilterPermissions();
    $viewer = makeDriverIndexFilterViewer();

    $available = makeDriverIndexFilterDriver(['driver_status' => 'available']);
    $onTrip = makeDriverIndexFilterDriver(['driver_status' => 'on_trip']);

    $response = $this->actingAs($viewer)->get('/drivers?'.http_build_query([
        'filters' => ['driver_status' => ['on_trip']],
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('users.data', fn ($users) => collect($users)->pluck('id')->contains($onTrip->id)
            && ! collect($users)->pluck('id')->contains($available->id)
        )
    );
});

it('filters the driver index by vendor_id using the filters[] array format the table sends', function () {
    seedDriverIndexFilterPermissions();
    $viewer = makeDriverIndexFilterViewer();

    $vendorA = Vendor::create(['name' => 'Vendor A', 'status' => 'active']);
    $vendorB = Vendor::create(['name' => 'Vendor B', 'status' => 'active']);

    $driverA = makeDriverIndexFilterDriver(['vendor_id' => $vendorA->id]);
    $driverB = makeDriverIndexFilterDriver(['vendor_id' => $vendorB->id]);

    $response = $this->actingAs($viewer)->get('/drivers?'.http_build_query([
        'filters' => ['vendor_id' => [(string) $vendorA->id]],
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('users.data', fn ($users) => collect($users)->pluck('id')->contains($driverA->id)
            && ! collect($users)->pluck('id')->contains($driverB->id)
        )
    );
});
