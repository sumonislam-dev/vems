import { ServerSideDataTable } from '@/base-components/base-data-table';
import { PageHeader } from '@/base-components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem, DataTableColumn, TripFeedback, TripFeedbackPriority, TripFeedbackStatus } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Clock, FileText, Plus, Eye } from 'lucide-react';
import { useMemo } from 'react';

interface PaginatedItems {
    data: TripFeedback[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface ComplaintsIndexProps {
    items: PaginatedItems;
    stats: {
        total: number;
        open: number;
        in_review: number;
        resolved: number;
        closed: number;
    };
    categories: Record<string, string>;
    canViewAll: boolean;
    queryParams: {
        search?: string;
        type?: string;
        category?: string;
        status?: string;
        priority?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
        per_page?: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Feedback & Complaints', href: '/complaints' },
];

const getStatusBadge = (status: TripFeedbackStatus) => {
    const config = {
        open: { label: 'Open', className: 'border-yellow-200 bg-yellow-100 text-yellow-800 dark:border-yellow-500/30 dark:bg-yellow-500/15 dark:text-yellow-300' },
        in_review: { label: 'In Review', className: 'border-blue-200 bg-blue-100 text-blue-800 dark:border-blue-500/30 dark:bg-blue-500/15 dark:text-blue-300' },
        resolved: { label: 'Resolved', className: 'border-green-200 bg-green-100 text-green-800 dark:border-green-500/30 dark:bg-green-500/15 dark:text-green-300' },
        closed: { label: 'Closed', className: 'border-slate-200 bg-slate-100 text-slate-800 dark:border-slate-500/30 dark:bg-slate-500/15 dark:text-slate-300' },
    };
    const { label, className } = config[status] || config.open;
    return <Badge className={className}>{label}</Badge>;
};

const getPriorityBadge = (priority: TripFeedbackPriority) => {
    const config = {
        critical: { label: 'Critical', className: 'border-red-300 bg-red-100 text-red-800 dark:border-red-500/40 dark:bg-red-500/20 dark:text-red-300' },
        high: { label: 'High', className: 'border-orange-200 bg-orange-100 text-orange-800 dark:border-orange-500/30 dark:bg-orange-500/15 dark:text-orange-300' },
        medium: { label: 'Medium', className: 'border-blue-200 bg-blue-100 text-blue-800 dark:border-blue-500/30 dark:bg-blue-500/15 dark:text-blue-300' },
        low: { label: 'Low', className: 'border-slate-200 bg-slate-100 text-slate-800 dark:border-slate-500/30 dark:bg-slate-500/15 dark:text-slate-300' },
    };
    const { label, className } = config[priority] || config.low;
    return <Badge variant="outline" className={className}>{label}</Badge>;
};

export default function ComplaintsIndex({ items, stats, categories, canViewAll, queryParams }: ComplaintsIndexProps) {
    const pageProps = usePage().props as unknown as { auth?: { permissions?: string[] } };
    const permissions = pageProps.auth?.permissions ?? [];
    const canCreate = permissions.includes('create-complaints');

    const columns: DataTableColumn<TripFeedback>[] = useMemo(() => [
        {
            key: 'subject',
            label: 'Subject',
            sortable: true,
            render: (value, row) => (
                <div className="max-w-xs">
                    <p className="text-sm font-medium line-clamp-1">{value}</p>
                    {row.trip && (
                        <span className="text-xs text-gray-500">{row.trip.trip_number}</span>
                    )}
                </div>
            ),
        },
        {
            key: 'type',
            label: 'Type',
            render: (value) => (
                <Badge variant="outline" className="text-xs font-medium capitalize">{value}</Badge>
            ),
        },
        {
            key: 'category',
            label: 'Category',
            render: (value: string) => (
                <span className="text-sm capitalize">{categories[value] ?? value.replace('_', ' ')}</span>
            ),
        },
        ...(canViewAll ? [{
            key: 'submitted_by' as const,
            label: 'Submitted By',
            render: (_: unknown, row: TripFeedback) => (
                <span className="text-sm">{row.is_anonymous ? 'Anonymous' : row.submitter?.name ?? '-'}</span>
            ),
        }] : []),
        {
            key: 'priority',
            label: 'Priority',
            render: (value) => getPriorityBadge(value as TripFeedbackPriority),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (value) => getStatusBadge(value as TripFeedbackStatus),
        },
        {
            key: 'created_at',
            label: 'Submitted',
            sortable: true,
            render: (value) => <span className="text-sm text-gray-500">{new Date(value).toLocaleDateString()}</span>,
        },
        {
            key: 'id',
            label: 'Actions',
            render: (_, row) => (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => router.visit(route('complaints.show', row.id))}
                    className="cursor-pointer transition-all hover:scale-110 hover:bg-blue-50 hover:text-blue-600"
                >
                    <Eye className="h-4 w-4" />
                </Button>
            ),
        },
    ], [categories, canViewAll]);

    const filters = useMemo(() => [
        {
            key: 'type',
            label: 'Type',
            options: [
                { label: 'All', value: '' },
                { label: 'Feedback', value: 'feedback' },
                { label: 'Complaint', value: 'complaint' },
            ],
            type: 'select' as const,
        },
        {
            key: 'status',
            label: 'Status',
            options: [
                { label: 'All', value: '' },
                { label: 'Open', value: 'open' },
                { label: 'In Review', value: 'in_review' },
                { label: 'Resolved', value: 'resolved' },
                { label: 'Closed', value: 'closed' },
            ],
            type: 'select' as const,
        },
        {
            key: 'priority',
            label: 'Priority',
            options: [
                { label: 'All', value: '' },
                { label: 'Critical', value: 'critical' },
                { label: 'High', value: 'high' },
                { label: 'Medium', value: 'medium' },
                { label: 'Low', value: 'low' },
            ],
            type: 'select' as const,
        },
        {
            key: 'category',
            label: 'Category',
            options: [
                { label: 'All', value: '' },
                ...Object.entries(categories).map(([value, label]) => ({ label, value })),
            ],
            type: 'select' as const,
        },
    ], [categories]);

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Feedback & Complaints" />

            <div className="space-y-6">
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card className="p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-gray-600">Total</p>
                                <p className="text-2xl font-bold">{stats.total}</p>
                            </div>
                            <FileText className="h-8 w-8 text-gray-400" />
                        </div>
                    </Card>
                    <Card className="p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-gray-600">Open</p>
                                <p className="text-2xl font-bold text-yellow-600">{stats.open}</p>
                            </div>
                            <AlertTriangle className="h-8 w-8 text-yellow-400" />
                        </div>
                    </Card>
                    <Card className="p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-gray-600">In Review</p>
                                <p className="text-2xl font-bold text-blue-600">{stats.in_review}</p>
                            </div>
                            <Clock className="h-8 w-8 text-blue-400" />
                        </div>
                    </Card>
                    <Card className="p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-gray-600">Resolved</p>
                                <p className="text-2xl font-bold text-green-600">{stats.resolved}</p>
                            </div>
                            <CheckCircle2 className="h-8 w-8 text-green-400" />
                        </div>
                    </Card>
                </div>

                <PageHeader
                    title="Feedback & Complaints"
                    description={canViewAll ? 'Review and resolve feedback and complaints submitted about trips' : 'Feedback and complaints you have submitted'}
                    actions={canCreate ? [
                        {
                            label: 'Submit New',
                            icon: <Plus className="h-4 w-4" />,
                            href: route('complaints.create'),
                        },
                    ] : []}
                />

                <ServerSideDataTable
                    data={items}
                    columns={columns}
                    queryParams={queryParams}
                    filterOptions={{}}
                    filters={filters}
                    searchPlaceholder="Search by subject or trip number..."
                    exportable={false}
                    emptyMessage="No feedback or complaints found."
                    showSerialColumn
                />
            </div>
        </AppSidebarLayout>
    );
}
