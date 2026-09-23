import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    AlertTriangle,
    BarChart3,
    Bell,
    Calendar,
    Car,
    MapPin,
    Package,
    Route,
    Shield,
    TrendingUp,
    Truck,
    Users,
} from 'lucide-react';

interface ModuleStats {
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
}

interface PerformanceMetrics {
    completion_rate: number;
    fuel_efficiency: number;
    customer_satisfaction: number;
    vehicle_utilization: number;
    driver_performance: number;
    maintenance_compliance: number;
}

interface RoleStats {
    total_roles: number;
    admins: number;
    employees: number;
    drivers: number;
}

interface DashboardModuleCardsProps {
    moduleStats: ModuleStats;
    performanceMetrics: PerformanceMetrics;
    roleStats: RoleStats;
}

export function DashboardModuleCards({ moduleStats, performanceMetrics, roleStats }: DashboardModuleCardsProps) {
    return (
        <>
            {/* Module Overview Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <Card className="hover:shadow-lg transition-shadow duration-300">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">🚗 Vehicle Management</CardTitle>
                        <Car className="h-4 w-4 text-blue-600" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-blue-600">{moduleStats.vehicles.total}</div>
                        <p className="text-xs text-muted-foreground">Total Vehicles</p>
                        <div className="mt-2 space-y-1">
                            <div className="flex justify-between text-xs">
                                <span>Active:</span>
                                <span className="font-medium text-green-600">{moduleStats.vehicles.active}</span>
                            </div>
                            <div className="flex justify-between text-xs">
                                <span>Available:</span>
                                <span className="font-medium">{moduleStats.vehicles.available}</span>
                            </div>
                            <div className="flex justify-between text-xs">
                                <span>Maintenance:</span>
                                <span className="font-medium text-orange-600">{moduleStats.vehicles.maintenance}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card className="hover:shadow-lg transition-shadow duration-300">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">👨‍✈️ Driver Management</CardTitle>
                        <Users className="h-4 w-4 text-green-600" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-green-600">{moduleStats.drivers.total}</div>
                        <p className="text-xs text-muted-foreground">Total Drivers</p>
                        <div className="mt-2 space-y-1">
                            <div className="flex justify-between text-xs">
                                <span>Active:</span>
                                <span className="font-medium text-green-600">{moduleStats.drivers.active}</span>
                            </div>
                            <div className="flex justify-between text-xs">
                                <span>On Trip:</span>
                                <span className="font-medium text-blue-600">{moduleStats.drivers.on_trip}</span>
                            </div>
                            <div className="flex justify-between text-xs">
                                <span>Available:</span>
                                <span className="font-medium">{moduleStats.drivers.available}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card className="hover:shadow-lg transition-shadow duration-300">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">🗺️ Route Management</CardTitle>
                        <Route className="h-4 w-4 text-purple-600" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-purple-600">{moduleStats.routes.total}</div>
                        <p className="text-xs text-muted-foreground">Total Routes</p>
                        <div className="mt-2 space-y-1">
                            <div className="flex justify-between text-xs">
                                <span>Active:</span>
                                <span className="font-medium text-green-600">{moduleStats.routes.active}</span>
                            </div>
                            <div className="flex justify-between text-xs">
                                <span>Today:</span>
                                <span className="font-medium text-blue-600">{moduleStats.routes.scheduled_today}</span>
                            </div>
                            <div className="flex justify-between text-xs">
                                <span>Factories:</span>
                                <span className="font-medium">{moduleStats.routes.factories}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card className="hover:shadow-lg transition-shadow duration-300">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">📍 Trip & Attendance</CardTitle>
                        <MapPin className="h-4 w-4 text-indigo-600" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-indigo-600">{moduleStats.trips.today}</div>
                        <p className="text-xs text-muted-foreground">Trips Today</p>
                        <div className="mt-2 space-y-1">
                            <div className="flex justify-between text-xs">
                                <span>Completed:</span>
                                <span className="font-medium text-green-600">{moduleStats.trips.completed}</span>
                            </div>
                            <div className="flex justify-between text-xs">
                                <span>Ongoing:</span>
                                <span className="font-medium text-blue-600">{moduleStats.trips.ongoing}</span>
                            </div>
                            <div className="flex justify-between text-xs">
                                <span>Distance:</span>
                                <span className="font-medium">{moduleStats.trips.total_distance} km</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Secondary Module Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <Card className="hover:shadow-lg transition-shadow duration-300">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">🗓️ Scheduling</CardTitle>
                        <Calendar className="h-4 w-4 text-teal-600" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-teal-600">{moduleStats.schedules.this_week}</div>
                        <p className="text-xs text-muted-foreground">This Week</p>
                        <div className="mt-2 space-y-1">
                            <div className="flex justify-between text-xs">
                                <span>Pick &amp; Drop:</span>
                                <span className="font-medium">{moduleStats.schedules.pick_drop}</span>
                            </div>
                            <div className="flex justify-between text-xs">
                                <span>Engineer:</span>
                                <span className="font-medium">{moduleStats.schedules.engineer}</span>
                            </div>
                            <div className="flex justify-between text-xs">
                                <span>Training:</span>
                                <span className="font-medium">{moduleStats.schedules.training}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card className="hover:shadow-lg transition-shadow duration-300">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">🛠️ Issues &amp; Complaints</CardTitle>
                        <AlertTriangle className="h-4 w-4 text-red-600" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-red-600">{moduleStats.issues.open}</div>
                        <p className="text-xs text-muted-foreground">Open Issues</p>
                        <div className="mt-2 space-y-1">
                            <div className="flex justify-between text-xs">
                                <span>In Progress:</span>
                                <span className="font-medium text-yellow-600">{moduleStats.issues.in_progress}</span>
                            </div>
                            <div className="flex justify-between text-xs">
                                <span>Resolved Today:</span>
                                <span className="font-medium text-green-600">{moduleStats.issues.resolved_today}</span>
                            </div>
                            <div className="flex justify-between text-xs">
                                <span>This Month:</span>
                                <span className="font-medium">{moduleStats.issues.total_this_month}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card className="hover:shadow-lg transition-shadow duration-300">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">🔔 Vehicle Alerts</CardTitle>
                        <Bell className="h-4 w-4 text-orange-600" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-orange-600">{moduleStats.vehicleAlerts.expiring_documents}</div>
                        <p className="text-xs text-muted-foreground">Documents Expiring/Expired</p>
                        <div className="mt-2 space-y-1">
                            <div className="flex justify-between text-xs">
                                <span>Active Vehicles:</span>
                                <span className="font-medium text-green-600">{moduleStats.vehicleAlerts.active_vehicles}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card className="hover:shadow-lg transition-shadow duration-300">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">📊 Analytics &amp; Reports</CardTitle>
                        <BarChart3 className="h-4 w-4 text-cyan-600" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-cyan-600">{performanceMetrics.completion_rate}%</div>
                        <p className="text-xs text-muted-foreground">Completion Rate (This Month)</p>
                        <div className="mt-2 space-y-1">
                            <div className="flex justify-between text-xs">
                                <span>Satisfaction:</span>
                                <span className="font-medium text-green-600">{performanceMetrics.customer_satisfaction}/5</span>
                            </div>
                            <div className="flex justify-between text-xs">
                                <span>Utilization:</span>
                                <span className="font-medium">{performanceMetrics.vehicle_utilization}%</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Additional Module Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <Card className="hover:shadow-lg transition-shadow duration-300">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">🧩 Destination &amp; Factory</CardTitle>
                        <Package className="h-4 w-4 text-emerald-600" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-emerald-600">{moduleStats.routes.factories}</div>
                        <p className="text-xs text-muted-foreground">Active Factories</p>
                        <div className="mt-2 space-y-1">
                            <div className="flex justify-between text-xs">
                                <span>Pickup Points:</span>
                                <span className="font-medium">{moduleStats.routes.pickup_points}</span>
                            </div>
                            <div className="flex justify-between text-xs">
                                <span>Routes:</span>
                                <span className="font-medium text-blue-600">{moduleStats.routes.total}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card className="hover:shadow-lg transition-shadow duration-300">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">📦 Logistics</CardTitle>
                        <Truck className="h-4 w-4 text-amber-600" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-amber-600">{moduleStats.trips.total_distance}</div>
                        <p className="text-xs text-muted-foreground">Total KM Today</p>
                        <div className="mt-2 space-y-1">
                            <div className="flex justify-between text-xs">
                                <span>Avg Duration:</span>
                                <span className="font-medium">{moduleStats.trips.avg_duration} min</span>
                            </div>
                            <div className="flex justify-between text-xs">
                                <span>Efficiency:</span>
                                <span className="font-medium text-green-600">{performanceMetrics.fuel_efficiency} km/l</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card className="hover:shadow-lg transition-shadow duration-300">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">🧑‍💼 Roles &amp; Permissions</CardTitle>
                        <Shield className="h-4 w-4 text-violet-600" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-violet-600">{roleStats.total_roles}</div>
                        <p className="text-xs text-muted-foreground">Active Roles</p>
                        <div className="mt-2 space-y-1">
                            <div className="flex justify-between text-xs">
                                <span>Admins:</span>
                                <span className="font-medium text-red-600">{roleStats.admins}</span>
                            </div>
                            <div className="flex justify-between text-xs">
                                <span>Drivers:</span>
                                <span className="font-medium text-blue-600">{roleStats.drivers}</span>
                            </div>
                            <div className="flex justify-between text-xs">
                                <span>Employees:</span>
                                <span className="font-medium">{roleStats.employees}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card className="hover:shadow-lg transition-shadow duration-300">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">📈 Performance Summary</CardTitle>
                        <TrendingUp className="h-4 w-4 text-pink-600" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-pink-600">{performanceMetrics.driver_performance}%</div>
                        <p className="text-xs text-muted-foreground">Driver Performance</p>
                        <div className="mt-2 space-y-1">
                            <div className="flex justify-between text-xs">
                                <span>Maintenance:</span>
                                <span className="font-medium text-green-600">{performanceMetrics.maintenance_compliance}%</span>
                            </div>
                            <div className="flex justify-between text-xs">
                                <span>Completion:</span>
                                <span className="font-medium text-blue-600">{performanceMetrics.completion_rate}%</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
