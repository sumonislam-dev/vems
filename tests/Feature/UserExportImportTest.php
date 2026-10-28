<?php

use App\Models\Department;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * UserController::export()/import() were stub placeholders returning a
 * "coming soon" JSON message — a broken "Export Users" button in
 * production, and an entirely unreachable import route. This covers the
 * real CSV export/import implementation (see docs/IMPROVEMENT_PLAN_2026-09.md §4).
 */
function seedUserExportImportPermissions(): void
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'view-users', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'create-users', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'export-users', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'import-users', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage-user-roles', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'driver', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'transport-manager', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeUserExportImportActor(array $permissions = []): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Export Import Actor',
        'username' => 'export_import_actor_'.uniqid(),
        'email' => 'export_import_actor_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    if (! empty($permissions)) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

// --- export ---------------------------------------------------------------

it('downloads a real CSV file (not a JSON stub) when exporting users', function () {
    seedUserExportImportPermissions();
    $actor = makeUserExportImportActor(['view-users', 'export-users']);
    $department = Department::create(['name' => 'Engineering', 'code' => 'ENG-'.uniqid()]);
    $employee = User::create([
        'email_verified_at' => now(),
        'name' => 'Exportable Employee',
        'username' => 'exportable_employee_'.uniqid(),
        'email' => 'exportable_employee_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'department_id' => $department->id,
        'password' => Hash::make('password'),
    ]);

    $response = $this->actingAs($actor)->get(route('users.export'));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->getContent();
    expect($csv)->toContain('Exportable Employee')
        ->and($csv)->toContain('Engineering')
        ->and($csv)->toContain('ID,Name,Username');
});

