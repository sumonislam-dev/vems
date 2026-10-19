import { AttendanceStatusCard } from '@/components/attendance-status-card';
import { DashboardCharts } from '@/components/dashboard-charts';
import { DashboardModuleCards } from '@/components/dashboard-module-cards';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { AttendanceStatusSummary, Factory } from '@/types/attendance';
import { Activity, AlertTriangle, Calendar, CheckCircle, Clock, MessageSquare, Truck, UserCheck, Users } from 'lucide-react';
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

export interface ManagementDashboardProps {
    attendanceStatus: AttendanceStatusSummary | null;
    factories: Factory[];
    stats: {
        total_users: number;
        total_vehicles: number;
        active_vehicles: number;
        total_vendors: number;
    };
    moduleStats: {
        vehicles: {
            total: number;
            active: number;
            maintenance: number;
            available: number;
            regular: number;
            adhoc: number;
        };
        drivers: {
            total: number;
            active: number;
            on_trip: number;
            available: number;
        };
        routes: {
            total: number;
            active: number;
            scheduled_today: number;
            pickup_points: number;
            factories: number;
        };
        trips: {
            today: number;
            completed: number;
            ongoing: number;
            total_distance: number;
            avg_duration: number;
        };
        schedules: {
            this_week: number;
            pick_drop: number;
            engineer: number;
            training: number;
            adhoc: number;
        };
        issues: {
            open: number;
            in_progress: number;
            resolved_today: number;
            total_this_month: number;
        };
        vehicleAlerts: {
            expiring_documents: number;
            active_vehicles: number;
        };
    };
    roleStats: {
        total_roles: number;
        admins: number;
        employees: number;
        drivers: number;
    };
    recentActivities: Array<{
        id: number;
        type: string;
        message: string;
        user: string;
        time: string;
        icon: string;
        color: string;
    }>;
    upcomingSchedules: Array<{
        id: number;
        route: string;
        driver: string;
        vehicle: string;
        time: string;
        type: string;
        passengers: number;
        status: string;
    }>;
    activeIssues: Array<{
        id: number;
        title: string;
        category: string;
        priority: string;
        status: string;
        reported_by: string;
        assigned_to: string;
        created_at: string;
    }>;
    performanceMetrics: {
        completion_rate: number;
        fuel_efficiency: number;
        customer_satisfaction: number;
        vehicle_utilization: number;
        driver_performance: number;
        maintenance_compliance: number;
    };
    chartData: {
        weeklyTrips: Array<{
            day: string;
            trips: number;
            completed: number;
            cancelled: number;
        }>;
        vehicleStatus: Array<{
            name: string;
            value: number;
            color: string;
        }>;
        monthlyPerformance: Array<{
            month: string;
            completionRate: number;
            satisfaction: number;
            utilization: number;
        }>;
        routePerformance: Array<{
            route: string;
            trips: number;
            completionRate: number;
            rating: number;
        }>;
        issueCategories: Array<{
            category: string;
            count: number;
            color: string;
        }>;
        driverPerformance: Array<{
            range: string;
            count: number;
            color: string;
        }>;
    };
}

