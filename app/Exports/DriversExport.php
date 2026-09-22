<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DriversExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return ['ID', 'Name', 'Username', 'Email', 'Type', 'Status', 'Driver Status', 'Department', 'Vendor', 'License No', 'License Expiry', 'Created At'];
    }

    /**
     * @param  \App\Models\User  $driver
     */
    public function map($driver): array
    {
        return [
            $driver->id,
            $driver->name,
            $driver->username,
            $driver->email,
            ucfirst(str_replace('_', ' ', $driver->user_type)),
            ucfirst($driver->status),
            $driver->driver_status ? ucfirst(str_replace('_', ' ', $driver->driver_status)) : '',
            $driver->department->name ?? '',
            $driver->vendor->name ?? '',
            $driver->driving_license_no,
            $driver->license_expiry_date?->format('Y-m-d'),
            $driver->created_at->format('Y-m-d H:i:s'),
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
