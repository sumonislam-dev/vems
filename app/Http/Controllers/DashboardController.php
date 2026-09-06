<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Factory;
use App\Models\TripPassenger;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('dashboard', [
            'attendanceStatus' => $request->user() ? $this->buildAttendanceStatus($request->user()) : null,
            'factories' => Factory::select('id', 'name')->orderBy('name')->get(),
            // Basic stats from existing models
            'stats' => [
                'total_users' => User::count(),
                'total_vehicles' => Vehicle::count(),
                'active_vehicles' => Vehicle::where('is_active', true)->count(),
                'total_vendors' => Vendor::count(),
            ],
            'recent_vehicles' => Vehicle::with(['driver', 'vendor'])
                ->latest()
                ->take(5)
                ->get(),
            'recent_users' => User::latest()
                ->take(5)
                ->get(),

            // Module Statistics (Dummy Data)
            'moduleStats' => [
                'vehicles' => [
                    'total' => 45,
                    'active' => 38,
                    'maintenance' => 4,
                    'available' => 22,
                    'regular' => 35,
                    'adhoc' => 10,
                ],
                'drivers' => [
                    'total' => 52,
                    'active' => 47,
                    'on_trip' => 28,
                    'available' => 19,
                    'temporary' => 5,
                ],
                'routes' => [
                    'total' => 18,
                    'active' => 15,
                    'scheduled_today' => 12,
                    'pickup_points' => 45,
                    'factories' => 8,
                ],
                'trips' => [
                    'today' => 24,
                    'completed' => 18,
                    'ongoing' => 6,
                    'total_distance' => 1247,
                    'avg_duration' => 45,
                ],
                'schedules' => [
                    'this_week' => 156,
                    'pick_drop' => 120,
                    'engineer' => 18,
                    'training' => 12,
                    'adhoc' => 6,
                ],
                'issues' => [
                    'open' => 8,
                    'in_progress' => 5,
                    'resolved_today' => 12,
                    'total_this_month' => 67,
                ],
                'notifications' => [
                    'pending' => 15,
                    'sent_today' => 89,
                    'reminders' => 23,
                ],
            ],

            // Recent Activities (Dummy Data)
            'recentActivities' => [
                [
                    'id' => 1,
                    'type' => 'trip_completed',
                    'message' => 'Trip RSC-001 completed successfully',
                    'user' => 'Driver Ahmed Hassan',
                    'time' => '5 minutes ago',
                    'icon' => 'check-circle',
                    'color' => 'green',
                ],
                [
                    'id' => 2,
                    'type' => 'vehicle_assigned',
                    'message' => 'Vehicle BUS-15 assigned to Route R-05',
                    'user' => 'Coordinator Sarah Khan',
                    'time' => '12 minutes ago',
                    'icon' => 'truck',
                    'color' => 'blue',
                ],
                [
                    'id' => 3,
                    'type' => 'issue_reported',
                    'message' => 'AC issue reported for Vehicle BUS-08',
                    'user' => 'Employee John Smith',
                    'time' => '25 minutes ago',
                    'icon' => 'alert-triangle',
                    'color' => 'orange',
                ],
                [
                    'id' => 4,
                    'type' => 'schedule_approved',
                    'message' => 'Weekly schedule approved for Route R-12',
                    'user' => 'Admin Manager',
                    'time' => '1 hour ago',
                    'icon' => 'calendar-check',
                    'color' => 'green',
                ],
                [
                    'id' => 5,
                    'type' => 'driver_assigned',
                    'message' => 'Temporary driver assigned to Route R-03',
                    'user' => 'Coordinator Mike Johnson',
                    'time' => '2 hours ago',
                    'icon' => 'user-plus',
                    'color' => 'purple',
                ],
            ],

            // Upcoming Schedules (Dummy Data)
            'upcomingSchedules' => [
                [
                    'id' => 1,
                    'route' => 'R-01: Factory A → Office Complex',
                    'driver' => 'Ahmed Hassan',
                    'vehicle' => 'BUS-12',
                    'time' => '08:30 AM',
                    'type' => 'pick-and-drop',
                    'passengers' => 28,
                    'status' => 'scheduled',
                ],
                [
                    'id' => 2,
                    'route' => 'R-05: Training Center → HQ',
                    'driver' => 'Sarah Khan',
                    'vehicle' => 'VAN-08',
                    'time' => '09:15 AM',
                    'type' => 'training',
                    'passengers' => 12,
                    'status' => 'in_progress',
                ],
                [
                    'id' => 3,
                    'route' => 'R-12: Site Visit → Factory B',
                    'driver' => 'Mike Johnson',
                    'vehicle' => 'CAR-05',
                    'time' => '10:00 AM',
                    'type' => 'engineer',
                    'passengers' => 4,
                    'status' => 'scheduled',
                ],
                [
                    'id' => 4,
                    'route' => 'R-08: Emergency Transport',
                    'driver' => 'Ali Rahman',
                    'vehicle' => 'VAN-12',
                    'time' => '11:30 AM',
                    'type' => 'adhoc',
                    'passengers' => 6,
                    'status' => 'pending_approval',
                ],
            ],

            // Active Issues (Dummy Data)
            'activeIssues' => [
                [
                    'id' => 1,
                    'title' => 'AC not working in BUS-08',
                    'category' => 'vehicle',
                    'priority' => 'high',
                    'status' => 'in_progress',
                    'reported_by' => 'John Smith',
                    'assigned_to' => 'Maintenance Team',
                    'created_at' => '2 hours ago',
                ],
                [
                    'id' => 2,
                    'title' => 'Driver late pickup complaint',
                    'category' => 'driver',
                    'priority' => 'medium',
                    'status' => 'open',
                    'reported_by' => 'Employee Group',
                    'assigned_to' => 'Coordinator Sarah',
                    'created_at' => '4 hours ago',
                ],
                [
                    'id' => 3,
                    'title' => 'Route R-15 needs optimization',
                    'category' => 'route',
                    'priority' => 'low',
                    'status' => 'open',
                    'reported_by' => 'Analytics System',
                    'assigned_to' => 'Route Manager',
                    'created_at' => '1 day ago',
                ],
            ],

            // Performance Metrics (Dummy Data)
            'performanceMetrics' => [
                'on_time_percentage' => 94.5,
                'fuel_efficiency' => 12.8,
                'customer_satisfaction' => 4.6,
                'vehicle_utilization' => 87.2,
                'driver_performance' => 91.3,
                'maintenance_compliance' => 96.8,
            ],

            // Chart Data (Dummy Data)
            'chartData' => [
                // Weekly Trip Statistics
                'weeklyTrips' => [
                    ['day' => 'Mon', 'trips' => 28, 'completed' => 26, 'cancelled' => 2],
                    ['day' => 'Tue', 'trips' => 32, 'completed' => 30, 'cancelled' => 2],
                    ['day' => 'Wed', 'trips' => 25, 'completed' => 24, 'cancelled' => 1],
                    ['day' => 'Thu', 'trips' => 35, 'completed' => 33, 'cancelled' => 2],
                    ['day' => 'Fri', 'trips' => 40, 'completed' => 38, 'cancelled' => 2],
                    ['day' => 'Sat', 'trips' => 22, 'completed' => 21, 'cancelled' => 1],
                    ['day' => 'Sun', 'trips' => 18, 'completed' => 17, 'cancelled' => 1],
                ],

                // Vehicle Status Distribution
                'vehicleStatus' => [
                    ['name' => 'Active', 'value' => 38, 'color' => '#10b981'],
                    ['name' => 'Available', 'value' => 22, 'color' => '#3b82f6'],
                    ['name' => 'Maintenance', 'value' => 4, 'color' => '#f59e0b'],
                    ['name' => 'Inactive', 'value' => 7, 'color' => '#ef4444'],
                ],

                // Monthly Performance Trends
                'monthlyPerformance' => [
                    ['month' => 'Jan', 'onTime' => 92.5, 'satisfaction' => 4.3, 'utilization' => 85.2],
                    ['month' => 'Feb', 'onTime' => 93.8, 'satisfaction' => 4.4, 'utilization' => 87.1],
                    ['month' => 'Mar', 'onTime' => 91.2, 'satisfaction' => 4.2, 'utilization' => 84.8],
                    ['month' => 'Apr', 'onTime' => 94.1, 'satisfaction' => 4.5, 'utilization' => 88.3],
                    ['month' => 'May', 'onTime' => 95.3, 'satisfaction' => 4.6, 'utilization' => 89.7],
                    ['month' => 'Jun', 'onTime' => 94.5, 'satisfaction' => 4.6, 'utilization' => 87.2],
                ],

                // Route Performance
                'routePerformance' => [
                    ['route' => 'R-01', 'trips' => 45, 'onTime' => 96.2, 'rating' => 4.8],
                    ['route' => 'R-02', 'trips' => 38, 'onTime' => 94.1, 'rating' => 4.6],
                    ['route' => 'R-03', 'trips' => 42, 'onTime' => 92.8, 'rating' => 4.4],
                    ['route' => 'R-04', 'trips' => 35, 'onTime' => 95.7, 'rating' => 4.7],
                    ['route' => 'R-05', 'trips' => 40, 'onTime' => 93.5, 'rating' => 4.5],
                ],

                // Issue Categories
                'issueCategories' => [
                    ['category' => 'Vehicle', 'count' => 12, 'color' => '#ef4444'],
                    ['category' => 'Driver', 'count' => 8, 'color' => '#f59e0b'],
                    ['category' => 'Route', 'count' => 5, 'color' => '#3b82f6'],
                    ['category' => 'Schedule', 'count' => 3, 'color' => '#8b5cf6'],
                ],

                // Driver Performance Distribution
                'driverPerformance' => [
                    ['range' => '90-100%', 'count' => 28, 'color' => '#10b981'],
                    ['range' => '80-89%', 'count' => 15, 'color' => '#3b82f6'],
                    ['range' => '70-79%', 'count' => 6, 'color' => '#f59e0b'],
                    ['range' => '60-69%', 'count' => 3, 'color' => '#ef4444'],
                ],
            ],
        ]);
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
}
