import React from 'react';
import { Head } from '@inertiajs/react';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { PageHeader } from '@/base-components/page-header';
import { ServerSideDataTable } from '@/base-components/base-data-table';
import { DataTableColumn, ColumnFilter } from '@/types';
import { ActivityLogEntry } from '@/types/activity-log';
import { Badge } from '@/components/ui/badge';

interface Props {
    activities: {
        data: ActivityLogEntry[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filterOptions: {
        log_names: string[];
        events: string[];
        causers: Array<{ id: number; name: string }>;
    };
    queryParams: {
        search?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
        filters?: Record<string, string | string[]>;
        per_page?: number;
    };
}

const eventBadgeVariant = (event: string | null) => {
    if (event === 'created' || event === 'login') return 'default';
    if (event === 'deleted' || event === 'login_failed') return 'destructive';
    return 'secondary';
};

function ChangesSummary({ properties }: { properties: ActivityLogEntry['properties'] }) {
    const { old, new: next, other } = properties;

    if (old && next) {
        const keys = Array.from(new Set([...Object.keys(old), ...Object.keys(next)]));

        if (keys.length === 0) {
            return <span className="text-muted-foreground">-</span>;
        }

        return (
            <div className="space-y-1 text-xs">
                {keys.slice(0, 6).map((key) => (
                    <div key={key}>
                        <span className="font-medium">{key}:</span>{' '}
                        <span className="text-muted-foreground line-through">{formatValue(old[key])}</span>{' '}
                        <span>&rarr;</span> <span>{formatValue(next[key])}</span>
                    </div>
                ))}
                {keys.length > 6 && <div className="text-muted-foreground">+{keys.length - 6} more</div>}
            </div>
        );
    }

    if (next) {
        return (
            <div className="space-y-1 text-xs">
                {Object.entries(next).slice(0, 6).map(([key, value]) => (
                    <div key={key}>
                        <span className="font-medium">{key}:</span> <span>{formatValue(value)}</span>
                    </div>
                ))}
            </div>
        );
    }

    if (other) {
        return (
            <div className="space-y-1 text-xs">
                {Object.entries(other).map(([key, value]) => (
                    <div key={key}>
                        <span className="font-medium">{key}:</span> <span>{formatValue(value)}</span>
                    </div>
                ))}
            </div>
        );
    }

    return <span className="text-muted-foreground">-</span>;
}

function formatValue(value: unknown): string {
    if (value === null || value === undefined || value === '') return 'empty';
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
}

export default function ActivityLogsIndex({ activities, filterOptions, queryParams }: Props) {
    const columns: DataTableColumn<ActivityLogEntry>[] = [
        {
            key: 'created_at',
            label: 'When',
            sortable: true,
            render: (value) => new Date(value).toLocaleString(),
        },
        {
            key: 'log_name',
            label: 'Area',
            filterable: true,
            render: (value) => <Badge variant="outline">{value}</Badge>,
        },
        {
            key: 'event' as keyof ActivityLogEntry,
            label: 'Event',
            filterable: true,
            render: (value) => (value ? <Badge variant={eventBadgeVariant(value)}>{value.replace(/_/g, ' ')}</Badge> : '-'),
        },
        {
            key: 'subject_type' as keyof ActivityLogEntry,
            label: 'Subject',
            render: (_, activity) =>
                activity.subject_type ? (
                    <span>
                        {activity.subject_type}
                        {activity.subject_id ? ` #${activity.subject_id}` : ''}
                    </span>
                ) : (
                    <span className="text-muted-foreground">-</span>
                ),
        },
        {
            key: 'causer' as keyof ActivityLogEntry,
            label: 'By',
            render: (_, activity) => activity.causer?.name ?? <span className="text-muted-foreground">System</span>,
        },
        {
            key: 'description',
            label: 'Description',
        },
        {
            key: 'properties' as keyof ActivityLogEntry,
            label: 'Changes',
            render: (_, activity) => <ChangesSummary properties={activity.properties} />,
        },
    ];

    const filters: ColumnFilter[] = [
        {
            key: 'log_name',
            label: 'Area',
            type: 'multiselect',
            options: filterOptions.log_names.map((name) => ({ label: name, value: name })),
        },
        {
            key: 'event',
            label: 'Event',
            type: 'multiselect',
            options: filterOptions.events.map((event) => ({ label: event.replace(/_/g, ' '), value: event })),
        },
        {
            key: 'causer_id',
            label: 'User',
            type: 'multiselect',
            options: filterOptions.causers.map((causer) => ({ label: causer.name, value: causer.id })),
        },
    ];

    return (
        <AppSidebarLayout>
            <Head title="Activity Log" />

            <div className="space-y-6">
                <PageHeader title="Activity Log" description="Track who did what across the system" />

                <ServerSideDataTable
                    data={activities}
                    columns={columns}
                    queryParams={queryParams}
                    filterOptions={filterOptions}
                    filters={filters}
                    searchPlaceholder="Search description or model..."
                />
            </div>
        </AppSidebarLayout>
    );
}
