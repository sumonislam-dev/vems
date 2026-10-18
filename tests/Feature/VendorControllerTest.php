<?php

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Vendor;
use App\Models\VendorContactPerson;
use App\Models\Zone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * VendorController: listing/search, create/update with nested contact
 * persons, private document uploads, the "in use by vehicles" delete guard,
 * per-action permissions and zone scoping (Vendor uses BelongsToZone).
 */
function vendorTestSeedPermissions(): void
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    foreach (['view-vendors', 'create-vendors', 'edit-vendors', 'delete-vendors', 'view-all-zones'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
}

function vendorTestUser(array $permissions = [], ?Zone $zone = null): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Vendor Test User',
        'username' => 'vendor_test_'.uniqid(),
        'email' => 'vendor_test_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
        'zone_id' => $zone?->id,
    ]);
    $user->givePermissionTo($permissions);

    return $user;
}

function vendorTestMake(array $attributes = []): Vendor
{
    return Vendor::create(array_merge(['name' => 'Vendor '.uniqid(), 'status' => 'active'], $attributes));
}

function vendorTestPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Acme Rentals',
        'status' => 'active',
        'phone' => '01700000000',
        'email' => 'acme@example.com',
        'contact_persons' => [
            ['name' => 'Rahim', 'position' => 'Manager', 'is_primary' => true],
        ],
    ], $overrides);
}

beforeEach(function () {
    vendorTestSeedPermissions();
});

// --- index / show -------------------------------------------------------

it('lists vendors with stats and filters by search and status', function () {
    $acme = vendorTestMake(['name' => 'Acme Rentals', 'status' => 'active']);
    $other = vendorTestMake(['name' => 'Bolt Transport', 'status' => 'inactive']);
    Vehicle::create(['brand' => 'Toyota', 'model' => 'Hiace', 'registration_number' => 'VND-'.uniqid(), 'is_active' => true, 'vendor_id' => $acme->id]);

    $viewer = vendorTestUser(['view-vendors']);

    $this->actingAs($viewer)->get('/vendors')->assertOk()->assertInertia(fn ($page) => $page
        ->component('vendors/index')
        ->where('stats.total', 2)
        ->where('stats.active', 1)
        ->where('stats.inactive', 1)
        ->where('stats.with_vehicles', 1)
    );

    $this->actingAs($viewer)->get('/vendors?search=Acme')->assertInertia(fn ($page) => $page
        ->where('vendors.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$acme->id])
        ->where('queryParams.search', 'Acme')
    );

    $this->actingAs($viewer)->get('/vendors?status=inactive')->assertInertia(fn ($page) => $page
        ->where('vendors.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$other->id])
    );
});

it('shows a vendor with its contact persons and vehicles', function () {
    $vendor = vendorTestMake();
    $vendor->contactPersons()->create(['name' => 'Karim']);

    $this->actingAs(vendorTestUser(['view-vendors']))->get("/vendors/{$vendor->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vendors/show')
            ->where('vendor.id', $vendor->id)
            ->where('vendor.contact_persons.0.name', 'Karim')
            ->has('vendor.vehicles', 0)
        );
});

// --- permissions --------------------------------------------------------

it('requires the matching vendor permission for every action', function () {
    $vendor = vendorTestMake();
    $nobody = vendorTestUser();

    $this->actingAs($nobody)->get('/vendors')->assertForbidden();
    $this->actingAs($nobody)->get("/vendors/{$vendor->id}")->assertForbidden();
    $this->actingAs($nobody)->get('/vendors/create')->assertForbidden();
    $this->actingAs($nobody)->post('/vendors', vendorTestPayload())->assertForbidden();
    $this->actingAs($nobody)->get("/vendors/{$vendor->id}/edit")->assertForbidden();
    $this->actingAs($nobody)->put("/vendors/{$vendor->id}", vendorTestPayload())->assertForbidden();
    $this->actingAs($nobody)->delete("/vendors/{$vendor->id}")->assertForbidden();

    // view-vendors alone does not grant write access
    $viewer = vendorTestUser(['view-vendors']);
    $this->actingAs($viewer)->post('/vendors', vendorTestPayload())->assertForbidden();
    $this->actingAs($viewer)->delete("/vendors/{$vendor->id}")->assertForbidden();

    expect(Vendor::count())->toBe(1);
});

// --- create / store -----------------------------------------------------

it('renders the create form with selectable zones', function () {
    $zone = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);

    $this->actingAs(vendorTestUser(['create-vendors']))->get('/vendors/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vendors/create')
            ->where('zones', fn ($zones) => collect($zones)->pluck('id')->contains($zone->id))
        );
});

