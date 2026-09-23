<?php

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * DepartmentController::export()/import() had the identical bug as
 * UserController's: stub placeholders returning a "coming soon" JSON
 * message, AND /departments/export + /departments/import were registered
 * after Route::resource('departments', ...) and shadowed by
 * departments.show's GET /departments/{department} — unreachable even with
 * a working controller. See docs/IMPROVEMENT_PLAN_2026-09.md §4.
 */
function seedDepartmentExportImportPermissions(): void
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'view-departments', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'create-departments', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeDepartmentExportImportActor(array $permissions = []): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Dept Export Import Actor',
        'username' => 'dept_export_import_actor_'.uniqid(),
        'email' => 'dept_export_import_actor_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    if (! empty($permissions)) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

// --- export -----------------------------------------------------------

it('downloads a real CSV file (not a JSON stub) when exporting departments', function () {
    seedDepartmentExportImportPermissions();
    $actor = makeDepartmentExportImportActor(['view-departments']);
    Department::create(['name' => 'Engineering', 'code' => 'ENG-'.uniqid()]);

    $response = $this->actingAs($actor)->get(route('departments.export'));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->getContent();
    expect($csv)->toContain('Engineering')
        ->and($csv)->toContain('ID,Name,Code');
});

it('denies exporting departments to someone without view-departments permission', function () {
    seedDepartmentExportImportPermissions();
    $actor = makeDepartmentExportImportActor();

    $this->actingAs($actor)->get(route('departments.export'))->assertForbidden();
});

// --- import -------------------------------------------------------------

function makeDepartmentsImportCsv(string $contents): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'departments_import_').'.csv';
    file_put_contents($path, $contents);

    return new UploadedFile($path, 'departments.csv', 'text/csv', null, true);
}

// Department.code is limited to 10 characters, unlike the longer
// identifiers used elsewhere in these tests — uniqid() alone (13 chars)
// would already exceed it, so keep these short.
function shortDeptCode(string $prefix = ''): string
{
    return strtoupper($prefix.substr(uniqid(), -6));
}

it('creates departments from an uploaded csv file', function () {
    seedDepartmentExportImportPermissions();
    $actor = makeDepartmentExportImportActor(['create-departments']);

    $codeOne = shortDeptCode('A');
    $codeTwo = shortDeptCode('B');
    $csv = "name,code,location,is_active,attendance_mode\n".
        "Imported Dept One,{$codeOne},Dhaka,1,self_service\n".
        "Imported Dept Two,{$codeTwo},Chattogram,0,biometric";

    $response = $this->actingAs($actor)->post(route('departments.import'), [
        'file' => makeDepartmentsImportCsv($csv),
    ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect();
    $response->assertSessionHas('success');

    $one = Department::where('name', 'Imported Dept One')->first();
    $two = Department::where('name', 'Imported Dept Two')->first();

    expect($one)->not->toBeNull()
        ->and($one->is_active)->toBeTrue()
        ->and($one->attendance_mode)->toBe('self_service')
        ->and($two)->not->toBeNull()
        ->and($two->is_active)->toBeFalse();
});

it('skips invalid rows and reports them instead of failing the whole import', function () {
    seedDepartmentExportImportPermissions();
    $actor = makeDepartmentExportImportActor(['create-departments']);
    $existing = Department::create(['name' => 'Existing Dept', 'code' => shortDeptCode('E')]);
    $missingNameCode = shortDeptCode('M');

    $csv = "name,code\n".
        ','.$missingNameCode."\n".
        "Duplicate Code,{$existing->code}\n".
        'Valid Dept,'.shortDeptCode('V');

    $response = $this->actingAs($actor)->post(route('departments.import'), [
        'file' => makeDepartmentsImportCsv($csv),
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('warning');

    expect(Department::where('name', 'Valid Dept')->exists())->toBeTrue()
        ->and(Department::where('code', $missingNameCode)->exists())->toBeFalse();
});

it('denies importing departments to someone without create-departments permission', function () {
    seedDepartmentExportImportPermissions();
    $actor = makeDepartmentExportImportActor();

    $this->actingAs($actor)->post(route('departments.import'), [
        'file' => makeDepartmentsImportCsv('name,code'."\n".'Blocked,'.shortDeptCode('X')),
    ])->assertForbidden();
});