export function ManagementDashboard({
    attendanceStatus,
    factories,
    moduleStats,
    roleStats,
    recentActivities,
    upcomingSchedules,
    activeIssues,
    performanceMetrics,
    chartData,
}: ManagementDashboardProps) {
    const getIconComponent = (iconName: string) => {
        const icons: Record<string, React.ComponentType<{ className?: string }>> = {
            'check-circle': CheckCircle,
            truck: Truck,
            'alert-triangle': AlertTriangle,
            'calendar-check': Calendar,
            'user-plus': UserCheck,
        };
        return icons[iconName] || Activity;
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'scheduled':
                return 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300';
            case 'in_progress':
                return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-500/15 dark:text-yellow-300';
            case 'pending_approval':
                return 'bg-orange-100 text-orange-800 dark:bg-orange-500/15 dark:text-orange-300';
            case 'completed':
                return 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300';
            default:
                return 'bg-slate-100 text-slate-800 dark:bg-slate-500/15 dark:text-slate-300';
        }
    };

    const getPriorityColor = (priority: string) => {
        switch (priority) {
            case 'high':
                return 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300';
            case 'medium':
                return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-500/15 dark:text-yellow-300';
            case 'low':
                return 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300';
            default:
                return 'bg-slate-100 text-slate-800 dark:bg-slate-500/15 dark:text-slate-300';
        }
    };

    return (
        <div className="space-y-6 p-6">
            {/* Attendance status — single always-visible widget, see docs/ATTENDANCE_SYSTEM_PLAN.md §11.1 */}
            {attendanceStatus && <AttendanceStatusCard status={attendanceStatus} factories={factories} />}

            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-3xl font-bold text-foreground">Status At A Glance</h1>
                </div>
                <div className="flex items-center space-x-4">
                    <Badge variant="outline" className="border-green-600 text-green-600">
                        <Activity className="mr-1 h-4 w-4" />
                        System Active
                    </Badge>
                </div>
            </div>

            {/* Module Overview Cards */}
            <DashboardModuleCards moduleStats={moduleStats} performanceMetrics={performanceMetrics} roleStats={roleStats} />

            {/* Charts Section */}
            <DashboardCharts chartData={chartData} />

            {/* Main Content Grid */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {/* Recent Activities */}
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle className="flex items-center">
                            <Activity className="mr-2 h-5 w-5" />
                            Recent Activities
                        </CardTitle>
                        <CardDescription>Latest system activities and updates</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {recentActivities.map((activity) => {
                                const IconComponent = getIconComponent(activity.icon);
                                return (
                                    <div
                                        key={activity.id}
                                        className="flex items-start space-x-3 rounded-lg p-3 transition-colors hover:bg-gray-50 dark:hover:bg-gray-800"
                                    >
                                        <div
                                            className={`rounded-full p-2 ${
                                                activity.color === 'green'
                                                    ? 'bg-green-100 text-green-600 dark:bg-green-500/15 dark:text-green-300'
                                                    : activity.color === 'blue'
                                                      ? 'bg-blue-100 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300'
                                                      : activity.color === 'orange'
                                                        ? 'bg-orange-100 text-orange-600 dark:bg-orange-500/15 dark:text-orange-300'
                                                        : activity.color === 'purple'
                                                          ? 'bg-violet-100 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300'
                                                          : 'bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300'
                                            }`}
                                        >
                                            <IconComponent className="h-4 w-4" />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-medium text-foreground">{activity.message}</p>
                                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                                by {activity.user} • {activity.time}
                                            </p>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>

                {/* Driver Performance Distribution */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center">
                            <Users className="mr-2 h-5 w-5" />
                            Driver Performance Distribution
                        </CardTitle>
                        <CardDescription>Performance rating distribution</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {chartData.driverPerformance.length > 0 ? (
                            <ResponsiveContainer width="100%" height={250}>
                                <PieChart>
                                    <Pie
                                        data={chartData.driverPerformance}
                                        cx="50%"
                                        cy="50%"
                                        innerRadius={40}
                                        outerRadius={80}
                                        paddingAngle={5}
                                        dataKey="count"
                                        label={({ range, count }) => `${range}: ${count}`}
                                    >
                                        {chartData.driverPerformance.map((entry, index) => (
                                            <Cell key={`cell-${index}`} fill={entry.color} />
                                        ))}
                                    </Pie>
                                    <Tooltip />
                                </PieChart>
                            </ResponsiveContainer>
                        ) : (
                            <div className="flex h-[250px] items-center justify-center text-sm text-muted-foreground">No rated drivers yet.</div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Upcoming Schedules & Active Issues */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {/* Upcoming Schedules */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center">
                            <Clock className="mr-2 h-5 w-5" />
                            Upcoming Schedules
                        </CardTitle>
                        <CardDescription>Next scheduled trips</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {upcomingSchedules.map((schedule) => (
                                <div key={schedule.id} className="rounded-lg border p-3 transition-shadow hover:shadow-sm">
                                    <div className="mb-2 flex items-center justify-between">
                                        <Badge className={getStatusColor(schedule.status)}>{schedule.status.replace('_', ' ')}</Badge>
                                        <span className="text-sm font-medium text-blue-600">{schedule.time}</span>
                                    </div>
                                    <h4 className="mb-1 text-sm font-medium text-foreground">{schedule.route}</h4>
                                    <div className="space-y-1 text-xs text-gray-500 dark:text-gray-400">
                                        <div className="flex justify-between">
                                            <span>Driver:</span>
                                            <span>{schedule.driver}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>Vehicle:</span>
                                            <span>{schedule.vehicle}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>Passengers:</span>
                                            <span>{schedule.passengers}</span>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Active Issues */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center">
                            <MessageSquare className="mr-2 h-5 w-5" />
                            Active Issues & Complaints
                        </CardTitle>
                        <CardDescription>Issues requiring attention</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {activeIssues.map((issue) => (
                                <div key={issue.id} className="rounded-lg border p-4 transition-shadow hover:shadow-sm">
                                    <div className="mb-2 flex items-center justify-between">
                                        <div className="flex items-center space-x-2">
                                            <Badge className={getPriorityColor(issue.priority)}>{issue.priority}</Badge>
                                            <Badge variant="outline">{issue.category}</Badge>
                                        </div>
                                        <Badge className={getStatusColor(issue.status)}>{issue.status.replace('_', ' ')}</Badge>
                                    </div>
                                    <h4 className="mb-2 font-medium text-foreground">{issue.title}</h4>
                                    <div className="space-y-1 text-sm text-gray-500 dark:text-gray-400">
                                        <div className="flex justify-between">
                                            <span>Reported by:</span>
                                            <span>{issue.reported_by}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>Assigned to:</span>
                                            <span>{issue.assigned_to}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span>Created:</span>
                                            <span>{issue.created_at}</span>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
