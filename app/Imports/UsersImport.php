<?php

namespace App\Imports;

use App\Imports\Concerns\ResolvesUserImportCodes;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
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
 * employee_id, designation, department_code, zone_code, personal_phone,
 * whatsapp_id, role, status. name and zone_code are required; a blank username
 * is generated from the email or name (see UsernameGenerator). zone_code is
 * one zone code, several separated by commas, or ALL. A blank role means
 * "employee"; other roles need manage-user-roles, and super-admin and driver
 * can't be imported (drivers have their own import, DriversImport). Imported
 * accounts get a random password: users set one via "Forgot Password".
 */
class UsersImport implements SkipsEmptyRows, SkipsOnFailure, ToModel, WithHeadingRow, WithMultipleSheets, WithValidation
{
    use ResolvesUserImportCodes, SkipsFailures;

    // The admin role makes an admin account; every other role (including
    // transport-manager: office staff who run transport) is an employee
    private const USER_TYPES = [
        'admin' => 'admin',
    ];

    public int $imported = 0;

    /** @var array<int, string> */
    private array $roles;

    public function __construct(private User $importer)
    {
        $this->loadCodes($importer);

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
        // All-or-nothing per row: the account, its role and its zones
        $user = DB::transaction(function () use ($row) {
            $role = $row['role'] ?: 'employee';

            $user = User::create([
                'name' => $row['name'],
                'username' => $row['username'],
                'email' => $row['email'],
                'employee_id' => $row['employee_id'],
                'designation' => $row['designation'],
                'department_id' => $this->departmentId($row['department_code']),
                'personal_phone' => $row['personal_phone'],
                'whatsapp_id' => $row['whatsapp_id'] ?: $row['personal_phone'],
                'user_type' => self::USER_TYPES[$role] ?? 'employee',
                'status' => $row['status'] ?: 'active',
                'email_verified_at' => now(),
                'password' => $this->unknownPasswordHash(),
            ]);

            $user->assignRole($role);
            $this->applyZones($user, $row['zone_code']);

            return $user;
        });

        $this->imported++;

        return $user;
    }

    public function prepareForValidation(array $data, int $index): array
    {
        $data = $this->trimColumns($data, ['name', 'username', 'email', 'employee_id', 'designation', 'department_code', 'zone_code', 'personal_phone', 'whatsapp_id', 'role', 'status']);

        $data['role'] = $data['role'] ? strtolower($data['role']) : null;
        $data['status'] = $data['status'] ? strtolower($data['status']) : null;

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
            'department_code' => ['nullable', $this->departmentRule()],
            'zone_code' => ['required', $this->zoneRule()],
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
}
