<?php

namespace App\Exports;

use App\Exports\Sheets\ReportArraySheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The Monthly Vehicle Report as an Excel workbook laid out like the
 * Logistics team's original: a "Summary" sheet (Sheet1) and a "Details"
 * sheet (vehicle-by-day plan). Built from MonthlyVehicleReport::build().
 */
class MonthlyVehicleReportExport implements WithMultipleSheets
{
    public function __construct(private array $report) {}

    public function sheets(): array
    {
        return [
            $this->summarySheet(),
            $this->detailsSheet(),
        ];
    }

    private function summarySheet(): ReportArraySheet
    {
        $r = $this->report;
        $rows = [];
        $bold = [];

        $heading = function (string $text) use (&$rows, &$bold) {
            $rows[] = [];
            $rows[] = [$text];
            $bold[] = count($rows);
        };
        $header = function (array $cells) use (&$rows, &$bold) {
            $rows[] = $cells;
            $bold[] = count($rows);
        };

        $rows[] = ['RMG Sustainability Council (RSC)'];
        $bold[] = 1;
        $rows[] = ['No. of Vehicle for '.$r['period']['label']];
        $bold[] = 2;

        // Daily grid
        $rows[] = ['', ...array_column($r['days'], 'weekday'), 'Total'];
        $bold[] = count($rows);
        $header(['Purpose', ...array_column($r['days'], 'date'), '']);
        foreach ($r['daily'] as $row) {
            $rows[] = [$row['label'], ...$row['values'], $row['total']];
            if ($row['kind'] === 'total') {
                $bold[] = count($rows);
            }
        }

        $heading('Trips by purpose');
        $header(['Purpose', 'Vehicle-days', 'Trips', 'Share of vehicle-days (%)']);
        foreach ($r['purposes'] as $p) {
            $rows[] = [$p['label'], $p['vehicle_days'], $p['trips'], $p['share'] ?? '-'];
        }

        $heading('Ad-hoc vehicles needed in a day');
        $header(['Ad-hoc vehicles in a day', 'Working days', 'Share of working days (%)', 'Ad-hoc vehicle-days']);
        foreach ($r['adhoc_bands'] as $b) {
            $rows[] = [$b['label'], $b['working_days'], $b['share'], $b['adhoc_vehicle_days']];
        }

        $heading('Key numbers');
        $header(['Measure', 'This period', 'Previous period ('.$r['period']['previous_from'].' to '.$r['period']['previous_to'].')']);
        foreach ($r['key_numbers'] as $k) {
            $rows[] = [$k['label'], $k['current'], $k['previous']];
        }

        $heading('By office');
        $header(['Office', 'Trips', 'Vehicle-days', 'Ad-hoc vehicle-days']);
        foreach ($r['offices'] as $o) {
            $rows[] = [$o['zone'], $o['trips'], $o['vehicle_days'], $o['adhoc_vehicle_days']];
        }

        $heading('Service quality and vendor performance');
        $header(['Measure', 'This period', 'Previous period']);
        foreach ($r['service'] as $s) {
            $rows[] = [$s['label'], $s['current'] ?? 'Not tracked', $s['previous'] ?? 'Not tracked'];
        }

        $heading('Vendor scorecard');
        $header(['Vendor', 'Trips', 'Vehicle-days', 'Late arrivals', 'Breakdowns', 'Replacements', 'Complaints', 'Complaints still open', 'Complaints per 100 trips']);
        foreach ($r['vendors'] as $v) {
            $rows[] = [$v['vendor'], $v['trips'], $v['vehicle_days'], 'Not tracked', $v['breakdowns'], $v['replacements'], $v['complaints'], $v['complaints_open'], $v['issues_per_100']];
            if ($v['vendor'] === 'All vendors') {
                $bold[] = count($rows);
            }
        }

        return new ReportArraySheet('Summary', $rows, $bold);
    }

    private function detailsSheet(): ReportArraySheet
    {
        $r = $this->report;
        $d = $r['details'];
        $rows = [];
        $bold = [];
        $blanks = array_fill(0, 6, '');

        $rows[] = [$r['period']['label'].' — (n) = trips that day'];
        $bold[] = 1;
        $rows[] = ['Sl#', 'Driver', 'Vendor', 'Type', 'Phone', 'Vehicle#', ...array_column($r['days'], 'weekday'), 'Total trips', 'Remarks (route)'];
        $bold[] = 2;
        $rows[] = [...$blanks, ...array_column($r['days'], 'date'), '', ''];

        foreach ($d['groups'] as $group) {
            $rows[] = [$group['label']];
            $bold[] = count($rows);

            foreach ($group['rows'] as $v) {
                $rows[] = [
                    $v['sl'], $v['driver'], $v['vendor'], ucfirst((string) $v['vehicle_type']), $v['phone'], $v['registration_number'],
                    ...array_map(fn ($c) => match (true) {
                        $c === null => '',
                        $c['trips'] > 0 => "{$c['text']} ({$c['trips']})",
                        default => $c['text'],
                    }, $v['cells']),
                    $v['total_trips'], $v['remarks'],
                ];
            }

            $rows[] = ['', 'Vehicles used', '', '', '', '', ...$group['used_per_day']];
        }

        $rows[] = [];
        $rows[] = ['', '# of vehicles assigned per day', '', '', '', '', ...$d['assigned_per_day']];
        $bold[] = count($rows);
        $rows[] = ['', '# of vehicles not available (downtime)', '', '', '', '', ...$d['down_per_day']];
        $bold[] = count($rows);
        $rows[] = ['', '# of vehicles remaining idle per day', '', '', '', '', ...$d['idle_per_day']];
        $bold[] = count($rows);
        $rows[] = ['', 'Idle = available but not used. Fleet size: '.$d['fleet_size']];

        return new ReportArraySheet('Details', $rows, $bold);
    }
}
