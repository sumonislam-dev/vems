<?php

use App\Models\Trip;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The driver forms only offer what the server accepts (licence classes come
 * from User::LICENSE_CLASSES), save every field they show, and a refused
 * delete comes back as a flash message the toast can show.
 */
function makeDriverFormEditor(): User
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    foreach (['view-drivers', 'create-drivers', 'edit-drivers', 'delete-drivers', 'view-all-zones'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $editor = User::create([
        'email_verified_at' => now(),
        'name' => 'Driver Form Editor',
        'username' => 'driver.form.editor',
        'user_type' => 'admin',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
    $editor->givePermissionTo(['view-drivers', 'create-drivers', 'edit-drivers', 'delete-drivers']);

    return $editor;
}

function makeFormDriver(Vendor $vendor): User
{
    return User::create([
        'name' => 'Form Driver',
        'username' => 'form.driver',
        'personal_phone' => '01700000000',
        'whatsapp_id' => '01700000000',
        'user_type' => 'driver',
        'status' => 'active',
        'vendor_id' => $vendor->id,
        'driving_license_no' => 'DL-FORM-1',
        'license_class' => 'B',
        'license_expiry_date' => now()->addYear()->toDateString(),
        'password' => Hash::make('password'),
    ]);
}

function driverFormPayload(User $driver, array $overrides = []): array
{
    return [
        'name' => $driver->name,
        'username' => $driver->username,
        'personal_phone' => '01700000000',
        'whatsapp_id' => '01700000000',
        'user_type' => 'driver',
        'status' => 'active',
        'vendor_id' => $driver->vendor_id,
        'zone_access' => 'all',
        'driving_license_no' => $driver->driving_license_no,
        'license_class' => $driver->license_class,
        'license_expiry_date' => now()->addYear()->toDateString(),
        ...$overrides,
    ];
}

it('accepts every licence class the driver form offers, and nothing else', function () {
    $editor = makeDriverFormEditor();
    $driver = makeFormDriver(Vendor::create(['name' => 'V1', 'status' => 'active']));

    $offered = $this->actingAs($editor)->get("/drivers/{$driver->id}/edit")->assertOk()
        ->viewData('page')['props']['licenseClasses'];

    expect(collect($offered)->pluck('value')->all())->toBe(array_keys(User::LICENSE_CLASSES));

    foreach ($offered as $option) {
        $this->actingAs($editor)->put("/drivers/{$driver->id}", driverFormPayload($driver, ['license_class' => $option['value']]))
            ->assertSessionHasNoErrors();
        expect($driver->fresh()->license_class)->toBe($option['value']);
    }

    $this->actingAs($editor)->put("/drivers/{$driver->id}", driverFormPayload($driver, ['license_class' => 'E']))
        ->assertSessionHasErrors('license_class');
});

it('saves the Area/Location field', function () {
    $editor = makeDriverFormEditor();
    $driver = makeFormDriver(Vendor::create(['name' => 'V2', 'status' => 'active']));

    $this->actingAs($editor)->put("/drivers/{$driver->id}", driverFormPayload($driver, ['area' => 'Gazipur']))
        ->assertSessionHasNoErrors();

    expect($driver->fresh()->area)->toBe('Gazipur');
});

it('flashes why a driver cannot be deleted', function () {
    $editor = makeDriverFormEditor();
    $driver = makeFormDriver(Vendor::create(['name' => 'V3', 'status' => 'active']));
    Trip::create([
        'trip_number' => 'TRIP-DRV-1',
        'requested_by' => $driver->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-05-03',
        'scheduled_start_time' => '08:00:00',
        'scheduled_end_time' => '09:00:00',
        'status' => 'completed',
    ]);

    $this->actingAs($editor)->delete("/drivers/{$driver->id}")
        ->assertSessionHas('error', 'Cannot delete driver: they have requested trips on record. Deactivate the account instead.');

    expect($driver->fresh())->not->toBeNull();
});
