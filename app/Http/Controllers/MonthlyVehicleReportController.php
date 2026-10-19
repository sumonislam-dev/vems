<?php

namespace App\Http\Controllers;

use App\Exports\MonthlyVehicleReportExport;
use App\Models\Vehicle;
use App\Models\Vendor;
use App\Models\Zone;
use App\Services\Reports\MonthlyVehicleReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class MonthlyVehicleReportController extends Controller implements HasMiddleware
{
    public const RENTAL_TYPES = ['own', 'pool', 'rental', 'adhoc', 'support'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-reports', only: ['index']),
            new Middleware('permission:export-reports', only: ['export']),
        ];
    }

    public function index(Request $request)
    {
        [$from, $to, $filters] = $this->resolveFilters($request);

        return Inertia::render('reports/monthly-vehicle', [
            'report' => (new MonthlyVehicleReport($from, $to, $filters, $request->user()))->build(),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                ...array_map(fn ($v) => $v === null ? null : (string) $v, $filters),
            ],
            'filterOptions' => [
                'zones' => Zone::selectableBy(),
                'vendors' => Vendor::query()->select('id', 'name')->orderBy('name')->get(),
                'vehicles' => Vehicle::query()->select('id', 'registration_number')->orderBy('registration_number')->get(),
                'rental_types' => self::RENTAL_TYPES,
            ],
            'maxDays' => MonthlyVehicleReport::MAX_DAYS,
        ]);
    }

    public function export(Request $request)
    {
        $request->validate(['format' => 'required|in:excel,pdf']);
        [$from, $to, $filters] = $this->resolveFilters($request);

        $report = (new MonthlyVehicleReport($from, $to, $filters, $request->user()))->build();
        $fileName = 'monthly-vehicle-report-'.$from->format('Ymd').'-'.$to->format('Ymd');

        if ($request->input('format') === 'excel') {
            return Excel::download(new MonthlyVehicleReportExport($report), "{$fileName}.xlsx");
        }

        return Pdf::loadView('exports.monthly-vehicle-report-pdf', [
            'report' => $report,
            'exportDate' => now()->format('F j, Y g:i A'),
        ])
            ->setPaper('A3', 'landscape')
            ->setOptions(['defaultFont' => 'Arial', 'isHtml5ParserEnabled' => true])
            ->download("{$fileName}.pdf");
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: array}
     */
    private function resolveFilters(Request $request): array
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'zone_id' => [...Zone::validationRule(), 'integer'],
            'vendor_id' => 'nullable|integer|exists:vendors,id',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'rental_type' => 'nullable|in:'.implode(',', self::RENTAL_TYPES),
        ]);

        // Default: the current calendar month
        $from = isset($validated['from']) ? Carbon::parse($validated['from']) : now()->startOfMonth();
        $to = isset($validated['to']) ? Carbon::parse($validated['to']) : $from->copy()->endOfMonth();

        if ($from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1 > MonthlyVehicleReport::MAX_DAYS) {
            throw ValidationException::withMessages([
                'to' => 'Choose a period of '.MonthlyVehicleReport::MAX_DAYS.' days or less.',
            ]);
        }

        $filters = [
            'zone_id' => isset($validated['zone_id']) ? (int) $validated['zone_id'] : null,
            'vendor_id' => isset($validated['vendor_id']) ? (int) $validated['vendor_id'] : null,
            'vehicle_id' => isset($validated['vehicle_id']) ? (int) $validated['vehicle_id'] : null,
            'rental_type' => $validated['rental_type'] ?? null,
        ];

        return [$from, $to, $filters];
    }
}
