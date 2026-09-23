import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    AlertTriangle,
    BarChart3,
    Car,
    Route,
    TrendingUp,
} from 'lucide-react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Legend,
    Line,
    LineChart,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

interface ChartData {
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
}

interface DashboardChartsProps {
    chartData: ChartData;
}

export function DashboardCharts({ chartData }: DashboardChartsProps) {
    return (
        <>
            {/* Charts Section */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center">
                            <BarChart3 className="w-5 h-5 mr-2" />
                            Weekly Trip Statistics
                        </CardTitle>
                        <CardDescription>Trip completion trends over the week</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ResponsiveContainer width="100%" height={300}>
                            <BarChart data={chartData.weeklyTrips}>
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis dataKey="day" />
                                <YAxis />
                                <Tooltip />
                                <Legend />
                                <Bar dataKey="completed" fill="#10b981" name="Completed" />
                                <Bar dataKey="cancelled" fill="#ef4444" name="Cancelled" />
                            </BarChart>
                        </ResponsiveContainer>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center">
                            <Car className="w-5 h-5 mr-2" />
                            Vehicle Status Distribution
                        </CardTitle>
                        <CardDescription>Current status of all vehicles</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ResponsiveContainer width="100%" height={300}>
                            <PieChart>
                                <Pie
                                    data={chartData.vehicleStatus}
                                    cx="50%"
                                    cy="50%"
                                    labelLine={false}
                                    label={({ name, percent }) => `${name} ${((percent ?? 0) * 100).toFixed(0)}%`}
                                    outerRadius={80}
                                    fill="#8884d8"
                                    dataKey="value"
                                >
                                    {chartData.vehicleStatus.map((entry, index) => (
                                        <Cell key={`cell-${index}`} fill={entry.color} />
                                    ))}
                                </Pie>
                                <Tooltip />
                            </PieChart>
                        </ResponsiveContainer>
                    </CardContent>
                </Card>
            </div>

            {/* Performance Charts */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center">
                            <TrendingUp className="w-5 h-5 mr-2" />
                            Monthly Performance Trends
                        </CardTitle>
                        <CardDescription>Performance metrics over the last 6 months</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ResponsiveContainer width="100%" height={300}>
                            <LineChart data={chartData.monthlyPerformance}>
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis dataKey="month" />
                                <YAxis />
                                <Tooltip />
                                <Legend />
                                <Line type="monotone" dataKey="completionRate" stroke="#3b82f6" name="Completion %" strokeWidth={2} />
                                <Line type="monotone" dataKey="utilization" stroke="#10b981" name="Utilization %" strokeWidth={2} />
                            </LineChart>
                        </ResponsiveContainer>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center">
                            <AlertTriangle className="w-5 h-5 mr-2" />
                            Issue Categories (This Month)
                        </CardTitle>
                        <CardDescription>Breakdown of issues by category</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ResponsiveContainer width="100%" height={300}>
                            <BarChart data={chartData.issueCategories} layout="horizontal">
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis type="number" />
                                <YAxis dataKey="category" type="category" width={80} />
                                <Tooltip />
                                <Bar dataKey="count" fill="#ef4444" />
                            </BarChart>
                        </ResponsiveContainer>
                    </CardContent>
                </Card>
            </div>

            {/* Route Performance Chart */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center">
                        <Route className="w-5 h-5 mr-2" />
                        Route Performance Analysis
                    </CardTitle>
                    <CardDescription>Performance metrics for top 5 routes</CardDescription>
                </CardHeader>
                <CardContent>
                    <ResponsiveContainer width="100%" height={350}>
                        <AreaChart data={chartData.routePerformance}>
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis dataKey="route" />
                            <YAxis />
                            <Tooltip />
                            <Legend />
                            <Area type="monotone" dataKey="completionRate" stackId="1" stroke="#3b82f6" fill="#3b82f6" fillOpacity={0.6} name="Completion %" />
                            <Area type="monotone" dataKey="rating" stackId="2" stroke="#10b981" fill="#10b981" fillOpacity={0.6} name="Rating (x20)" />
                        </AreaChart>
                    </ResponsiveContainer>
                </CardContent>
            </Card>
        </>
    );
}
