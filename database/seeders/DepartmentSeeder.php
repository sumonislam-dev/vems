<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

/**
 * The organisation's real departments. Runs in production too (see
 * DatabaseSeeder). Ids match the existing records, so imports and
 * references by id line up; 12 is not used.
 */
class DepartmentSeeder extends Seeder
{
    public const DEPARTMENTS = [
        1 => ['Information Technology', 'IT'],
        2 => ['Engagement and Logistics', 'ENL'],
        3 => ['Remediation Programme Department', 'RPD'],
        4 => ['Occupational Safety and Health Complaints Unit', 'OSHCMS'],
        5 => ['Occupational Safety and Health (OSH) Training Department', 'OSHTP'],
        6 => ['Structural Safety Engineering Team', 'SSE'],
        7 => ['Electrical Safety Engineering Team', 'ESE'],
        8 => ['Fire & Life Safety', 'FLSE'],
        9 => ['Boiler Safety Engineering Team', 'BSE'],
        10 => ['Leadership Team', 'LT'],
        11 => ['Human Resource Management', 'HRM'],
        12 => ['Accounts', 'ACC'],
        13 => ['Intern', 'INT'],
    ];

    public function run(): void
    {
        foreach (self::DEPARTMENTS as $id => [$name, $code]) {
            Department::updateOrCreate(['id' => $id], ['name' => $name, 'code' => $code, 'is_active' => true]);
        }
    }
}
