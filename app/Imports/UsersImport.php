<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Spatie\Permission\Models\Role;

/**
 * Bulk-creates employee accounts from an uploaded CSV/Excel file.
 *
 * Expected header row (case-insensitive; Maatwebsite normalizes headers to
 * snake_case): name, username, email, personal_phone, whatsapp_id, role,
 * status. Only name/username are required; a missing/unknown role falls
 * back to "employee". Imported accounts get a random password — there's no
 * way to know what the uploader intended, so users log in via "Forgot
 * Password" the first time.
 */
class UsersImport implements SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    use SkipsFailures;

    public int $imported = 0;

    public function model(array $row): ?User
    {
        $status = $row['status'] ?? null;

        $user = User::create([
            'name' => $row['name'],
            'username' => $row['username'],
            'email' => $row['email'] ?? null,
            'personal_phone' => $row['personal_phone'] ?? null,
            'whatsapp_id' => $row['whatsapp_id'] ?? ($row['personal_phone'] ?? null),
            'user_type' => 'employee',
            'status' => in_array($status, ['active', 'inactive', 'suspended'], true) ? $status : 'active',
            'email_verified_at' => now(),
            'password' => Hash::make(Str::random(16)),
        ]);

        $role = Role::where('name', $row['role'] ?? 'employee')->first()
            ?? Role::where('name', 'employee')->first();

        if ($role) {
            $user->assignRole($role);
        }

        $this->imported++;

        return $user;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
        ];
    }
}
