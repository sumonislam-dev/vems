<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Factory;
use App\Models\Stop;
use App\Models\Trip;
use App\Models\TripFeedback;
use App\Models\TripPassenger;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use App\Models\VehicleRoute;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    /**
     * Every management-dashboard widget except attendanceStatus (per-user)
     * and factories (cheap, already-real reference data) is fleet-wide, not
     * user-specific — so it's cached under one shared key rather than
     * recomputed (40+ queries) on every single admin's every page load.
     * A short TTL, not manual invalidation on every trip/vehicle/complaint
     * mutation, is the tradeoff: dashboard numbers can lag reality by up to
     * this long.
     */
    private const MANAGEMENT_DASHBOARD_CACHE_TTL_MINUTES = 5;

    public function index(Request $request): Response
    {
        $user = $request->user();

        // Drivers get their own variant: trips they're assigned via their
        // vehicle (see Trip::getDriverAttribute()/scopeVisibleTo()), not the
        // requester/passenger-oriented "my trips" list employees get.
        if ($user && $user->hasRole('driver')) {
            return Inertia::render('dashboard', [
                'variant' => 'driver',
                'attendanceStatus' => $this->buildAttendanceStatus($user),
                'factories' => Factory::select('id', 'name')->orderBy('name')->get(),
                'myTrips' => $this->buildDriverTrips($user),
                'myComplaints' => $this->buildMyComplaints($user),
                'license' => [
                    'status' => $user->license_status,
                    'expiry_date' => $user->license_expiry_date?->toDateString(),
                ],
                'driverStatus' => $user->driver_status,
            ]);
        }

        // Only roles with fleet-wide visibility (admin/super-admin, via the
        // same `view-trips` permission Trip::visibleTo()/TripFeedback::visibleTo()
        // gate on) get the management dashboard. Everyone else (employee)
        // gets a personal, self-service view of their own data.
        if ($user && ! $user->can('view-trips')) {
            return Inertia::render('dashboard', [
                'variant' => 'employee',
                'attendanceStatus' => $this->buildAttendanceStatus($user),
                'factories' => Factory::select('id', 'name')->orderBy('name')->get(),
                'myTrips' => $this->buildMyTrips($user),
                'myComplaints' => $this->buildMyComplaints($user),
            ]);
        }

        return Inertia::render('dashboard', array_merge(
            ['variant' => 'management'],
            $this->buildManagementDashboard($user)
        ));
    }

    protected function buildManagementDashboard(?User $user): array
    {
        $aggregates = Cache::remember(
            'dashboard.management.aggregates',
            now()->addMinutes(self::MANAGEMENT_DASHBOARD_CACHE_TTL_MINUTES),
            fn () => $this->buildManagementDashboardAggregates()
        );

        return array_merge($aggregates, [
            'attendanceStatus' => $user ? $this->buildAttendanceStatus($user) : null,
            'factories' => Factory::select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    protected function buildManagementDashboardAggregates(): array
    {
        return [
            'stats' => [
                'total_users' => User::count(),
                'total_vehicles' => Vehicle::count(),
                'active_vehicles' => Vehicle::where('is_active', true)->count(),
                'total_vendors' => Vendor::count(),
            ],
            'recent_vehicles' => Vehicle::with(['driver:id,name', 'vendor:id,name'])
                ->latest()
                ->take(5)
                ->get(),
            'recent_users' => User::latest()
                ->take(5)
                ->get(['id', 'name', 'username', 'user_type', 'status', 'image', 'photo', 'created_at']),
            'moduleStats' => $this->buildModuleStats(),
            'roleStats' => $this->buildRoleStats(),
            'recentActivities' => $this->buildRecentActivities(),
            'upcomingSchedules' => $this->buildUpcomingSchedules(),
            'activeIssues' => $this->buildActiveIssues(),
            'performanceMetrics' => $this->buildPerformanceMetrics(),
            'chartData' => [
                'weeklyTrips' => $this->buildWeeklyTripsChart(),
                'vehicleStatus' => $this->buildVehicleStatusChart(),
                'monthlyPerformance' => $this->buildMonthlyPerformanceChart(),
                'routePerformance' => $this->buildRoutePerformanceChart(),
                'issueCategories' => $this->buildIssueCategoriesChart(),
                'driverPerformance' => $this->buildDriverPerformanceChart(),
            ],
        ];
    }

    /**
     * §11.1: the single always-visible attendance widget's data — today's
     * status plus, when one applies, the trip pickup/drop-off context shown
     * inline. Read-only: no fan-out write happens here, only on the actual
     * Check In/Check Out button press (AttendanceController).
     */
    protected function buildAttendanceStatus(User $user): array
    {
        $record = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('work_date', today()->toDateString())
            ->first();

        $pendingTrip = TripPassenger::with(['trip:id,trip_number', 'pickupStop:id,name', 'dropoffStop:id,name'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'no_show', 'boarded'])
            ->whereHas('trip', fn ($q) => $q->where('status', 'in_progress'))
            ->orderByRaw("CASE WHEN status IN ('pending','no_show') THEN 0 ELSE 1 END")
            ->first();

        $trip = null;
        if ($pendingTrip) {
            $isPickup = in_array($pendingTrip->status, ['pending', 'no_show'], true);
            $trip = [
                'trip_number' => $pendingTrip->trip?->trip_number,
                'stage' => $isPickup ? 'pickup' : 'dropoff',
                'stop_name' => $isPickup ? $pendingTrip->pickupStop?->name : $pendingTrip->dropoffStop?->name,
            ];
        }

        return [
            'attendance_mode' => $user->attendanceMode(),
            'status' => $record?->status ?? 'not_checked_in',
            'check_in_at' => $record?->check_in_at?->toIso8601String(),
            'check_out_at' => $record?->check_out_at?->toIso8601String(),
            'break_minutes' => $record?->break_minutes ?? 0,
            'net_minutes' => $record?->net_minutes,
            'overtime_minutes' => $record?->overtime_minutes ?? 0,
            'trip' => $trip,
        ];
    }

    /**
     * Employee dashboard: counts + a short upcoming list, scoped via the same
     * Trip::visibleTo() used by TripController (requester, passenger, or driver).
     */
    protected function buildMyTrips(User $user): array
    {
        $base = Trip::visibleTo($user);

        $upcoming = (clone $base)
            ->whereIn('status', ['pending', 'approved', 'assigned', 'in_progress'])
            ->orderBy('scheduled_date')
            ->take(5)
            ->get(['id', 'trip_number', 'scheduled_date', 'schedule_type', 'status']);

        // One grouped query instead of three separate counts — same pattern
        // TripFeedbackController::index() uses for its status breakdown.
        $counts = (clone $base)->selectRaw(
            "SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending, ".
            "SUM(CASE WHEN status IN ('approved', 'assigned') THEN 1 ELSE 0 END) as upcoming, ".
            "SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress"
        )->first();

        return [
            'counts' => [
                'pending' => (int) $counts->pending,
                'upcoming' => (int) $counts->upcoming,
                'in_progress' => (int) $counts->in_progress,
            ],
            'upcoming' => $upcoming,
        ];
    }

    /**
     * Driver dashboard: trips assigned to this driver through their vehicle
     * (Trip::getDriverAttribute() reads $trip->vehicle->driver_id, not
     * trips.driver_id — see TripStateController::authorizeStartOrComplete()
     * for the same rule), split into today's schedule vs. what's further out.
     */
    protected function buildDriverTrips(User $user): array
    {
        $base = Trip::whereHas('vehicle', fn ($q) => $q->where('driver_id', $user->id));

        $today = (clone $base)
            ->whereDate('scheduled_date', today())
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->orderBy('scheduled_start_time')
            ->get(['id', 'trip_number', 'scheduled_date', 'scheduled_start_time', 'schedule_type', 'status']);

        $upcoming = (clone $base)
            ->whereDate('scheduled_date', '>', today())
            ->whereIn('status', ['approved', 'assigned'])
            ->orderBy('scheduled_date')
            ->take(5)
            ->get(['id', 'trip_number', 'scheduled_date', 'scheduled_start_time', 'schedule_type', 'status']);

        return [
            'counts' => [
                'today' => $today->count(),
                'in_progress' => (clone $base)->where('status', 'in_progress')->count(),
                'completed_today' => (clone $base)->whereDate('scheduled_date', today())->where('status', 'completed')->count(),
            ],
            'today_trips' => $today,
            'upcoming' => $upcoming,
        ];
    }

    /**
     * Employee dashboard: counts + recent list, scoped via the same
     * TripFeedback::visibleTo() used by TripFeedbackController.
     */
    protected function buildMyComplaints(User $user): array
    {
        $base = TripFeedback::visibleTo($user);

        $recent = (clone $base)
            ->latest()
            ->take(3)
            ->get(['id', 'subject', 'type', 'status', 'priority', 'created_at']);

        // One grouped query instead of two separate counts — same pattern
        // TripFeedbackController::index() uses for its status breakdown.
        $counts = (clone $base)->selectRaw(
            "SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open, ".
            'COUNT(*) as total'
        )->first();

        return [
            'counts' => [
                'open' => (int) $counts->open,
                'total' => (int) $counts->total,
            ],
            'recent' => $recent,
        ];
    }

    /**
     * Management dashboard module cards. Every number here is a real query —
     * there is no "notifications" module because no notification feature
     * exists in this app; it's replaced with a vehicle-document-expiry alert
     * count (Vehicle::getExpiringDocuments(), the same check VehicleExpiryTest
     * exercises), computed in PHP rather than the model's own
     * scopeWithExpiringDocuments() because that scope's DATEDIFF() SQL is
     * MySQL-only and would break under the SQLite test database.
     */
    protected function buildModuleStats(): array
    {
        $today = today();
        $weekStart = today()->startOfWeek()->toDateString();
        $weekEnd = today()->endOfWeek()->toDateString();

        $vehicleCounts = Vehicle::selectRaw(
            'COUNT(*) as total, '.
            'SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active, '.
            "SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance, ".
            "SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available, ".
            "SUM(CASE WHEN rental_type = 'adhoc' THEN 1 ELSE 0 END) as adhoc"
        )->first();

        $driverCounts = User::drivers()->selectRaw(
            'COUNT(*) as total, '.
            "SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active, ".
            "SUM(CASE WHEN driver_status = 'on_trip' THEN 1 ELSE 0 END) as on_trip, ".
            "SUM(CASE WHEN driver_status = 'available' THEN 1 ELSE 0 END) as available"
        )->first();

        $activeRoutes = Trip::where('scheduled_date', '>=', now()->subDays(30)->toDateString())
            ->whereNotNull('vehicle_route_id')
            ->distinct('vehicle_route_id')
            ->count('vehicle_route_id');

        $scheduledTodayRoutes = Trip::whereDate('scheduled_date', $today)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->whereNotNull('vehicle_route_id')
            ->distinct('vehicle_route_id')
            ->count('vehicle_route_id');

        $tripsTodayCounts = Trip::whereDate('scheduled_date', $today)->selectRaw(
            'COUNT(*) as today, '.
            "SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed, ".
            "SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as ongoing"
        )->first();

        $completedToday = Trip::whereDate('scheduled_date', $today)
            ->where('status', 'completed')
            ->get(['distance_traveled', 'start_time', 'end_time']);
        $durationsToday = $completedToday
            ->filter(fn ($trip) => $trip->start_time && $trip->end_time)
            ->map(fn ($trip) => $trip->start_time->diffInMinutes($trip->end_time));

        $scheduleCounts = Trip::whereBetween('scheduled_date', [$weekStart, $weekEnd])->selectRaw(
            'COUNT(*) as this_week, '.
            "SUM(CASE WHEN schedule_type IN ('pick-and-drop', 'pick-up', 'drop-off') THEN 1 ELSE 0 END) as pick_drop, ".
            "SUM(CASE WHEN schedule_type = 'engineer' THEN 1 ELSE 0 END) as engineer, ".
            "SUM(CASE WHEN schedule_type = 'training' THEN 1 ELSE 0 END) as training, ".
            "SUM(CASE WHEN schedule_type = 'adhoc' THEN 1 ELSE 0 END) as adhoc"
        )->first();

        $issueCounts = TripFeedback::selectRaw(
            "SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open, ".
            "SUM(CASE WHEN status = 'in_review' THEN 1 ELSE 0 END) as in_progress, ".
            "SUM(CASE WHEN status = 'resolved' AND DATE(resolved_at) = ? THEN 1 ELSE 0 END) as resolved_today, ".
            'SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as total_this_month',
            [$today->toDateString(), now()->startOfMonth()->toDateTimeString()]
        )->first();

        $expiringVehicleCount = $this->countVehiclesWithExpiringDocuments();

        return [
            'vehicles' => [
                'total' => (int) $vehicleCounts->total,
                'active' => (int) $vehicleCounts->active,
                'maintenance' => (int) $vehicleCounts->maintenance,
                'available' => (int) $vehicleCounts->available,
                'regular' => (int) $vehicleCounts->total - (int) $vehicleCounts->adhoc,
                'adhoc' => (int) $vehicleCounts->adhoc,
            ],
            'drivers' => [
                'total' => (int) $driverCounts->total,
                'active' => (int) $driverCounts->active,
                'on_trip' => (int) $driverCounts->on_trip,
                'available' => (int) $driverCounts->available,
            ],
            'routes' => [
                'total' => VehicleRoute::count(),
                'active' => $activeRoutes,
                'scheduled_today' => $scheduledTodayRoutes,
                'pickup_points' => Stop::count(),
                'factories' => Factory::count(),
            ],
            'trips' => [
                'today' => (int) $tripsTodayCounts->today,
                'completed' => (int) $tripsTodayCounts->completed,
                'ongoing' => (int) $tripsTodayCounts->ongoing,
                'total_distance' => round((float) $completedToday->sum('distance_traveled'), 1),
                'avg_duration' => $durationsToday->isNotEmpty() ? (int) round($durationsToday->avg()) : 0,
            ],
            'schedules' => [
                'this_week' => (int) $scheduleCounts->this_week,
                'pick_drop' => (int) $scheduleCounts->pick_drop,
                'engineer' => (int) $scheduleCounts->engineer,
                'training' => (int) $scheduleCounts->training,
                'adhoc' => (int) $scheduleCounts->adhoc,
            ],
            'issues' => [
                'open' => (int) $issueCounts->open,
                'in_progress' => (int) $issueCounts->in_progress,
                'resolved_today' => (int) $issueCounts->resolved_today,
                'total_this_month' => (int) $issueCounts->total_this_month,
            ],
            'vehicleAlerts' => [
                'expiring_documents' => $expiringVehicleCount,
                'active_vehicles' => (int) $vehicleCounts->active,
            ],
        ];
    }

    /**
     * Real role distribution (Spatie's Role::users() morph relation) rather
     * than the hardcoded "4 roles / 2 admins / 8 coordinators / 156 employees"
     * this card used to render regardless of props.
     */
    protected function buildRoleStats(): array
    {
        $counts = Role::withCount('users')->get()->keyBy('name');

        return [
            'total_roles' => $counts->count(),
            'admins' => ($counts['super-admin']->users_count ?? 0) + ($counts['admin']->users_count ?? 0),
            'employees' => $counts['employee']->users_count ?? 0,
            'drivers' => $counts['driver']->users_count ?? 0,
        ];
    }

    /**
     * Merges three real event sources (completed trips, submitted feedback,
     * driver-vehicle assignments) into one feed, newest first.
     */
    protected function buildRecentActivities(): array
    {
        $completedTrips = Trip::where('status', 'completed')
            ->whereNotNull('end_time')
            ->with('vehicle.driver:id,name')
            ->latest('end_time')
            ->take(5)
            ->get()
            ->map(fn ($trip) => [
                'type' => 'trip_completed',
                'message' => "Trip {$trip->trip_number} completed",
                'user' => $trip->vehicle?->driver?->name ?? 'Unassigned driver',
                'timestamp' => $trip->end_time,
                'icon' => 'check-circle',
                'color' => 'green',
            ]);

        $feedback = TripFeedback::with('submitter:id,name')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($feedback) => [
                'type' => $feedback->type === 'complaint' ? 'issue_reported' : 'feedback_submitted',
                'message' => ($feedback->type === 'complaint' ? 'Complaint' : 'Feedback').": {$feedback->subject}",
                'user' => $feedback->is_anonymous ? 'Anonymous' : ($feedback->submitter?->name ?? 'Unknown'),
                'timestamp' => $feedback->created_at,
                'icon' => 'alert-triangle',
                'color' => 'orange',
            ]);

        $assignments = VehicleDriverAssignment::where('is_current', true)
            ->with(['driver:id,name', 'vehicle:id,registration_number'])
            ->latest('started_at')
            ->take(5)
            ->get()
            ->map(fn ($assignment) => [
                'type' => 'driver_assigned',
                'message' => "Driver {$assignment->driver?->name} assigned to vehicle {$assignment->vehicle?->registration_number}",
                'user' => $assignment->driver?->name ?? 'Unknown',
                'timestamp' => $assignment->started_at,
                'icon' => 'user-plus',
                'color' => 'purple',
            ]);

        return $completedTrips->concat($feedback)->concat($assignments)
            ->filter(fn ($activity) => $activity['timestamp'] !== null)
            ->sortByDesc('timestamp')
            ->take(6)
            ->values()
            ->map(fn ($activity, $index) => [
                'id' => $index + 1,
                'type' => $activity['type'],
                'message' => $activity['message'],
                'user' => $activity['user'],
                'time' => $activity['timestamp']->diffForHumans(),
                'icon' => $activity['icon'],
                'color' => $activity['color'],
            ])
            ->all();
    }

    protected function buildUpcomingSchedules(): array
    {
        return Trip::whereIn('status', ['pending', 'approved', 'assigned', 'in_progress'])
            ->whereDate('scheduled_date', '>=', today())
            ->with(['vehicle.driver:id,name', 'vehicleRoute:id,name'])
            ->withCount('passengers')
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_start_time')
            ->take(6)
            ->get()
            ->map(fn ($trip) => [
                'id' => $trip->id,
                'route' => $trip->vehicleRoute?->name ?? $trip->trip_number,
                'driver' => $trip->vehicle?->driver?->name ?? 'Unassigned',
                'vehicle' => $trip->vehicle?->registration_number ?? 'Unassigned',
                'time' => $trip->scheduled_start_time
                    ? Carbon::createFromFormat('H:i:s', $trip->scheduled_start_time)->format('h:i A')
                    : '—',
                'type' => $trip->schedule_type,
                'passengers' => $trip->passengers_count,
                'status' => $trip->status,
            ])
            ->all();
    }

    protected function buildActiveIssues(): array
    {
        return TripFeedback::whereIn('status', ['open', 'in_review'])
            ->with(['submitter:id,name', 'assignee:id,name'])
            ->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->latest()
            ->take(6)
            ->get()
            ->map(fn ($feedback) => [
                'id' => $feedback->id,
                'title' => $feedback->subject,
                'category' => $feedback->category,
                'priority' => $feedback->priority,
                'status' => $feedback->status,
                'reported_by' => $feedback->is_anonymous ? 'Anonymous' : ($feedback->submitter?->name ?? 'Unknown'),
                'assigned_to' => $feedback->assignee?->name ?? 'Unassigned',
                'created_at' => $feedback->created_at->diffForHumans(),
            ])
            ->all();
    }

    /**
     * Note: there's no "on-time" tracking anywhere in this app (no column
     * compares scheduled vs. actual start) — 'completion_rate' (completed vs.
     * completed+cancelled, same formula ReportController uses) replaces the
     * old fabricated 'on_time_percentage'.
     */
    protected function buildPerformanceMetrics(): array
    {
        $monthStart = now()->startOfMonth();

        [$completionRate] = $this->completionRateFor($monthStart->toDateString(), now()->toDateString());

        // Total distance / total fuel across all trips, not an average of each
        // trip's own ratio — averaging per-trip ratios lets a single trip with
        // a near-zero fuel_consumed value dominate and blow up the result.
        $fuelStats = Trip::where('scheduled_date', '>=', $monthStart->toDateString())
            ->where('status', 'completed')
            ->whereNotNull('fuel_consumed')
            ->where('fuel_consumed', '>', 0)
            ->selectRaw('SUM(distance_traveled) as total_distance, SUM(fuel_consumed) as total_fuel')
            ->first();
        $totalFuelConsumed = (float) $fuelStats->total_fuel;
        $fuelEfficiency = $totalFuelConsumed > 0
            ? round(((float) $fuelStats->total_distance) / $totalFuelConsumed, 1)
            : 0.0;

        $customerSatisfaction = $this->averageFeedbackRating($monthStart, now());

        $activeVehicleCount = Vehicle::where('is_active', true)->count();
        $vehiclesUsedThisWeek = Trip::whereBetween('scheduled_date', [today()->startOfWeek()->toDateString(), today()->endOfWeek()->toDateString()])
            ->whereNotNull('vehicle_id')
            ->distinct('vehicle_id')
            ->count('vehicle_id');
        $vehicleUtilization = $activeVehicleCount > 0 ? round($vehiclesUsedThisWeek / $activeVehicleCount * 100, 1) : 0.0;

        $ratedDrivers = User::drivers()->where('average_rating', '>', 0)->pluck('average_rating');
        $driverPerformance = $ratedDrivers->isNotEmpty() ? round(((float) $ratedDrivers->avg()) / 5 * 100, 1) : 0.0;

        $expiringVehicleCount = $this->countVehiclesWithExpiringDocuments();
        $maintenanceCompliance = $activeVehicleCount > 0
            ? round(($activeVehicleCount - $expiringVehicleCount) / $activeVehicleCount * 100, 1)
            : 100.0;

        return [
            'completion_rate' => $completionRate,
            'fuel_efficiency' => $fuelEfficiency,
            'customer_satisfaction' => $customerSatisfaction,
            'vehicle_utilization' => $vehicleUtilization,
            'driver_performance' => $driverPerformance,
            'maintenance_compliance' => $maintenanceCompliance,
        ];
    }

    protected function buildWeeklyTripsChart(): array
    {
        $start = today()->subDays(6);

        $rows = Trip::whereBetween('scheduled_date', [$start->toDateString(), today()->toDateString()])
            ->selectRaw(
                'scheduled_date, COUNT(*) as trips, '.
                "SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed, ".
                "SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled"
            )
            ->groupBy('scheduled_date')
            ->get()
            ->keyBy(fn ($row) => $row->scheduled_date->toDateString());

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $start->copy()->addDays($i);
            $row = $rows->get($date->toDateString());
            $days[] = [
                'day' => $date->format('D'),
                'trips' => (int) ($row->trips ?? 0),
                'completed' => (int) ($row->completed ?? 0),
                'cancelled' => (int) ($row->cancelled ?? 0),
            ];
        }

        return $days;
    }

    protected function buildVehicleStatusChart(): array
    {
        $colors = [
            'available' => '#3b82f6',
            'assigned' => '#10b981',
            'in_transit' => '#06b6d4',
            'maintenance' => '#f59e0b',
            'out_of_service' => '#ef4444',
        ];

        return Vehicle::selectRaw('status, COUNT(*) as value')
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'name' => ucwords(str_replace('_', ' ', $row->status)),
                'value' => (int) $row->value,
                'color' => $colors[$row->status] ?? '#94a3b8',
            ])
            ->all();
    }

    protected function buildMonthlyPerformanceChart(): array
    {
        $activeVehicleCount = Vehicle::where('is_active', true)->count();
        $months = [];

        for ($i = 5; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth();
            $monthEnd = now()->subMonths($i)->endOfMonth();

            [$completionRate] = $this->completionRateFor($monthStart->toDateString(), $monthEnd->toDateString());
            $satisfaction = $this->averageFeedbackRating($monthStart, $monthEnd);

            $vehiclesUsed = Trip::whereBetween('scheduled_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->whereNotNull('vehicle_id')
                ->distinct('vehicle_id')
                ->count('vehicle_id');
            $utilization = $activeVehicleCount > 0 ? round($vehiclesUsed / $activeVehicleCount * 100, 1) : 0.0;

            $months[] = [
                'month' => $monthStart->format('M'),
                'completionRate' => $completionRate,
                'satisfaction' => $satisfaction,
                'utilization' => $utilization,
            ];
        }

        return $months;
    }

    protected function buildRoutePerformanceChart(): array
    {
        $topRoutes = Trip::whereNotNull('vehicle_route_id')
            ->selectRaw('vehicle_route_id, COUNT(*) as trips_count')
            ->groupBy('vehicle_route_id')
            ->orderByDesc('trips_count')
            ->take(5)
            ->get();

        $routeNames = VehicleRoute::whereIn('id', $topRoutes->pluck('vehicle_route_id'))->pluck('name', 'id');

        return $topRoutes->map(function ($row) use ($routeNames) {
            [$completionRate] = $this->completionRateFor(null, null, $row->vehicle_route_id);
            $rating = $this->averageFeedbackRating(null, null, $row->vehicle_route_id);

            return [
                'route' => $routeNames[$row->vehicle_route_id] ?? 'Unknown Route',
                'trips' => (int) $row->trips_count,
                'completionRate' => $completionRate,
                'rating' => $rating,
            ];
        })->values()->all();
    }

    protected function buildIssueCategoriesChart(): array
    {
        $colors = [
            'driver_behavior' => '#f59e0b',
            'vehicle_condition' => '#ef4444',
            'punctuality' => '#3b82f6',
            'safety' => '#dc2626',
            'route' => '#8b5cf6',
            'other' => '#94a3b8',
        ];

        $counts = TripFeedback::selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category');

        return collect(TripFeedbackController::CATEGORIES)
            ->map(fn ($label, $key) => [
                'category' => $label,
                'count' => (int) ($counts[$key] ?? 0),
                'color' => $colors[$key] ?? '#94a3b8',
            ])
            ->filter(fn ($row) => $row['count'] > 0)
            ->values()
            ->all();
    }

    /**
     * Buckets User::average_rating (a real, 1-5 field updated on trip
     * completion, see User::updateDriverStats()) into the same
     * percentage-style ranges the old dummy chart used. Drivers who've never
     * been rated (average_rating = 0) are excluded rather than shown as 0%,
     * since "never rated" and "rated poorly" aren't the same thing.
     */
    protected function buildDriverPerformanceChart(): array
    {
        $ratings = User::drivers()->where('average_rating', '>', 0)->pluck('average_rating');

        $buckets = [
            '90-100%' => 0,
            '80-89%' => 0,
            '70-79%' => 0,
            '60-69%' => 0,
            'Below 60%' => 0,
        ];

        foreach ($ratings as $rating) {
            $percent = ((float) $rating / 5) * 100;
            $bucket = match (true) {
                $percent >= 90 => '90-100%',
                $percent >= 80 => '80-89%',
                $percent >= 70 => '70-79%',
                $percent >= 60 => '60-69%',
                default => 'Below 60%',
            };
            $buckets[$bucket]++;
        }

        $colors = [
            '90-100%' => '#10b981',
            '80-89%' => '#3b82f6',
            '70-79%' => '#f59e0b',
            '60-69%' => '#ef4444',
            'Below 60%' => '#94a3b8',
        ];

        return collect($buckets)
            ->map(fn ($count, $range) => ['range' => $range, 'count' => $count, 'color' => $colors[$range]])
            ->filter(fn ($row) => $row['count'] > 0)
            ->values()
            ->all();
    }

    /**
     * Shared completed-vs-cancelled ratio, optionally scoped to a date range
     * and/or a route — same formula ReportController uses for completion/
     * cancellation rate. Returns [rate] (array so call sites can destructure
     * without an intermediate variable).
     */
    protected function completionRateFor(?string $from, ?string $to, ?int $vehicleRouteId = null): array
    {
        $query = Trip::query();

        if ($from && $to) {
            $query->whereBetween('scheduled_date', [$from, $to]);
        }

        if ($vehicleRouteId) {
            $query->where('vehicle_route_id', $vehicleRouteId);
        }

        $counts = $query->selectRaw(
            "SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed, ".
            "SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled"
        )->first();

        $completed = (int) $counts->completed;
        $cancelled = (int) $counts->cancelled;
        $total = $completed + $cancelled;

        return [$total > 0 ? round($completed / $total * 100, 1) : 0.0];
    }

    /**
     * Average of driver_rating/vehicle_rating across feedback in a date
     * range and/or for a specific route (via the feedback's trip).
     */
    protected function averageFeedbackRating(?Carbon $from = null, ?Carbon $to = null, ?int $vehicleRouteId = null): float
    {
        $query = TripFeedback::query();

        if ($from && $to) {
            $query->whereBetween('created_at', [$from, $to]);
        }

        if ($vehicleRouteId) {
            $query->whereHas('trip', fn ($q) => $q->where('vehicle_route_id', $vehicleRouteId));
        }

        /** @var Collection $ratings */
        $ratings = $query->get(['driver_rating', 'vehicle_rating'])
            ->flatMap(fn ($feedback) => array_filter([$feedback->driver_rating, $feedback->vehicle_rating]));

        return $ratings->isNotEmpty() ? round((float) $ratings->avg(), 1) : 0.0;
    }

    /**
     * Counts active vehicles with an expiring/expired tracked document, in
     * PHP via Vehicle::getExpiringDocuments() rather than the model's
     * scopeWithExpiringDocuments() — that scope's raw DATEDIFF() is
     * MySQL-only and errors under the SQLite test database.
     */
    protected function countVehiclesWithExpiringDocuments(): int
    {
        return Vehicle::where('is_active', true)
            ->get([
                'tax_token_last_date', 'tax_token_alert_enabled',
                'fitness_certificate_last_date', 'fitness_alert_enabled',
                'insurance_last_date', 'insurance_alert_enabled',
                'alert_days_before',
            ])
            ->filter(fn ($vehicle) => ! empty($vehicle->getExpiringDocuments()))
            ->count();
    }
}
