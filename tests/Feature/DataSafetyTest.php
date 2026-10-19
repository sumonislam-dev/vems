<?php

use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContactPerson;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Multi-step saves are all-or-nothing, and the app runs on Dhaka time.
 */
function dataSafetyUser(array $permissions): User
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    foreach ($permissions as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Data Safety',
        'username' => 'data_safety_'.uniqid(),
        'email' => 'data_safety_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
    $user->givePermissionTo($permissions);

    return $user;
}

it('does not keep a vendor whose contact persons failed to save', function () {
    VendorContactPerson::creating(fn () => throw new RuntimeException('simulated failure'));

    $this->actingAs(dataSafetyUser(['create-vendors']))->post('/vendors', [
        'name' => 'Half Saved Rentals',
        'status' => 'active',
        'contact_persons' => [['name' => 'Rafiq']],
    ])->assertSessionHas('error', 'Failed to create vendor.');

    expect(Vendor::where('name', 'Half Saved Rentals')->exists())->toBeFalse();
});

it('saves a vendor and its contacts together', function () {
    $this->actingAs(dataSafetyUser(['create-vendors']))->post('/vendors', [
        'name' => 'Whole Rentals',
        'status' => 'active',
        'contact_persons' => [['name' => 'Rafiq', 'is_primary' => true]],
    ])->assertSessionHas('success');

    expect(Vendor::where('name', 'Whole Rentals')->first()->contactPersons()->count())->toBe(1);
});

it('runs on Bangladesh time', function () {
    expect(config('app.timezone'))->toBe('Asia/Dhaka')
        ->and(now()->getTimezone()->getName())->toBe('Asia/Dhaka');
});
