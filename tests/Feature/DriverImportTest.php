<?php

use App\Exports\DriversImportTemplate;
use App\Imports\DriversImport;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function makeDriverImportActor(array $permissions = ['view-drivers', 'import-drivers']): User
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    foreach (['view-drivers', 'create-drivers', 'import-drivers'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    Role::firstOrCreate(['name' => 'driver', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Driver Import Actor',
        'username' => 'driver_import_actor_'.uniqid(),
        'user_type' => 'admin',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
    $user->givePermissionTo($permissions);

    return $user;
}

function makeDriversImportCsv(array $rows): UploadedFile
{
    $columns = array_keys(DriversImportTemplate::COLUMNS);
    $lines = [implode(',', $columns)];
    foreach ($rows as $row) {
        $lines[] = implode(',', array_map(fn ($column) => $row[$column] ?? '', $columns));
    }

    $path = tempnam(sys_get_temp_dir(), 'drivers_import_').'.csv';
    file_put_contents($path, implode("\n", $lines));

    return new UploadedFile($path, 'drivers.csv', 'text/csv', null, true);
}

function driverImportRow(array $overrides = []): array
{
    return [
        'name' => 'Md. Karim Uddin',
        'personal_phone' => '01711111111',
        'vendor' => 'Test Vendor',
        'zone_code' => 'TST',
        'driving_license_no' => 'DK-'.uniqid(),
        'license_class' => 'c',
        'license_issue_date' => '15/01/2022',
        'license_expiry_date' => '2030-01-14',
        ...$overrides,
    ];
}

beforeEach(function () {
    testZoneId();
    Vendor::create(['name' => 'Test Vendor', 'status' => 'active']);
});

it('keeps the import columns and the template columns the same', function () {
    expect(array_keys(DriversImportTemplate::COLUMNS))->toBe(DriversImport::COLUMNS);
});

it('creates drivers from an uploaded file', function () {
    $actor = makeDriverImportActor();

    $this->actingAs($actor)->post(route('drivers.import'), ['file' => makeDriversImportCsv([
        driverImportRow(['driving_license_no' => 'DK-0001', 'designation' => 'Senior Driver']),
    ])])->assertSessionDoesntHaveErrors()->assertSessionHas('success');

    $driver = User::where('driving_license_no', 'DK-0001')->first();

    expect($driver)->not->toBeNull()
        ->and($driver->username)->toBe('karim.uddin')
        ->and($driver->user_type)->toBe('driver')
        ->and($driver->hasRole('driver'))->toBeTrue()
        ->and($driver->driver_status)->toBe('available')
        ->and($driver->status)->toBe('active')
        ->and($driver->vendor->name)->toBe('Test Vendor')
        ->and($driver->whatsapp_id)->toBe('01711111111')
        ->and($driver->license_class)->toBe('C')
        ->and($driver->license_issue_date->format('Y-m-d'))->toBe('2022-01-15')
        ->and($driver->license_expiry_date->format('Y-m-d'))->toBe('2030-01-14')
        ->and($driver->designation)->toBe('Senior Driver')
        ->and($driver->accessZones()->pluck('zones.id')->all())->toBe([testZoneId()])
        ->and($driver->isDriver())->toBeTrue();
});

it('skips rows missing a required column or with bad values, and imports the rest', function () {
    $actor = makeDriverImportActor();

    $response = $this->actingAs($actor)->post(route('drivers.import'), ['file' => makeDriversImportCsv([
        driverImportRow(['name' => 'Good Driver', 'driving_license_no' => 'DK-GOOD']),
        driverImportRow(['name' => 'No Licence', 'driving_license_no' => '']),
        driverImportRow(['name' => 'Bad Vendor', 'vendor' => 'Nobody Ltd']),
        driverImportRow(['name' => 'Bad Class', 'license_class' => 'Z']),
        driverImportRow(['name' => 'Bad Date', 'license_expiry_date' => 'next year']),
        driverImportRow(['name' => 'Expires Before Issue', 'license_issue_date' => '2025-01-01', 'license_expiry_date' => '2024-01-01']),
    ])]);

    $response->assertSessionHas('warning', fn ($message) => str_contains($message, 'Imported 1 driver(s). 5 row(s) skipped'));

    expect(User::where('user_type', 'driver')->pluck('name')->all())->toBe(['Good Driver']);
});

it('skips a licence number that is already on file, so re-importing does not duplicate drivers', function () {
    $actor = makeDriverImportActor();
    $file = fn () => makeDriversImportCsv([driverImportRow(['driving_license_no' => 'DK-SAME'])]);

    $this->actingAs($actor)->post(route('drivers.import'), ['file' => $file()])->assertSessionHas('success');
    $this->actingAs($actor)->post(route('drivers.import'), ['file' => $file()])
        ->assertSessionHas('warning', fn ($message) => str_contains($message, 'A driver with this licence number already exists'));

    expect(User::where('driving_license_no', 'DK-SAME')->count())->toBe(1);
});

it('downloads a template that marks the required columns', function () {
    $actor = makeDriverImportActor();

    $response = $this->actingAs($actor)->get(route('drivers.import-template'));
    $response->assertOk()->assertDownload('drivers_import_template.xlsx');

    $sheets = Excel::toArray(new class {}, $response->baseResponse->getFile()->getPathname(), null, Maatwebsite\Excel\Excel::XLSX);
    $instructions = collect($sheets[1])->filter(fn ($row) => ($row[1] ?? null) === 'Required')->pluck(0)->all();

    expect($sheets[0][0])->toBe(DriversImport::COLUMNS)
        ->and($instructions)->toBe(['name', 'personal_phone', 'vendor', 'zone_code', 'driving_license_no', 'license_class', 'license_expiry_date'])
        ->and(collect($sheets[2])->pluck(0))->toContain('Test Vendor')
        ->and(collect($sheets[3])->pluck(0))->toContain('TST');
});

it('denies the driver import to someone without import-drivers', function () {
    $actor = makeDriverImportActor(['view-drivers', 'create-drivers']);

    $this->actingAs($actor)->get(route('drivers.import-template'))->assertForbidden();
    $this->actingAs($actor)->post(route('drivers.import'), ['file' => makeDriversImportCsv([driverImportRow()])])->assertForbidden();

    expect(User::where('user_type', 'driver')->exists())->toBeFalse();
});

it('reads Excel date cells, which arrive as serial numbers', function () {
    $actor = makeDriverImportActor();
    $serial = Date::PHPToExcel(new DateTime('2031-03-15'));

    $this->actingAs($actor)->post(route('drivers.import'), ['file' => makeDriversImportCsv([
        driverImportRow(['driving_license_no' => 'DK-SERIAL', 'license_expiry_date' => (string) $serial]),
    ])])->assertSessionHas('success');

    expect(User::where('driving_license_no', 'DK-SERIAL')->first()->license_expiry_date->format('Y-m-d'))->toBe('2031-03-15');
});
