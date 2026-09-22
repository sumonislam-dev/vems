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

class VehiclesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
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
        return ['ID', 'Brand', 'Model', 'Type', 'Color', 'Registration', 'Vendor', 'Driver', 'Status', 'Created At'];
    }

    /**
     * @param  \App\Models\Vehicle  $vehicle
     */
    public function map($vehicle): array
    {
        return [
            $vehicle->id,
            $vehicle->brand,
            $vehicle->model,
            ucfirst(str_replace('_', ' ', $vehicle->vehicle_type)),
            $vehicle->color,
            $vehicle->registration_number,
            $vehicle->vendor->name ?? '',
            $vehicle->driver->name ?? '',
            $vehicle->is_active ? 'Active' : 'Inactive',
            $vehicle->created_at->format('Y-m-d H:i:s'),
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
