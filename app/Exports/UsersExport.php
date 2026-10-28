<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Users list export (Excel); UserController's CSV and PDF use the same
 * headings and rows.
 */
class UsersExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public const HEADINGS = ['ID', 'Name', 'Username', 'Employee ID', 'Designation', 'Email', 'User Type', 'Status', 'Department', 'Phone', 'Created At'];

    public function __construct(protected $query) {}

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return self::HEADINGS;
    }

    /**
     * @param  User  $user
     */
    public function map($user): array
    {
        return self::row($user);
    }

    /**
     * @param  User  $user
     */
    public static function row($user): array
    {
        return [
            $user->id,
            $user->name,
            $user->username,
            $user->employee_id,
            $user->designation,
            $user->email,
            $user->user_type,
            $user->status,
            $user->department->name ?? '',
            $user->personal_phone ?? $user->official_phone,
            $user->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }
}
