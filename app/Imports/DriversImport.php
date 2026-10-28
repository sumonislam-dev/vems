<?php

namespace App\Imports;

use App\Imports\Concerns\ResolvesUserImportCodes;
use App\Models\User;
use App\Models\Vendor;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Bulk-creates drivers from an uploaded CSV/Excel file (the template, which
 * marks the required columns, comes from DriversImportTemplate). Each row
 * becomes a driver-type user with the driver role, like Drivers -> Create.
 *
 * Required: name, personal_phone, vendor (an active vendor's name),
 * zone_code, driving_license_no, license_class and license_expiry_date. A
 * blank username is generated from the email or name; a blank whatsapp_id
 * copies personal_phone; driver_status defaults to available and status to
 * active. A licence number already on file is rejected, so importing the
 * same file twice doesn't duplicate drivers. Dates may be Excel dates or
 * text like 2027-12-31 or 31/12/2027. Drivers get a random password: reset
 * it from their profile before they first sign in.
 */
class DriversImport implements SkipsEmptyRows, SkipsOnFailure, ToModel, WithHeadingRow, WithMultipleSheets, WithValidation
{
    use ResolvesUserImportCodes, SkipsFailures;

    public const COLUMNS = [
        'name', 'username', 'email', 'employee_id', 'designation', 'personal_phone',
        'whatsapp_id', 'nid_number', 'vendor', 'zone_code', 'department_code',
        'driving_license_no', 'license_class', 'license_issue_date', 'license_expiry_date',
        'blood_group', 'joining_date', 'present_address', 'emergency_contact_name',
        'emergency_phone', 'driver_status', 'status',
    ];

    public const LICENSE_CLASSES = ['A', 'B', 'C', 'D'];

    public const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    /** on_trip is set by trips, not by hand */
    public const DRIVER_STATUSES = ['available', 'on_leave', 'inactive', 'suspended'];

    public int $imported = 0;

    /** @var array<string, int> lowercase vendor name => id */
    private array $vendors;

    public function __construct(User $importer)
    {
        $this->loadCodes($importer);

        $this->vendors = Vendor::active()->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [Str::lower(trim($name)) => $id])
            ->all();
    }

    /**
     * Only the first sheet holds drivers; the template's other sheets
     * explain the columns and list the valid codes.
     */
    public function sheets(): array
    {
        return [0 => $this];
    }

    public function model(array $row): ?User
    {
        // All-or-nothing per row: the account, its role and its zones
        $driver = DB::transaction(function () use ($row) {
            $driver = User::create([
                'name' => $row['name'],
                'username' => $row['username'],
                'email' => $row['email'],
                'employee_id' => $row['employee_id'],
                'designation' => $row['designation'],
                'personal_phone' => $row['personal_phone'],
                'whatsapp_id' => $row['whatsapp_id'] ?: $row['personal_phone'],
                'nid_number' => $row['nid_number'],
                'vendor_id' => $this->vendors[Str::lower($row['vendor'])],
                'department_id' => $this->departmentId($row['department_code']),
                'driving_license_no' => $row['driving_license_no'],
                'license_class' => $row['license_class'],
                'license_issue_date' => $row['license_issue_date'],
                'license_expiry_date' => $row['license_expiry_date'],
                'blood_group' => $row['blood_group'],
                'joining_date' => $row['joining_date'],
                'present_address' => $row['present_address'],
                'emergency_contact_name' => $row['emergency_contact_name'],
                'emergency_phone' => $row['emergency_phone'],
                'user_type' => 'driver',
                'driver_status' => $row['driver_status'] ?: 'available',
                'status' => $row['status'] ?: 'active',
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(32)),
            ]);

            $driver->assignRole('driver');
            $this->applyZones($driver, $row['zone_code']);

            return $driver;
        });

        $this->imported++;

        return $driver;
    }

    public function prepareForValidation(array $data, int $index): array
    {
        $data = $this->trimColumns($data, self::COLUMNS);

        foreach (['license_class', 'blood_group'] as $column) {
            $data[$column] = $data[$column] ? strtoupper($data[$column]) : null;
        }
        foreach (['driver_status', 'status'] as $column) {
            $data[$column] = $data[$column] ? str_replace(' ', '_', strtolower($data[$column])) : null;
        }
        foreach (['license_issue_date', 'license_expiry_date', 'joining_date'] as $column) {
            $data[$column] = $this->toDate($data[$column]);
        }

        return $this->withUsername($data);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'employee_id' => ['nullable', 'string', 'max:255', 'unique:users,employee_id'],
            'designation' => ['nullable', 'string', 'max:255'],
            'personal_phone' => ['required', 'string', 'max:20'],
            'whatsapp_id' => ['nullable', 'string', 'max:50'],
            'nid_number' => ['nullable', 'string', 'max:50', 'unique:users,nid_number'],
            'vendor' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                if (! isset($this->vendors[Str::lower($value)])) {
                    $fail("Unknown or inactive vendor \"{$value}\". Use a name from the Vendors sheet.");
                }
            }],
            'zone_code' => ['required', $this->zoneRule()],
            'department_code' => ['nullable', $this->departmentRule()],
            'driving_license_no' => ['required', 'string', 'max:50', 'unique:users,driving_license_no'],
            'license_class' => ['required', 'in:'.implode(',', self::LICENSE_CLASSES)],
            'license_issue_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'license_expiry_date' => ['required', 'date_format:Y-m-d'],
            'blood_group' => ['nullable', 'in:'.implode(',', self::BLOOD_GROUPS)],
            'joining_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'present_address' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_phone' => ['nullable', 'string', 'max:20'],
            'driver_status' => ['nullable', 'in:'.implode(',', self::DRIVER_STATUSES)],
            'status' => ['nullable', 'in:active,inactive,suspended'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function customValidationMessages(): array
    {
        return [
            'driving_license_no.unique' => 'A driver with this licence number already exists.',
            'nid_number.unique' => 'This NID number is already registered.',
            'license_issue_date.date_format' => 'Use a date like 2027-12-31 or 31/12/2027.',
            'license_expiry_date.date_format' => 'Use a date like 2027-12-31 or 31/12/2027.',
            'joining_date.date_format' => 'Use a date like 2027-12-31 or 31/12/2027.',
        ];
    }

    /** The expiry date must come after the issue date, when both are given */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($validator->getData() as $rowNumber => $row) {
                $issued = $row['license_issue_date'] ?? null;
                $expires = $row['license_expiry_date'] ?? null;

                if ($this->isDate($issued) && $this->isDate($expires) && $expires <= $issued) {
                    $validator->errors()->add("{$rowNumber}.license_expiry_date", 'License expiry date must be after the issue date.');
                }
            }
        });
    }

    private function isDate(?string $value): bool
    {
        return $value !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }
}
