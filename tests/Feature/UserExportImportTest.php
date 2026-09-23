<?php

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
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
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'driver', 'guard_name' => 'web']);
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
    $actor = makeUserExportImportActor(['view-users']);
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
    $actor = makeUserExportImportActor(['view-users']);
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

// --- import -----------------------------------------------------------------

function makeUsersImportCsv(string $contents): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'users_import_').'.csv';
    file_put_contents($path, $contents);

    return new UploadedFile($path, 'users.csv', 'text/csv', null, true);
}

it('creates users from an uploaded csv file', function () {
    seedUserExportImportPermissions();
    $actor = makeUserExportImportActor(['create-users']);

    $csv = "name,username,email,personal_phone,role,status\n".
        'Imported One,imported_one_'.uniqid().',imported_one_'.uniqid()."@example.com,01711111111,employee,active\n".
        'Imported Two,imported_two_'.uniqid().',,01722222222,driver,active';

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
        ->and($two)->not->toBeNull()
        ->and($two->hasRole('driver'))->toBeTrue();
});

it('skips invalid rows and reports them instead of failing the whole import', function () {
    seedUserExportImportPermissions();
    $actor = makeUserExportImportActor(['create-users']);
    $existing = User::create([
        'email_verified_at' => now(),
        'name' => 'Existing User',
        'username' => 'already_taken_username',
        'email' => 'existing_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $csv = "name,username,email,role,status\n".
        ',missing_name_'.uniqid().",,employee,active\n".
        'Duplicate Username,'.$existing->username.",,employee,active\n".
        'Valid Row,valid_row_'.uniqid().',,employee,active';

    $response = $this->actingAs($actor)->post(route('users.import'), [
        'file' => makeUsersImportCsv($csv),
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('warning');

    expect(User::where('name', 'Valid Row')->exists())->toBeTrue()
        ->and(User::where('username', 'like', 'missing_name_%')->exists())->toBeFalse();
});

it('denies importing users to someone without create-users permission', function () {
    seedUserExportImportPermissions();
    $actor = makeUserExportImportActor();

    $this->actingAs($actor)->post(route('users.import'), [
        'file' => makeUsersImportCsv("name,username\nBlocked,blocked_".uniqid()),
    ])->assertForbidden();
});