it('stores a vendor together with its contact persons', function () {
    $this->actingAs(vendorTestUser(['view-vendors', 'create-vendors']))
        ->post('/vendors', vendorTestPayload([
            'contact_persons' => [
                ['name' => 'Rahim', 'position' => 'Manager', 'is_primary' => true],
                ['name' => 'Karim', 'phone' => '01800000000', 'is_primary' => false],
            ],
        ]))
        ->assertRedirect('/vendors')
        ->assertSessionHas('success');

    $vendor = Vendor::where('name', 'Acme Rentals')->firstOrFail();
    expect($vendor->contactPersons()->pluck('name')->sort()->values()->all())->toBe(['Karim', 'Rahim'])
        ->and($vendor->contactPersons()->primary()->value('name'))->toBe('Rahim');
});

it('rejects invalid vendor input', function () {
    $this->actingAs(vendorTestUser(['create-vendors']))
        ->post('/vendors', [
            'name' => '',
            'status' => 'archived',
            'email' => 'not-an-email',
            'website' => 'not a url',
            'contact_persons' => [['position' => 'Nameless']],
        ])
        ->assertSessionHasErrors(['name', 'status', 'email', 'website', 'contact_persons.0.name']);

    expect(Vendor::count())->toBe(0);
});

it('stores uploaded vendor papers on the private disk', function () {
    Storage::fake('local');

    $this->actingAs(vendorTestUser(['create-vendors']))
        ->post('/vendors', vendorTestPayload([
            'tin_file' => UploadedFile::fake()->create('tin.pdf', 100, 'application/pdf'),
        ]))
        ->assertRedirect('/vendors');

    $vendor = Vendor::where('name', 'Acme Rentals')->firstOrFail();
    expect($vendor->tin_file)->toStartWith('vendor_documents/');
    Storage::disk('local')->assertExists($vendor->tin_file);
});

it('rejects a vendor document of a disallowed type', function () {
    Storage::fake('local');

    $this->actingAs(vendorTestUser(['create-vendors']))
        ->post('/vendors', vendorTestPayload([
            'tin_file' => UploadedFile::fake()->create('tin.exe', 10, 'application/octet-stream'),
        ]))
        ->assertSessionHasErrors('tin_file');

    expect(Vendor::count())->toBe(0);
});

// --- edit / update ------------------------------------------------------

it('updates a vendor and syncs contact persons (update, add, remove)', function () {
    $vendor = vendorTestMake(['name' => 'Old Name']);
    $kept = $vendor->contactPersons()->create(['name' => 'Kept']);
    $removed = $vendor->contactPersons()->create(['name' => 'Removed']);

    $this->actingAs(vendorTestUser(['edit-vendors']))
        ->put("/vendors/{$vendor->id}", vendorTestPayload([
            'name' => 'New Name',
            'contact_persons' => [
                ['id' => $kept->id, 'name' => 'Kept Renamed'],
                ['name' => 'Brand New'],
            ],
        ]))
        ->assertRedirect('/vendors');

    expect($vendor->fresh()->name)->toBe('New Name')
        ->and($kept->fresh()->name)->toBe('Kept Renamed')
        ->and(VendorContactPerson::find($removed->id))->toBeNull()
        ->and($vendor->contactPersons()->pluck('name')->sort()->values()->all())->toBe(['Brand New', 'Kept Renamed']);
});

