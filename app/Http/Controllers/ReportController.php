<?php

namespace App\Http\Controllers;

use App\Exports\TripsReportExport;
use App\Models\Trip;
use App\Models\TripPassenger;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRoute;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-reports', only: ['index']),
            new Middleware('permission:export-reports', only: ['export']),
        ];
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'schedule_type' => 'nullable|string|in:pick-and-drop,pick-up,drop-off,engineer,training,adhoc,reposition,inspection,complaints,CVV,Incident Inspection,officials,Assigned',
            'status' => 'nullable|string|in:pending,approved,rejected,assigned,in_progress,completed,cancelled',
            'driver_id' => 'nullable|integer|exists:users,id',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'route_id' => 'nullable|integer|exists:vehicle_routes,id',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:10|max:100',
        ]);

        $to = !empty($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : now()->endOfDay();

        $from = !empty($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : (clone $to)->subDays(29)->startOfDay();

        $scheduleType = $validated['schedule_type'] ?? null;
        $status = $validated['status'] ?? null;
        $driverId = $validated['driver_id'] ?? null;
        $vehicleId = $validated['vehicle_id'] ?? null;
        $routeId = $validated['route_id'] ?? null;
        $search = $validated['search'] ?? null;
        $perPage = $validated['per_page'] ?? 15;

        $tripQuery = Trip::query()
            ->with([
                'vehicle:id,registration_number,brand,model',
                'driver:id,name',
                'department:id,name',
                'vehicleRoute:id,name',
            ])
            ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()]);

        if (!empty($scheduleType)) {
            $tripQuery->where('schedule_type', $scheduleType);
        }

        if (!empty($status)) {
            $tripQuery->where('status', $status);
        }

        if (!empty($driverId)) {
            $tripQuery->where('driver_id', $driverId);
        }

        if (!empty($vehicleId)) {
            $tripQuery->where('vehicle_id', $vehicleId);
        }

        if (!empty($routeId)) {
            $tripQuery->where('vehicle_route_id', $routeId);
        }

        if (!empty($search)) {
            $tripQuery->where(function ($q) use ($search) {
                $q->where('trip_number', 'like', "%{$search}%")
                    ->orWhere('start_location', 'like', "%{$search}%")
                    ->orWhere('end_location', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('driver', fn ($driverQuery) => $driverQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('registration_number', 'like', "%{$search}%"));
            });
        }

        $tripAggregate = (clone $tripQuery)
            ->selectRaw(
                'COUNT(*) as total_trips, '
                . "SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_trips, "
                . "SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_trips"
            )
            ->first();

        $passengerSummary = TripPassenger::query()
            ->join('trips', 'trips.id', '=', 'trip_passengers.trip_id')
            ->whereBetween('trips.scheduled_date', [$from->toDateString(), $to->toDateString()])
            ->when($scheduleType, fn ($query) => $query->where('trips.schedule_type', $scheduleType))
            ->selectRaw(
                'COUNT(*) as total, '
                . "SUM(CASE WHEN trip_passengers.status = 'completed' THEN 1 ELSE 0 END) as completed, "
                . "SUM(CASE WHEN trip_passengers.status = 'no_show' THEN 1 ELSE 0 END) as no_show"
            )
            ->first();

        $tripsByStatus = (clone $tripQuery)
            ->selectRaw('status, COUNT(*) as value')
            ->groupBy('status')
            ->orderByDesc('value')
            ->get()
            ->map(fn ($row) => [
                'status' => (string) $row->status,
                'value' => (int) $row->value,
            ])
            ->values();

        $tripsByScheduleType = (clone $tripQuery)
            ->selectRaw('schedule_type, COUNT(*) as value')
            ->groupBy('schedule_type')
            ->orderByDesc('value')
            ->get()
            ->map(fn ($row) => [
                'schedule_type' => (string) $row->schedule_type,
                'value' => (int) $row->value,
            ])
            ->values();

        $dailyTrips = (clone $tripQuery)
            ->selectRaw('scheduled_date as date, COUNT(*) as total, SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed')
            ->groupBy('scheduled_date')
            ->orderBy('scheduled_date')
            ->get()
            ->map(fn ($row) => [
                'date' => (string) $row->date,
                'total' => (int) $row->total,
                'completed' => (int) $row->completed,
            ])
            ->values();

        $topRoutes = (clone $tripQuery)
            ->join('vehicle_routes', 'vehicle_routes.id', '=', 'trips.vehicle_route_id')
            ->selectRaw('vehicle_routes.name as route_name, COUNT(*) as trip_count')
            ->groupBy('vehicle_routes.name')
            ->orderByDesc('trip_count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'route_name' => (string) $row->route_name,
                'trip_count' => (int) $row->trip_count,
            ])
            ->values();

        $tripHistories = (clone $tripQuery)
            ->orderByDesc('scheduled_date')
            ->orderByDesc('scheduled_start_time')
            ->paginate($perPage)
            ->withQueryString();

        $activeVehicles = Vehicle::where('is_active', true)->count();

        $totalTrips = (int) ($tripAggregate->total_trips ?? 0);
        $completedTrips = (int) ($tripAggregate->completed_trips ?? 0);
        $cancelledTrips = (int) ($tripAggregate->cancelled_trips ?? 0);

        $reportData = [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'schedule_type' => $scheduleType,
                'status' => $status,
                'driver_id' => $driverId,
                'vehicle_id' => $vehicleId,
                'route_id' => $routeId,
                'search' => $search,
                'per_page' => $perPage,
            ],
            'summary' => [
                'total_trips' => $totalTrips,
                'completed_trips' => $completedTrips,
                'cancelled_trips' => $cancelledTrips,
                'completion_rate' => $totalTrips > 0 ? round(($completedTrips / $totalTrips) * 100, 2) : 0,
                'cancellation_rate' => $totalTrips > 0 ? round(($cancelledTrips / $totalTrips) * 100, 2) : 0,
                'active_vehicles' => $activeVehicles,
                'total_passengers' => (int) ($passengerSummary->total ?? 0),
                'completed_passengers' => (int) ($passengerSummary->completed ?? 0),
                'no_show_passengers' => (int) ($passengerSummary->no_show ?? 0),
            ],
            'charts' => [
                'daily_trips' => $dailyTrips,
                'trips_by_status' => $tripsByStatus,
                'trips_by_schedule_type' => $tripsByScheduleType,
            ],
            'top_routes' => $topRoutes,
            'trip_histories' => $tripHistories,
            'filter_options' => [
                'statuses' => ['pending', 'approved', 'rejected', 'assigned', 'in_progress', 'completed', 'cancelled'],
                'drivers' => User::query()
                    ->where('status', 'active')
                    ->where('user_type', 'driver')
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get(),
                'vehicles' => Vehicle::query()
                    ->select('id', 'registration_number')
                    ->orderBy('registration_number')
                    ->get(),
                'routes' => VehicleRoute::query()
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get(),
            ],
        ];

        return Inertia::render('reports/index', [
            'report' => $reportData,
            'queryParams' => $request->only(['from', 'to', 'schedule_type', 'status', 'driver_id', 'vehicle_id', 'route_id', 'search', 'per_page']),
        ]);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'schedule_type' => 'nullable|string|in:pick-and-drop,pick-up,drop-off,engineer,training,adhoc,reposition,inspection,complaints,CVV,Incident Inspection,officials,Assigned',
            'status' => 'nullable|string|in:pending,approved,rejected,assigned,in_progress,completed,cancelled',
            'driver_id' => 'nullable|integer|exists:users,id',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'route_id' => 'nullable|integer|exists:vehicle_routes,id',
            'search' => 'nullable|string|max:100',
            'format' => 'nullable|string|in:csv,excel,pdf',
        ]);

        $to = !empty($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : now()->endOfDay();

        $from = !empty($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : (clone $to)->subDays(29)->startOfDay();

        $scheduleType = $validated['schedule_type'] ?? null;
        $status = $validated['status'] ?? null;
        $driverId = $validated['driver_id'] ?? null;
        $vehicleId = $validated['vehicle_id'] ?? null;
        $routeId = $validated['route_id'] ?? null;
        $search = $validated['search'] ?? null;
        $format = $validated['format'] ?? 'csv';

        $query = Trip::query()
            ->with(['vehicle:id,registration_number', 'driver:id,name', 'department:id,name', 'vehicleRoute:id,name'])
            ->withCount('passengers')
            ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()]);

        if (!empty($scheduleType)) {
            $query->where('schedule_type', $scheduleType);
        }

        if (!empty($status)) {
            $query->where('status', $status);
        }

        if (!empty($driverId)) {
            $query->where('driver_id', $driverId);
        }

        if (!empty($vehicleId)) {
            $query->where('vehicle_id', $vehicleId);
        }

        if (!empty($routeId)) {
            $query->where('vehicle_route_id', $routeId);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('trip_number', 'like', "%{$search}%")
                    ->orWhere('start_location', 'like', "%{$search}%")
                    ->orWhere('end_location', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('driver', fn ($driverQuery) => $driverQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('registration_number', 'like', "%{$search}%"));
            });
        }

        $query->orderBy('scheduled_date');

        $timestamp = now()->format('Ymd_His');

        return match ($format) {
            'excel' => Excel::download(new TripsReportExport(clone $query), "trip-report-{$timestamp}.xlsx"),
            'pdf' => $this->exportPdf(clone $query, $from, $to, $timestamp),
            default => $this->exportCsv(clone $query, $timestamp),
        };
    }

    private function exportCsv($query, string $timestamp): StreamedResponse
    {
        $fileName = "trip-report-{$timestamp}.csv";

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Trip Number',
                'Date',
                'Status',
                'Schedule Type',
                'Route',
                'Vehicle',
                'Driver',
                'Department',
                'Passengers',
            ]);

            $query
                ->chunk(500, function ($trips) use ($handle) {
                    foreach ($trips as $trip) {
                        fputcsv($handle, [
                            $trip->trip_number,
                            $trip->scheduled_date,
                            $trip->status,
                            $trip->schedule_type,
                            $trip->vehicleRoute?->name,
                            $trip->vehicle?->registration_number,
                            $trip->driver?->name,
                            $trip->department?->name,
                            $trip->passengers_count,
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function exportPdf($query, Carbon $from, Carbon $to, string $timestamp)
    {
        $trips = $query->get();
        $pdf = Pdf::loadView('exports.trips-report-pdf', [
            'trips' => $trips,
            'exportDate' => now()->format('F j, Y g:i A'),
            'fromDate' => $from->toDateString(),
            'toDate' => $to->toDateString(),
            'totalRecords' => $trips->count(),
        ]);

        $pdf->setPaper('A4', 'landscape')
            ->setOptions([
                'defaultFont' => 'Arial',
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'chroot' => public_path(),
            ]);

        return $pdf->download("trip-report-{$timestamp}.pdf");
    }
}
