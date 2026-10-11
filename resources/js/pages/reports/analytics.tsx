import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { useCan } from '@/lib/permissions';
import { cn } from '@/lib/utils';
import { BreadcrumbItem, SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { BarChart3, Download, Filter } from 'lucide-react';
import { useState } from 'react';

type CellValue = string | number | null;

interface ReportColumn {
    key: string;
    label: string;
    align: 'left' | 'right';
}

interface ReportSection {
    key: string;
    title: string;
    description?: string;
    columns: ReportColumn[];
    rows: (Record<string, CellValue> & { _tone?: 'danger' | 'warning' | null })[];
}

interface FilterDefinition {
    key: string;
    label: string;
    all: string | null;
    options: { value: string; label: string }[];
}

interface AnalyticsReportProps {
    slug: string;
    title: string;
    description: string;
    usesDateRange: boolean;
    report: {
        summary: { label: string; value: CellValue; hint?: string }[];
        sections: ReportSection[];
    };
    filters: Record<string, string | null>;
    filterOptions: {
        zones: { id: number; name: string }[];
        extra: FilterDefinition[];
    };
}

const ALL = 'all';

const formatCell = (value: CellValue | undefined) => {
    if (value === null || value === undefined || value === '') return '—';
    return typeof value === 'number' ? value.toLocaleString() : value;
};

const TONES = {
    danger: 'bg-red-50 dark:bg-red-500/10',
    warning: 'bg-amber-50 dark:bg-amber-500/10',
};

export default function AnalyticsReport({ slug, title, description, usesDateRange, report, filters, filterOptions }: AnalyticsReportProps) {
    const can = useCan();
    const { errors } = usePage<SharedData & { errors: Record<string, string> }>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Reports', href: '/reports' },
        { title, href: route('reports.analytics', slug) },
    ];

    // Dropdowns without an "all" option (e.g. the compliance window) start on their first option
    const [form, setForm] = useState<Record<string, string>>(() => {
        const initial: Record<string, string> = { from: filters.from ?? '', to: filters.to ?? '', zone_id: filters.zone_id ?? ALL };
        filterOptions.extra.forEach((f) => {
            initial[f.key] = filters[f.key] ?? (f.all === null ? (f.options[0]?.value ?? '') : ALL);
        });
        return initial;
    });

    const query = () => {
        const entries = Object.entries(form).filter(([key, value]) => value && value !== ALL && (usesDateRange || (key !== 'from' && key !== 'to')));
        return Object.fromEntries(entries) as Record<string, string>;
    };

    const apply = () => router.get(route('reports.analytics', slug), query(), { preserveState: true, preserveScroll: true });

    const exportAs = (format: 'excel' | 'pdf') => {
        const params = new URLSearchParams({ ...query(), format });
        window.location.href = `${route('reports.analytics.export', slug)}?${params.toString()}`;
    };

    const select = (key: string, label: string, all: string | null, options: { value: string; label: string }[]) => (
        <Select key={key} value={form[key]} onValueChange={(value) => setForm((prev) => ({ ...prev, [key]: value }))}>
            <SelectTrigger aria-label={label}>
                <SelectValue placeholder={label} />
            </SelectTrigger>
            <SelectContent>
                {all !== null && <SelectItem value={ALL}>{all}</SelectItem>}
                {options.map((o) => (
                    <SelectItem key={o.value} value={o.value}>
                        {o.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title={title} />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold">
                            <BarChart3 className="h-6 w-6" />
                            {title}
                        </h1>
                        <p className="text-sm text-muted-foreground">{description}</p>
                    </div>
                    {can('export-reports') && (
                        <div className="flex gap-2">
                            <Button variant="outline" onClick={() => exportAs('excel')}>
                                <Download className="mr-2 h-4 w-4" />
                                Excel
                            </Button>
                            <Button variant="outline" onClick={() => exportAs('pdf')}>
                                <Download className="mr-2 h-4 w-4" />
                                PDF
                            </Button>
                        </div>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Filter className="h-4 w-4" />
                            Filters
                        </CardTitle>
                        <CardDescription>{usesDateRange ? 'Defaults to the current month.' : 'As of today.'}</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                            {usesDateRange && (
                                <>
                                    <Input type="date" aria-label="From" value={form.from} onChange={(e) => setForm((p) => ({ ...p, from: e.target.value }))} />
                                    <Input type="date" aria-label="To" value={form.to} onChange={(e) => setForm((p) => ({ ...p, to: e.target.value }))} />
                                </>
                            )}
                            {select('zone_id', 'Office', 'All offices', filterOptions.zones.map((z) => ({ value: String(z.id), label: z.name })))}
                            {filterOptions.extra.map((f) => select(f.key, f.label, f.all, f.options))}
                        </div>
                        {(errors.from || errors.to) && <p className="text-sm text-destructive">{errors.from || errors.to}</p>}
                        <Button onClick={apply}>Apply</Button>
                    </CardContent>
                </Card>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {report.summary.map((item) => (
                        <Card key={item.label}>
                            <CardContent className="p-4">
                                <p className="text-sm text-muted-foreground">{item.label}</p>
                                <p className="mt-1 text-2xl font-semibold">{formatCell(item.value)}</p>
                                {item.hint && <p className="text-xs text-muted-foreground">{item.hint}</p>}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {report.sections.map((section) => (
                    <Card key={section.key}>
                        <CardHeader>
                            <CardTitle className="text-base">{section.title}</CardTitle>
                            {section.description && <CardDescription>{section.description}</CardDescription>}
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        {section.columns.map((column) => (
                                            <TableHead key={column.key} className={cn('whitespace-nowrap', column.align === 'right' && 'text-right')}>
                                                {column.label}
                                            </TableHead>
                                        ))}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {section.rows.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={section.columns.length} className="py-6 text-center text-muted-foreground">
                                                Nothing to show for these filters.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        section.rows.map((row, index) => (
                                            <TableRow key={index} className={cn(row._tone && TONES[row._tone])}>
                                                {section.columns.map((column) => (
                                                    <TableCell key={column.key} className={cn(column.align === 'right' && 'text-right tabular-nums')}>
                                                        {formatCell(row[column.key])}
                                                    </TableCell>
                                                ))}
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AppSidebarLayout>
    );
}