it('will not let an update touch another vendor\'s contact person', function () {
    $vendor = vendorTestMake();
    $foreign = vendorTestMake()->contactPersons()->create(['name' => 'Foreign Contact']);

    $this->actingAs(vendorTestUser(['edit-vendors']))
        ->put("/vendors/{$vendor->id}", vendorTestPayload([
            'contact_persons' => [['id' => $foreign->id, 'name' => 'Hijacked']],
        ]))
        ->assertSessionHasErrors('contact_persons.0.id');

    expect($foreign->fresh()->name)->toBe('Foreign Contact');
});

it('replaces an existing vendor document and deletes the old file', function () {
    Storage::fake('local');
    Storage::disk('local')->put('vendor_documents/old-tin.pdf', 'old');
    $vendor = vendorTestMake(['tin_file' => 'vendor_documents/old-tin.pdf']);

    $this->actingAs(vendorTestUser(['edit-vendors']))
        ->put("/vendors/{$vendor->id}", vendorTestPayload([
            'tin_file' => UploadedFile::fake()->create('new-tin.pdf', 100, 'application/pdf'),
        ]))
        ->assertRedirect('/vendors');

    $vendor->refresh();
    expect($vendor->tin_file)->not->toBe('vendor_documents/old-tin.pdf');
    Storage::disk('local')->assertMissing('vendor_documents/old-tin.pdf');
    Storage::disk('local')->assertExists($vendor->tin_file);
});

// --- destroy ------------------------------------------------------------

it('deletes an unused vendor along with its documents', function () {
    Storage::fake('local');
    Storage::disk('local')->put('vendor_documents/bin.pdf', 'bin');
    $vendor = vendorTestMake(['bin_file' => 'vendor_documents/bin.pdf']);
    $vendor->contactPersons()->create(['name' => 'Contact']);

    $this->actingAs(vendorTestUser(['delete-vendors']))
        ->delete("/vendors/{$vendor->id}")
        ->assertRedirect('/vendors')
        ->assertSessionHas('success');

    expect(Vendor::find($vendor->id))->toBeNull()
        ->and(VendorContactPerson::where('vendor_id', $vendor->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing('vendor_documents/bin.pdf');
});

it('refuses to delete a vendor that still has vehicles', function () {
    $vendor = vendorTestMake();
    Vehicle::create(['brand' => 'Toyota', 'model' => 'Hiace', 'registration_number' => 'VND-'.uniqid(), 'is_active' => true, 'vendor_id' => $vendor->id]);

    $this->actingAs(vendorTestUser(['delete-vendors']))
        ->from('/vendors')
        ->delete("/vendors/{$vendor->id}")
        ->assertRedirect('/vendors')
        ->assertSessionHas('error');

    expect(Vendor::find($vendor->id))->not->toBeNull();
});

// --- zone scoping -------------------------------------------------------

it('hides and blocks another zone\'s vendor from a zone-restricted user', function () {
    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);
    $own = vendorTestMake(['zone_id' => $dhaka->id]);
    $foreign = vendorTestMake(['zone_id' => $ctg->id]);

    $user = vendorTestUser(['view-vendors', 'edit-vendors', 'delete-vendors'], $dhaka);

    $this->actingAs($user)->get('/vendors')->assertInertia(fn ($page) => $page
        ->where('vendors.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$own->id])
        ->where('stats.total', 1)
    );

    $this->actingAs($user)->get("/vendors/{$foreign->id}")->assertNotFound();
    $this->actingAs($user)->put("/vendors/{$foreign->id}", vendorTestPayload())->assertNotFound();
    $this->actingAs($user)->delete("/vendors/{$foreign->id}")->assertNotFound();

    expect(Vendor::withoutGlobalScope('zone')->find($foreign->id)->name)->toBe($foreign->name);
});

it('rejects a zone-restricted user saving a vendor into another zone', function () {
    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);

    $this->actingAs(vendorTestUser(['create-vendors'], $dhaka))
        ->post('/vendors', vendorTestPayload(['zone_id' => $ctg->id]))
        ->assertSessionHasErrors('zone_id');

    expect(Vendor::withoutGlobalScope('zone')->count())->toBe(0);
});