it('excludes drivers from the users export (mirrors the index scope)', function () {
    seedUserExportImportPermissions();
    $actor = makeUserExportImportActor(['view-users', 'export-users']);
    User::create([
        'email_verified_at' => now(),
        'name' => 'Should Not Appear Driver',
        'username' => 'excluded_driver_'.uniqid(),
        'email' => 'excluded_driver_'.uniqid().'@example.com',
        'user_type' => 'driver',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $response = $this->actingAs($actor)->get(route('users.export'));

    expect($response->getContent())->not->toContain('Should Not Appear Driver');
});

it('denies exporting users to someone without view-users permission', function () {
    seedUserExportImportPermissions();
    $actor = makeUserExportImportActor();

    $this->actingAs($actor)->get(route('users.export'))->assertForbidden();
});

// --- import template --------------------------------------------------------

it('downloads the import template with the users sheet and code lists', function () {
    seedUserExportImportPermissions();
    testZoneId();
    Department::create(['name' => 'Accounts', 'code' => 'ACC', 'is_active' => true]);
    $actor = makeUserExportImportActor(['import-users']);

    $response = $this->actingAs($actor)->get(route('users.import-template'));

    $response->assertOk();
    $response->assertDownload('users_import_template.xlsx');

    $path = $response->baseResponse->getFile()->getPathname();
    $sheets = Excel::toArray(new class {}, $path, null, Maatwebsite\Excel\Excel::XLSX);

    expect($sheets)->toHaveCount(4)
        ->and($sheets[0][0])->toBe(['name', 'username', 'email', 'employee_id', 'designation', 'department_code', 'zone_code', 'personal_phone', 'whatsapp_id', 'role', 'status'])
        ->and(collect($sheets[1])->pluck(0))->toContain('ACC')
        ->and(collect($sheets[2])->pluck(0))->toContain('TST');
});

it('imports the downloaded template as-is, ignoring the code list sheets', function () {
    seedUserExportImportPermissions();
    testZoneId();
    Department::create(['name' => 'Accounts', 'code' => 'ACC', 'is_active' => true]);
    $actor = makeUserExportImportActor(['create-users', 'import-users']);

    $template = $this->actingAs($actor)->get(route('users.import-template'))->baseResponse->getFile()->getPathname();
    $file = new UploadedFile($template, 'users.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->actingAs($actor)->post(route('users.import'), ['file' => $file])
        ->assertSessionHas('success', 'Imported 1 user(s) successfully.');

    expect(User::where('username', 'example.name')->first()->personal_phone)->toBe('01700000000');
});

it('denies the import template to someone without import-users permission', function () {
    seedUserExportImportPermissions();
    $actor = makeUserExportImportActor();

    $this->actingAs($actor)->get(route('users.import-template'))->assertForbidden();
});

// --- import -----------------------------------------------------------------

function makeUsersImportCsv(string $contents): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'users_import_').'.csv';
    file_put_contents($path, $contents);

    return new UploadedFile($path, 'users.csv', 'text/csv', null, true);
}

it('generates blank usernames on import, unique within the file', function () {
    seedUserExportImportPermissions();
    testZoneId();
    $actor = makeUserExportImportActor(['create-users', 'import-users']);

    $csv = "name,username,email,zone_code\n".
        "Rowshan Runa,,rowshan.runa@rsc-bd.org,TST\n".
        "Md. Shajahan Mia,,,TST\n".
        'Md. Shajahan Mia,,,TST';

    $this->actingAs($actor)->post(route('users.import'), ['file' => makeUsersImportCsv($csv)])
        ->assertSessionDoesntHaveErrors()
        ->assertSessionHas('success');

    expect(User::where('email', 'rowshan.runa@rsc-bd.org')->value('username'))->toBe('rowshan.runa')
        ->and(User::where('name', 'Md. Shajahan Mia')->orderBy('id')->pluck('username')->all())->toBe(['shajahan.mia', 'mdshajahan.mia']);
});

it('creates users from an uploaded csv file', function () {
    seedUserExportImportPermissions();
    testZoneId();
    $department = Department::create(['name' => 'Accounts', 'code' => 'ACC']);
    $actor = makeUserExportImportActor(['create-users', 'import-users']);

    $csv = "name,username,email,employee_id,designation,department_code,zone_code,personal_phone,role,status\n".
        'Imported One,imported_one_'.uniqid().',imported_one_'.uniqid()."@example.com,EMP-1,Accounts Officer,acc,tst,01711111111,employee,active\n".
        'Imported Two,imported_two_'.uniqid().',,,,,TST,01722222222,,';

    $response = $this->actingAs($actor)->post(route('users.import'), [
        'file' => makeUsersImportCsv($csv),
    ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect();
    $response->assertSessionHas('success');

    $one = User::where('name', 'Imported One')->first();
    $two = User::where('name', 'Imported Two')->first();

    expect($one)->not->toBeNull()
        ->and($one->user_type)->toBe('employee')
        ->and($one->hasRole('employee'))->toBeTrue()
        ->and($one->employee_id)->toBe('EMP-1')
        ->and($one->designation)->toBe('Accounts Officer')
        ->and($one->department_id)->toBe($department->id)
        ->and($one->personal_phone)->toBe('01711111111')
        ->and($one->accessZones()->pluck('zones.id')->all())->toBe([testZoneId()])
        ->and($two)->not->toBeNull()
        ->and($two->hasRole('employee'))->toBeTrue()
        ->and($two->status)->toBe('active');
});

it('grants all zones for ALL and several zones for a comma list', function () {
    seedUserExportImportPermissions();
    $tst = testZoneId();
    $other = Zone::create(['name' => 'Other Zone', 'code' => 'OTH'])->id;
    $actor = makeUserExportImportActor(['create-users', 'import-users']);

    $csv = "name,username,zone_code\n".
        "All Zones User,all_zones_user,ALL\n".
        'Two Zones User,two_zones_user,"TST, OTH"';

    $this->actingAs($actor)->post(route('users.import'), ['file' => makeUsersImportCsv($csv)])
        ->assertSessionHas('success');

    $all = User::where('username', 'all_zones_user')->first();
    $two = User::where('username', 'two_zones_user')->first();

    expect($all->hasDirectPermission('view-all-zones'))->toBeTrue()
        ->and($two->accessZones()->pluck('zones.id')->sort()->values()->all())->toBe([$tst, $other]);
});

it('skips invalid rows and reports them instead of failing the whole import', function () {
    seedUserExportImportPermissions();
    testZoneId();
    $actor = makeUserExportImportActor(['create-users', 'import-users']);
    $existing = User::create([
        'email_verified_at' => now(),
        'name' => 'Existing User',
        'username' => 'already_taken_username',
        'email' => 'existing_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $csv = "name,username,department_code,zone_code,role\n".
        ',missing_name_'.uniqid().",,TST,employee\n".
        'Duplicate Username,'.$existing->username.",,TST,employee\n".
        "No Zone,no_zone_user,,,employee\n".
        "Bad Department,bad_department_user,NOPE,TST,employee\n".
        "Bad Zone,bad_zone_user,,NOPE,employee\n".
        'Valid Row,valid_row_'.uniqid().',,TST,employee';

    $response = $this->actingAs($actor)->post(route('users.import'), [
        'file' => makeUsersImportCsv($csv),
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('warning');

    expect(User::where('name', 'Valid Row')->exists())->toBeTrue()
        ->and(User::where('username', 'like', 'missing_name_%')->exists())->toBeFalse()
        ->and(User::whereIn('username', ['no_zone_user', 'bad_department_user', 'bad_zone_user'])->exists())->toBeFalse();
});

it('only lets the importer give roles they may manage, never super-admin or driver', function () {
    seedUserExportImportPermissions();
    testZoneId();
    $plain = makeUserExportImportActor(['create-users', 'import-users']);
    $manager = makeUserExportImportActor(['create-users', 'import-users', 'manage-user-roles']);

    $csv = "name,username,zone_code,role\n".
        "Would Be Super,would_be_super,TST,super-admin\n".
        "Would Be Driver,would_be_driver,TST,driver\n".
        'Would Be Manager,would_be_manager,TST,transport-manager';

    $this->actingAs($plain)->post(route('users.import'), ['file' => makeUsersImportCsv($csv)])
        ->assertSessionHas('warning');
    expect(User::whereIn('username', ['would_be_super', 'would_be_driver', 'would_be_manager'])->exists())->toBeFalse();

    $this->actingAs($manager)->post(route('users.import'), ['file' => makeUsersImportCsv($csv)])
        ->assertSessionHas('warning');
    $created = User::where('username', 'would_be_manager')->first();

    expect(User::whereIn('username', ['would_be_super', 'would_be_driver'])->exists())->toBeFalse()
        ->and($created->hasRole('transport-manager'))->toBeTrue()
        ->and($created->user_type)->toBe('employee');
});

it('does not let a zone-limited importer use other zones or ALL', function () {
    seedUserExportImportPermissions();
    $tst = testZoneId();
    Zone::create(['name' => 'Other Zone', 'code' => 'OTH']);
    $actor = makeUserExportImportActor(['create-users', 'import-users']);
    $actor->accessZones()->sync([$tst]);

    $csv = "name,username,zone_code\n".
        "Own Zone,own_zone_user,TST\n".
        "Other Zone,other_zone_user,OTH\n".
        'All Zones,all_zones_attempt,ALL';

    $this->actingAs($actor)->post(route('users.import'), ['file' => makeUsersImportCsv($csv)])
        ->assertSessionHas('warning');

    expect(User::where('username', 'own_zone_user')->exists())->toBeTrue()
        ->and(User::whereIn('username', ['other_zone_user', 'all_zones_attempt'])->exists())->toBeFalse();
});

it('denies importing users to someone without create-users permission', function () {
    seedUserExportImportPermissions();
    $actor = makeUserExportImportActor();

    $this->actingAs($actor)->post(route('users.import'), [
        'file' => makeUsersImportCsv("name,username\nBlocked,blocked_".uniqid()),
    ])->assertForbidden();
});

it('hashes one unknown password per import rather than one per row', function () {
    seedUserExportImportPermissions();
    testZoneId();
    $actor = makeUserExportImportActor(['create-users', 'import-users']);

    $this->actingAs($actor)->post(route('users.import'), [
        'file' => makeUsersImportCsv("name,zone_code\nFirst Imported,TST\nSecond Imported,TST"),
    ])->assertSessionHas('success');

    $hashes = User::whereIn('name', ['First Imported', 'Second Imported'])->pluck('password')->unique();

    expect($hashes)->toHaveCount(1)
        ->and(Hash::check('password', $hashes->first()))->toBeFalse();
});

it('reports an unexpected failure mid-import instead of a server error', function () {
    seedUserExportImportPermissions();
    testZoneId();
    $actor = makeUserExportImportActor(['create-users', 'import-users']);
    Excel::shouldReceive('import')->andThrow(new RuntimeException('Unknown column designation'));

    $this->actingAs($actor)->post(route('users.import'), [
        'file' => makeUsersImportCsv("name,zone_code\nAnyone,TST"),
    ])->assertRedirect()
        ->assertSessionHas('error', fn ($message) => str_contains($message, 'Import stopped by an unexpected error after 0 user(s)')
            && ! str_contains($message, 'Unknown column'));
});
