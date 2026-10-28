<?php

use App\Models\Department;
use App\Models\Logistics;
use App\Models\Trip;
use App\Models\TripPassenger;
use App\Models\TripPassengerEvent;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Vendor;
use App\Models\Zone;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Private documents, passenger history on trip edits, vehicle soft delete,
 * production seeding, Trip Report passenger totals and the logistics page.
 */
function seedSecurityFixPermissions(): void
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    foreach (['view-users', 'create-users', 'view-drivers', 'view-vehicles', 'delete-vehicles', 'view-vendors',
        'view-trips', 'edit-trips', 'view-reports', 'view-logistics', 'view-all-zones'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
}

function securityUser(array $permissions = [], array $attributes = []): User
{
    $user = User::create(array_merge([
        'email_verified_at' => now(),
        'name' => 'Security User',
        'username' => 'security_'.uniqid(),
        'email' => 'security_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ], $attributes));
    $user->givePermissionTo($permissions);

    return $user;
}

function securityVehicle(array $attributes = []): Vehicle
{
    return Vehicle::create(array_merge(['brand' => 'Toyota', 'model' => 'Hiace', 'registration_number' => 'SEC-'.uniqid(), 'is_active' => true], $attributes));
}

function securityTrip(array $attributes = []): Trip
{
    return Trip::create(array_merge([
        'requested_by' => securityUser()->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-11-02',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
        'status' => 'pending',
    ], $attributes));
}

// --- private documents -------------------------------------------------------

it('stores a user\'s NID scan on the private disk', function () {
    seedSecurityFixPermissions();
    Storage::fake('local');
    Storage::fake('public');

    $this->actingAs(securityUser(['create-users']))->post('/users', [
        'zone_access' => 'specific',
        'zone_ids' => [testZoneId()],
        'name' => 'With NID',
        'username' => 'with_nid',
        'personal_phone' => '01700000001',
        'whatsapp_id' => '01700000001',
        'status' => 'active',
        'user_type' => 'employee',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'nid_file' => UploadedFile::fake()->create('nid.pdf', 100, 'application/pdf'),
    ])->assertSessionDoesntHaveErrors();

    $path = User::where('username', 'with_nid')->value('nid_file');
    expect($path)->toStartWith('driver_documents/');
    Storage::disk('local')->assertExists($path);
    Storage::disk('public')->assertMissing($path);
});

it('only serves a user document to the owner or someone allowed to view them', function () {
    seedSecurityFixPermissions();
    Storage::fake('local');
    Storage::disk('local')->put('driver_documents/nid.pdf', 'scan');
    $driver = securityUser([], ['user_type' => 'driver', 'nid_file' => 'driver_documents/nid.pdf']);
    $url = "/users/{$driver->id}/documents/nid_file";

    $this->get($url)->assertRedirect('/login');
    $this->actingAs(securityUser())->get($url)->assertForbidden();
    $this->actingAs($driver)->get($url)->assertOk();
    $this->actingAs(securityUser(['view-drivers']))->get($url)->assertOk();
    $this->actingAs(securityUser(['view-users']))->get("/users/{$driver->id}/documents/password")->assertNotFound();
});

it('keeps another zone\'s vehicle documents out of reach', function () {
    seedSecurityFixPermissions();
    Storage::fake('local');
    Storage::disk('local')->put('vehicle_documents/tax.pdf', 'tax');
    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);
    $vehicle = securityVehicle(['zone_id' => $ctg->id, 'tax_token_file' => 'vehicle_documents/tax.pdf']);
    $url = "/vehicles/{$vehicle->id}/documents/tax_token_file";

    $this->actingAs(securityUser(['view-vehicles'], ['zone_id' => $ctg->id]))->get($url)->assertOk();
    $this->actingAs(securityUser(['view-vehicles'], ['zone_id' => $dhaka->id]))->get($url)->assertNotFound();
    $this->actingAs(securityUser(['view-drivers'], ['zone_id' => $ctg->id]))->get($url)->assertForbidden();
});

it('serves vendor documents to vendor viewers, as a download when asked', function () {
    seedSecurityFixPermissions();
    Storage::fake('local');
    Storage::disk('local')->put('vendor_documents/tin.pdf', 'tin');
    $vendor = Vendor::create(['name' => 'Acme Rentals', 'status' => 'active', 'tin_file' => 'vendor_documents/tin.pdf']);
    $url = "/vendors/{$vendor->id}/documents/tin_file";

    $this->actingAs(securityUser())->get($url)->assertForbidden();
    $this->actingAs(securityUser(['view-vendors']))->get($url.'?download=1')
        ->assertOk()
        ->assertDownload('tin.pdf');
});

// --- passenger history on trip edit -------------------------------------------------

it('keeps passenger rows when a trip is edited', function () {
    seedSecurityFixPermissions();
    $trip = securityTrip();
    $kept = $trip->passengers()->create(['user_id' => securityUser()->id]);
    $dropped = $trip->passengers()->create(['user_id' => securityUser()->id]);
    $added = securityUser();

    $this->actingAs(securityUser(['edit-trips']))->put("/trips/{$trip->id}", [
        'priority' => 'medium',
        'scheduled_date' => '2026-11-02',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
        'passengers' => [['user_id' => $kept->user_id], ['user_id' => $added->id]],
    ])->assertSessionHasNoErrors();

    expect(TripPassenger::find($kept->id))->not->toBeNull()
        ->and(TripPassenger::find($dropped->id))->toBeNull()
        ->and($trip->passengers()->pluck('user_id')->sort()->values()->all())->toBe(collect([$kept->user_id, $added->id])->sort()->values()->all());
});

it('refuses to remove a passenger who already has check-in history', function () {
    seedSecurityFixPermissions();
    $trip = securityTrip(['status' => 'approved']);
    $passenger = $trip->passengers()->create(['user_id' => securityUser()->id]);
    TripPassengerEvent::create([
        'trip_passenger_id' => $passenger->id,
        'trip_id' => $trip->id,
        'user_id' => $passenger->user_id,
        'event_type' => 'check_in',
        'event_time' => now(),
    ]);

    $this->actingAs(securityUser(['edit-trips']))->put("/trips/{$trip->id}", [
        'priority' => 'medium',
        'scheduled_date' => '2026-11-02',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
        'passengers' => [],
    ])->assertSessionHasErrors('passengers');

    expect(TripPassenger::find($passenger->id))->not->toBeNull();
});

// --- vehicle soft delete ------------------------------------------------------------

it('soft-deletes a vehicle so past trips still show it', function () {
    seedSecurityFixPermissions();
    $vehicle = securityVehicle();
    $trip = securityTrip(['vehicle_id' => $vehicle->id, 'status' => 'completed']);

    $this->actingAs(securityUser(['delete-vehicles']))->delete("/vehicles/{$vehicle->id}")
        ->assertRedirect(route('vehicles.index'));

    expect(Vehicle::find($vehicle->id))->toBeNull()
        ->and(Vehicle::withTrashed()->find($vehicle->id)->trashed())->toBeTrue()
        ->and($trip->fresh()->vehicle_id)->toBe($vehicle->id)
        ->and($trip->fresh()->vehicle->registration_number)->toBe($vehicle->registration_number);
});

it('will not delete a vehicle that still has active trips', function () {
    seedSecurityFixPermissions();
    $vehicle = securityVehicle();
    securityTrip(['vehicle_id' => $vehicle->id, 'status' => 'approved']);

    $this->actingAs(securityUser(['delete-vehicles']))->delete("/vehicles/{$vehicle->id}")
        ->assertSessionHas('error');

    expect(Vehicle::find($vehicle->id))->not->toBeNull();
});

// --- production seeding ----------------------------------------------------------

it('seeds only roles, permissions, zones and the real departments in production', function () {
    app()->detectEnvironment(fn () => 'production');

    Artisan::call('db:seed', ['--force' => true]);

    expect(Role::where('name', 'super-admin')->exists())->toBeTrue()
        ->and(Zone::count())->toBeGreaterThan(0)
        ->and(Department::count())->toBe(count(DepartmentSeeder::DEPARTMENTS))
        ->and(Department::find(2)?->name)->toBe('Engagement and Logistics')
        ->and(User::count())->toBe(0)
        ->and(Vehicle::count())->toBe(0)
        ->and(Trip::count())->toBe(0);
});

// --- Trip Report passenger totals -----------------------------------------------------

it('counts Trip Report passengers from the filtered trips only', function () {
    seedSecurityFixPermissions();
    $completed = securityTrip(['status' => 'completed', 'scheduled_date' => now()->toDateString()]);
    $cancelled = securityTrip(['status' => 'cancelled', 'scheduled_date' => now()->toDateString()]);
    $completed->passengers()->create(['user_id' => securityUser()->id, 'status' => 'completed']);
    $cancelled->passengers()->create(['user_id' => securityUser()->id]);
    $cancelled->passengers()->create(['user_id' => securityUser()->id]);

    $this->actingAs(securityUser(['view-reports']))->get('/reports?status=completed')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.summary.total_passengers', 1)
            ->where('report.summary.completed_passengers', 1));
});

// --- logistics detail page ---------------------------------------------------------

it('renders the logistics detail page', function () {
    seedSecurityFixPermissions();
    $department = Department::create(['name' => 'Operations', 'code' => 'OPS-'.uniqid()]);
    $logistic = Logistics::create(['name' => 'Gazipur Hub', 'status' => true, 'department_id' => $department->id]);

    $this->actingAs(securityUser(['view-logistics']))->get("/logistics/{$logistic->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('logistics/show')->where('logistic.name', 'Gazipur Hub'));
});
