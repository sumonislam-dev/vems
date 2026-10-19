<?php

namespace App\Http\Controllers;

use App\Exports\AnalyticsReportExport;
use App\Models\Zone;
use App\Services\Reports\AnalyticsReport;
use App\Services\Reports\ComplaintsReport;
use App\Services\Reports\ComplianceReport;
use App\Services\Reports\DepartmentUsageReport;
use App\Services\Reports\DriverPerformanceReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Page, Excel and PDF for every AnalyticsReport, picked by URL slug.
 */
class AnalyticsReportController extends Controller implements HasMiddleware
{
    /** @var array<string, class-string<AnalyticsReport>> */
    public const REPORTS = [
        'complaints' => ComplaintsReport::class,
        'driver-performance' => DriverPerformanceReport::class,
        'compliance' => ComplianceReport::class,
        'department-usage' => DepartmentUsageReport::class,
    ];

    private const MAX_DAYS = 366;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-reports', only: ['show']),
            new Middleware('permission:export-reports', only: ['export']),
        ];
    }

    public function show(Request $request, string $report)
    {
        $class = self::REPORTS[$report];
        [$from, $to, $filters] = $this->resolveFilters($request, $class);

        return Inertia::render('reports/analytics', [
            'slug' => $report,
            'title' => $class::title(),
            'description' => $class::description(),
            'usesDateRange' => $class::usesDateRange(),
            'report' => (new $class($from, $to, $filters, $request->user()))->build(),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                ...array_map(fn ($v) => $v === null ? null : (string) $v, $filters),
            ],
            'filterOptions' => [
                'zones' => Zone::selectableBy(),
                'extra' => $class::filterOptions($request->user()),
            ],
        ]);
    }

    public function export(Request $request, string $report)
    {
        $request->validate(['format' => 'required|in:excel,pdf']);
        $class = self::REPORTS[$report];
        [$from, $to, $filters] = $this->resolveFilters($request, $class);

        $data = (new $class($from, $to, $filters, $request->user()))->build();
        $period = $class::usesDateRange() ? $from->format('d M Y').' to '.$to->format('d M Y') : 'As of '.now()->format('d M Y');
        $fileName = $report.'-report-'.($class::usesDateRange() ? $from->format('Ymd').'-'.$to->format('Ymd') : now()->format('Ymd'));

        if ($request->input('format') === 'excel') {
            return Excel::download(new AnalyticsReportExport($class::title(), $period, $data), "{$fileName}.xlsx");
        }

        return Pdf::loadView('exports.analytics-report-pdf', [
            'title' => $class::title(),
            'period' => $period,
            'report' => $data,
            'exportDate' => now()->format('F j, Y g:i A'),
        ])
            ->setPaper('A4', 'landscape')
            ->setOptions(['defaultFont' => 'Arial', 'isHtml5ParserEnabled' => true])
            ->download("{$fileName}.pdf");
    }

    /**
     * @param  class-string<AnalyticsReport>  $class
     * @return array{0: Carbon, 1: Carbon, 2: array}
     */
    private function resolveFilters(Request $request, string $class): array
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'zone_id' => [...Zone::validationRule(), 'integer'],
            ...$class::filterRules(),
        ]);

        // Default: the current calendar month
        $from = isset($validated['from']) ? Carbon::parse($validated['from']) : now()->startOfMonth();
        $to = isset($validated['to']) ? Carbon::parse($validated['to']) : $from->copy()->endOfMonth();

        if ($from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1 > self::MAX_DAYS) {
            throw ValidationException::withMessages(['to' => 'Choose a period of '.self::MAX_DAYS.' days or less.']);
        }

        $filters = ['zone_id' => isset($validated['zone_id']) ? (int) $validated['zone_id'] : null];
        foreach (array_keys($class::filterRules()) as $key) {
            $filters[$key] = $validated[$key] ?? null;
        }

        return [$from, $to, $filters];
    }
}
