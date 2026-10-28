<?php

namespace App\Exports;

use App\Exports\Sheets\ReportArraySheet;
use App\Imports\DriversImport;
use App\Models\Department;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The Excel file for DriversImport: a "Drivers" sheet with the headers (the
 * required ones shaded) and one example row, an "Instructions" sheet saying
 * what goes in each column and whether it's required, then the vendor, zone
 * and department codes the downloading user may use.
 */
class DriversImportTemplate implements WithMultipleSheets
{
    /** column => [required?, what to enter] */
    public const COLUMNS = [
        'name' => [true, 'Full name.'],
        'username' => [false, 'Leave blank to make one from the email, or else the name (Md. Shajahan Mia -> shajahan.mia).'],
        'email' => [false, 'Must not belong to another user.'],
        'employee_id' => [false, 'Must not belong to another user.'],
        'designation' => [false, 'e.g. Driver, Senior Driver.'],
        'personal_phone' => [true, 'Mobile number, e.g. 01700000000.'],
        'whatsapp_id' => [false, 'Blank = same as personal_phone.'],
        'nid_number' => [false, 'Must not belong to another user.'],
        'vendor' => [true, 'Vendor name exactly as on the Vendors sheet.'],
        'zone_code' => [true, 'A code from the Zones sheet; several separated by commas, e.g. DHK,CTG.'],
        'department_code' => [false, 'A code from the Departments sheet.'],
        'driving_license_no' => [true, 'Must not belong to another driver (a repeated licence row is skipped).'],
        'license_class' => [true, 'One of: A, B, C, D.'],
        'license_issue_date' => [false, 'Date, e.g. 2020-01-15 or 15/01/2020. Not in the future.'],
        'license_expiry_date' => [true, 'Date, e.g. 2030-01-14 or 14/01/2030. After the issue date.'],
        'blood_group' => [false, 'One of: A+, A-, B+, B-, AB+, AB-, O+, O-.'],
        'joining_date' => [false, 'Date. Not in the future.'],
        'present_address' => [false, 'Free text.'],
        'emergency_contact_name' => [false, 'Free text.'],
        'emergency_phone' => [false, 'Phone number.'],
        'driver_status' => [false, 'One of: available, on_leave, inactive, suspended. Blank = available.'],
        'status' => [false, 'One of: active, inactive, suspended. Blank = active.'],
    ];

    public function __construct(private User $user) {}

    public function sheets(): array
    {
        $vendors = Vendor::active()->orderBy('name')->pluck('name');
        $zones = Zone::whereIn('id', Zone::selectableBy($this->user)->pluck('id'))->orderBy('name')->get(['code', 'name']);
        $departments = Department::where('is_active', true)->orderBy('name')->get(['code', 'name']);

        $zoneRows = $zones->map(fn ($zone) => [$zone->code, $zone->name])->all();
        if (Zone::selectableIds($this->user) === null) {
            $zoneRows[] = [DriversImport::ALL_ZONES, 'All zones'];
        }

        $example = [
            'name' => 'Md. Example Driver',
            'personal_phone' => '01700000000',
            'designation' => 'Driver',
            'vendor' => $vendors->first(),
            'zone_code' => $zones->first()?->code,
            'driving_license_no' => 'DK0000000L00000',
            'license_class' => 'C',
            'license_issue_date' => now()->subYears(2)->format('Y-m-d'),
            'license_expiry_date' => now()->addYears(3)->format('Y-m-d'),
        ];

        return [
            $this->driversSheet(array_map(fn ($column) => $example[$column] ?? null, array_keys(self::COLUMNS))),
            new ReportArraySheet('Instructions', [
                ['column', 'required', 'what to enter'],
                ...collect(self::COLUMNS)->map(fn ($spec, $column) => [$column, $spec[0] ? 'Required' : 'Optional', $spec[1]])->values(),
                [],
                ['Shaded headers on the Drivers sheet are required. Delete the example row before importing.'],
                ['Imported drivers get a random password: reset it from the driver\'s profile before they sign in.'],
            ], [1]),
            new ReportArraySheet('Vendors', [['vendor'], ...$vendors->map(fn ($name) => [$name])], [1]),
            new ReportArraySheet('Zones', [['zone_code', 'name'], ...$zoneRows], [1]),
            new ReportArraySheet('Departments', [
                ['department_code', 'name'],
                ...$departments->map(fn ($department) => [$department->code, $department->name]),
            ], [1]),
        ];
    }

    /**
     * @param  array<int, mixed>  $example
     */
    private function driversSheet(array $example): ReportArraySheet
    {
        return new class('Drivers', [array_keys(self::COLUMNS), $example], [1]) extends ReportArraySheet implements WithColumnFormatting
        {
            public function styles(Worksheet $sheet): array
            {
                $styles = parent::styles($sheet);

                foreach (array_keys(DriversImportTemplate::COLUMNS) as $i => $column) {
                    if (DriversImportTemplate::COLUMNS[$column][0]) {
                        $styles[Coordinate::stringFromColumnIndex($i + 1).'1'] = [
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFDE68A']],
                        ];
                    }
                }

                return $styles;
            }

            // Keep leading zeros in ids, phone and licence numbers; show dates as 2027-12-31
            public function columnFormats(): array
            {
                $formats = [];
                foreach (array_keys(DriversImportTemplate::COLUMNS) as $i => $column) {
                    $letter = Coordinate::stringFromColumnIndex($i + 1);
                    $formats["{$letter}2:{$letter}1000"] = str_ends_with($column, '_date')
                        ? NumberFormat::FORMAT_DATE_YYYYMMDD
                        : NumberFormat::FORMAT_TEXT;
                }

                return $formats;
            }
        };
    }
}
