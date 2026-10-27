<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\User;
use App\Models\Zone;
use Closure;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;
use Spatie\Permission\Models\Role;

/**
 * Bulk-creates user accounts from an uploaded CSV/Excel file (the template
 * comes from UsersImportTemplate).
 *
 * Columns (headers are matched case-insensitively): name, username, email,
 * employee_id, department_code, zone_code, personal_phone, whatsapp_id, role,
 * status. name, username and zone_code are required. zone_code is one zone
 * code, several separated by commas, or ALL. A blank role means "employee";
 * other roles need manage-user-roles, and super-admin and driver can't be
 * imported (drivers need licence details, so use the Drivers form). Imported
 * accounts get a random password: users set one via "Forgot Password".
 */
class UsersImport implements SkipsEmptyRows, SkipsOnFailure, ToModel, WithHeadingRow, WithMultipleSheets, WithValidation
{
    use SkipsFailures;

    public const ALL_ZONES = 'ALL';

    private const USER_TYPES = [
        'admin' => 'admin',
        'transport-manager' => 'transport_manager',
    ];

    public int $imported = 0;

    /** @var array<string, int> lowercase department code => id */
    private array $departments;

    /** @var array<string, int> lowercase zone code => id, limited to the importer's zones */
    private array $zones;

    private bool $canGrantAllZones;

    /** @var array<int, string> */
    private array $roles;

    public function __construct(private User $importer)
    {
        $this->departments = Department::pluck('id', 'code')
            ->mapWithKeys(fn ($id, $code) => [strtolower($code) => $id])
            ->all();

        $this->canGrantAllZones = Zone::selectableIds($importer) === null;
        $this->zones = Zone::whereIn('id', Zone::selectableBy($importer)->pluck('id'))
            ->pluck('id', 'code')
            ->mapWithKeys(fn ($id, $code) => [strtolower($code) => $id])
            ->all();

        $this->roles = $importer->can('manage-user-roles')
            ? Role::whereNotIn('name', ['super-admin', 'driver'])->pluck('name')->all()
            : ['employee'];
    }

    /**
     * Only the first sheet holds users; the template's other sheets list
     * the valid codes.
     */
    public function sheets(): array
    {
        return [0 => $this];
    }

    public function model(array $row): ?User
    {
        $role = $row['role'] ?: 'employee';

        $user = User::create([
            'name' => $row['name'],
            'username' => $row['username'],
            'email' => $row['email'] ?: null,
            'employee_id' => $row['employee_id'] ?: null,
            'department_id' => $row['department_code'] ? $this->departments[strtolower($row['department_code'])] : null,
            'personal_phone' => $row['personal_phone'] ?: null,
            'whatsapp_id' => $row['whatsapp_id'] ?: ($row['personal_phone'] ?: null),
            'user_type' => self::USER_TYPES[$role] ?? 'employee',
            'status' => $row['status'] ?: 'active',
            'email_verified_at' => now(),
            'password' => Hash::make(Str::random(32)),
        ]);

        $user->assignRole($role);

        $zoneCodes = $this->zoneCodes($row['zone_code']);
        if ($zoneCodes === [self::ALL_ZONES]) {
            Zone::applyAccess($user, 'all');
        } else {
            Zone::applyAccess($user, 'specific', array_map(fn ($code) => $this->zones[$code], $zoneCodes));
        }

        $this->imported++;

        return $user;
    }

    /**
     * Cells come back as numbers for numeric-looking values (phone numbers,
     * employee ids) and missing columns are absent; make every expected
     * column a trimmed string (or null).
     */
    public function prepareForValidation(array $data, int $index): array
    {
        foreach (['name', 'username', 'email', 'employee_id', 'department_code', 'zone_code', 'personal_phone', 'whatsapp_id', 'role', 'status'] as $column) {
            $value = isset($data[$column]) ? trim((string) $data[$column]) : '';
            $data[$column] = $value === '' ? null : $value;
        }

        $data['role'] = $data['role'] ? strtolower($data['role']) : null;
        $data['status'] = $data['status'] ? strtolower($data['status']) : null;

        return $data;
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
            'department_code' => ['nullable', function (string $attribute, mixed $value, Closure $fail) {
                if (! isset($this->departments[strtolower($value)])) {
                    $fail("Unknown department code \"{$value}\".");
                }
            }],
            'zone_code' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                $codes = $this->zoneCodes($value);

                if (in_array(self::ALL_ZONES, $codes, true)) {
                    if ($codes !== [self::ALL_ZONES]) {
                        $fail('Use ALL on its own, not with other zone codes.');
                    } elseif (! $this->canGrantAllZones) {
                        $fail('You are not allowed to grant access to all zones.');
                    }

                    return;
                }

                $unknown = array_diff($codes, array_keys($this->zones));
                if ($unknown !== []) {
                    $fail('Unknown or not allowed zone code "'.strtoupper(implode(', ', $unknown)).'".');
                }
            }],
            'personal_phone' => ['nullable', 'string', 'max:20'],
            'whatsapp_id' => ['nullable', 'string', 'max:20'],
            'role' => ['nullable', function (string $attribute, mixed $value, Closure $fail) {
                if (! in_array($value, $this->roles, true)) {
                    $fail("Role \"{$value}\" can't be imported. Allowed: ".implode(', ', $this->roles).'.');
                }
            }],
            'status' => ['nullable', 'in:active,inactive,suspended'],
        ];
    }

    /**
     * "dhk, CTG" => ['dhk', 'ctg']; "all" => ['ALL'].
     *
     * @return array<int, string>
     */
    private function zoneCodes(?string $value): array
    {
        $codes = array_values(array_unique(array_filter(array_map(
            fn ($code) => strtolower(trim($code)),
            explode(',', (string) $value)
        ))));

        return array_map(fn ($code) => $code === strtolower(self::ALL_ZONES) ? self::ALL_ZONES : $code, $codes);
    }
}
