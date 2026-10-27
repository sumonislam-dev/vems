<?php

namespace App\Exports;

use App\Exports\Sheets\ReportArraySheet;
use App\Imports\UsersImport;
use App\Models\Department;
use App\Models\User;
use App\Models\Zone;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Spatie\Permission\Models\Role;

/**
 * The Excel file for UsersImport: a "Users" sheet with the headers and one
 * example row, then sheets listing the department, zone and role codes the
 * downloading user may use.
 */
class UsersImportTemplate implements WithMultipleSheets
{
    public const HEADINGS = [
        'name', 'username', 'email', 'employee_id', 'designation', 'department_code',
        'zone_code', 'personal_phone', 'whatsapp_id', 'role', 'status',
    ];

    public function __construct(private User $user) {}

    public function sheets(): array
    {
        $departments = Department::where('is_active', true)->orderBy('name')->get(['code', 'name']);
        $zones = Zone::whereIn('id', Zone::selectableBy($this->user)->pluck('id'))->orderBy('name')->get(['code', 'name']);
        $canGrantAllZones = Zone::selectableIds($this->user) === null;
        $roles = $this->user->can('manage-user-roles')
            ? Role::whereNotIn('name', ['super-admin', 'driver'])->orderBy('name')->pluck('name')
            : collect(['employee']);

        $example = [
            'Example Name', 'example.name', 'example.name@example.com', 'EMP-001', 'Officer',
            $departments->first()?->code, $zones->first()?->code,
            '01700000000', '01700000000', 'employee', 'active',
        ];

        $zoneRows = $zones->map(fn ($zone) => [$zone->code, $zone->name])->all();
        if ($canGrantAllZones) {
            $zoneRows[] = [UsersImport::ALL_ZONES, 'All zones'];
        }

        return [
            new class('Users', [self::HEADINGS, $example], [1]) extends ReportArraySheet implements WithColumnFormatting
            {
                // Keep leading zeros in ids and phone numbers typed into the sheet
                public function columnFormats(): array
                {
                    return [
                        'D2:D1000' => NumberFormat::FORMAT_TEXT,
                        'G2:H1000' => NumberFormat::FORMAT_TEXT,
                    ];
                }
            },
            new ReportArraySheet('Departments', [
                ['department_code', 'name'],
                ...$departments->map(fn ($department) => [$department->code, $department->name]),
            ], [1]),
            new ReportArraySheet('Zones', [
                ['zone_code', 'name'],
                ...$zoneRows,
                [],
                ['Several zones: separate the codes with commas, e.g. DHK,CTG'],
            ], [1]),
            new ReportArraySheet('Roles', [
                ['role'],
                ...$roles->map(fn ($role) => [$role]),
                [],
                ['Blank role = employee. Add drivers from the Drivers page (they need licence details).'],
            ], [1]),
        ];
    }
}
