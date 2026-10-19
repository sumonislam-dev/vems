<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * A plain worksheet from pre-built rows, with chosen rows in bold
 * (section titles, headers, totals).
 */
class ReportArraySheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    /**
     * @param  array<int, array>  $rows
     * @param  array<int, int>  $boldRows  1-based row numbers
     */
    public function __construct(
        private string $title,
        private array $rows,
        private array $boldRows = [],
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function styles(Worksheet $sheet): array
    {
        return collect($this->boldRows)
            ->unique()
            ->mapWithKeys(fn (int $row) => [$row => ['font' => ['bold' => true]]])
            ->all();
    }
}
