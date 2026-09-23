<?php

namespace App\Imports;

use App\Models\Department;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Bulk-creates departments from an uploaded CSV/Excel file.
 *
 * Expected header row (case-insensitive; Maatwebsite normalizes headers to
 * snake_case): name, code, description, location, phone, email, is_active,
 * attendance_mode. Only name/code are required. Department head and budget
 * allocation aren't set here — assign those afterward on the department's
 * edit page.
 */
class DepartmentsImport implements SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    use SkipsFailures;

    public int $imported = 0;

    public function model(array $row): ?Department
    {
        $attendanceMode = $row['attendance_mode'] ?? null;
        $isActive = $row['is_active'] ?? null;

        $department = Department::create([
            'name' => $row['name'],
            'code' => $row['code'],
            'description' => $row['description'] ?? null,
            'location' => $row['location'] ?? null,
            'phone' => $row['phone'] ?? null,
            'email' => $row['email'] ?? null,
            'is_active' => filter_var($isActive ?? true, FILTER_VALIDATE_BOOLEAN),
            'attendance_mode' => in_array($attendanceMode, ['biometric', 'self_service'], true) ? $attendanceMode : 'biometric',
        ]);

        $this->imported++;

        return $department;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:departments,name'],
            'code' => ['required', 'string', 'max:10', 'unique:departments,code'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
