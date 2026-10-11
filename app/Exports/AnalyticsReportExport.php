<?php

namespace App\Exports;

use App\Exports\Sheets\ReportArraySheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Any AnalyticsReport as a workbook: a Summary sheet, then one sheet per
 * section (see AnalyticsReport for the data shape).
 */
class AnalyticsReportExport implements WithMultipleSheets
{
    public function __construct(
        private string $title,
        private string $period,
        private array $report,
    ) {}

    public function sheets(): array
    {
        $summary = [[$this->title], [$this->period], [], ['Measure', 'Value']];
        foreach ($this->report['summary'] as $item) {
            $summary[] = [$item['label'].(isset($item['hint']) ? " ({$item['hint']})" : ''), $item['value'] ?? '—'];
        }

        $sheets = [new ReportArraySheet('Summary', $summary, [1, 4])];

        foreach ($this->report['sections'] as $section) {
            $rows = [array_column($section['columns'], 'label')];
            foreach ($section['rows'] as $row) {
                $rows[] = array_map(fn ($column) => $row[$column['key']] ?? '—', $section['columns']);
            }
            // Sheet titles are limited to 31 characters
            $sheets[] = new ReportArraySheet(mb_substr(str_replace(['/', '\\', '?', '*', '[', ']', ':'], '', $section['title']), 0, 31), $rows, [1]);
        }

        return $sheets;
    }
}
