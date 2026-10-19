import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { cn } from '@/lib/utils';
import { BreadcrumbItem, SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { Download, FileSpreadsheet, Filter } from 'lucide-react';
import { useState } from 'react';

interface DayCell {
    text: string;
    trips: number;
    down: boolean;
}

interface CompareRow {
    key: string;
    label: string;
    current: number | null;
    previous: number | null;
}

interface MonthlyVehicleReportProps {
    report: {
        period: { from: string; to: string; previous_from: string; previous_to: string; label: string };
        days: { date: string; weekday: string; day: number }[];
        daily: { key: string; label: string; kind: 'purpose' | 'transport' | 'total'; values: number[]; total: number }[];
        purposes: { key: string; label: string; vehicle_days: number; trips: number; share: number | null }[];
        adhoc_bands: { label: string; working_days: number; share: number; adhoc_vehicle_days: number }[];
        key_numbers: CompareRow[];
        offices: { zone: string; trips: number; vehicle_days: number; adhoc_vehicle_days: number }[];
        service: CompareRow[];
        vendors: {
            vendor: string;
            trips: number;
            vehicle_days: number;
            breakdowns: number;
            replacements: number;
            complaints: number;
            complaints_open: number;
            issues_per_100: number;
        }[];
        details: {
            groups: {
                key: string;
                label: string;
                rows: {
                    sl: number;
                    vehicle_id: number;
                    driver: string | null;
                    phone: string | null;
                    vendor: string | null;
                    vehicle_type: string;
                    registration_number: string;
                    cells: (DayCell | null)[];
                    total_trips: number;
                    remarks: string | null;
                }[];
                used_per_day: number[];
            }[];
            fleet_size: number;
            assigned_per_day: number[];
            down_per_day: number[];
            idle_per_day: number[];
        };
    };
    filters: {
        from: string;
        to: string;
        zone_id: string | null;
        vendor_id: string | null;
        vehicle_id: string | null;
        rental_type: string | null;
    };
    filterOptions: {
        zones: { id: number; name: string }[];
        vendors: { id: number; name: string }[];
        vehicles: { id: number; registration_number: string }[];
        rental_types: string[];
    };
    maxDays: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: '/reports' },
    { title: 'Monthly Vehicle Report', href: '/reports/monthly-vehicle' },
];

const ALL = 'all';

const fmt = (value: number | null) => (value === null ? 'Not tracked' : value.toLocaleString());

const rentalLabel = (type: string) => (type === 'adhoc' ? 'Ad-hoc' : type.charAt(0).toUpperCase() + type.slice(1));

function CompareTable({ rows, period }: { rows: CompareRow[]; period: MonthlyVehicleReportProps['report']['period'] }) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Measure</TableHead>
                    <TableHead className="text-right">This period</TableHead>
                    <TableHead className="text-right" title={`${period.previous_from} to ${period.previous_to}`}>
                        Previous period
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row) => (
                    <TableRow key={row.key}>
                        <TableCell className={cn(row.current === null && 'text-muted-foreground')}>{row.label}</TableCell>
                        <TableCell className="text-right font-medium">{fmt(row.current)}</TableCell>
                        <TableCell className="text-right text-muted-foreground">{fmt(row.previous)}</TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

export default function MonthlyVehicleReport({ report, filters, filterOptions, maxDays }: MonthlyVehicleReportProps) {
    const { errors } = usePage<SharedData & { errors: Record<string, string> }>().props;
    const [tab, setTab] = useState<'summary' | 'details'>('summary');
    const [form, setForm] = useState({
        from: filters.from,
        to: filters.to,
        zone_id: filters.zone_id ?? ALL,
        vendor_id: filters.vendor_id ?? ALL,
        vehicle_id: filters.vehicle_id ?? ALL,
        rental_type: filters.rental_type ?? ALL,
    });

    const query = () => Object.fromEntries(Object.entries(form).filter(([, value]) => value && value !== ALL)) as Record<string, string>;

    const apply = () => router.get(route('reports.monthly-vehicle'), query(), { preserveState: true, preserveScroll: true });

    const exportAs = (format: 'excel' | 'pdf') => {
        const params = new URLSearchParams({ ...query(), format });
        window.location.href = `${route('reports.monthly-vehicle.export')}?${params.toString()}`;
    };

    const selectFilter = (key: keyof typeof form, placeholder: string, allLabel: string, options: { value: string; label: string }[]) => (
        <Select value={form[key]} onValueChange={(value) => setForm((prev) => ({ ...prev, [key]: value }))}>
            <SelectTrigger aria-label={placeholder}>
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={ALL}>{allLabel}</SelectItem>
                {options.map((o) => (
                    <SelectItem key={o.value} value={o.value}>
                        {o.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );

    const dayHeaders = (nowrap = false) =>
        report.days.map((d) => (
            <TableHead key={d.date} className={cn('min-w-10 px-1 text-center text-xs', nowrap && 'whitespace-nowrap')}>
                <div className="text-muted-foreground">{d.weekday.slice(0, 2)}</div>
                <div>{d.day}</div>
            </TableHead>
        ));

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Monthly Vehicle Report" />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold">
                            <FileSpreadsheet className="h-6 w-6" />
                            Monthly Vehicle Report
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            No. of vehicles for {report.period.label} · compared with {report.period.previous_from} to {report.period.previous_to}
                        </p>
                    </div>
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
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Filter className="h-4 w-4" />
                            Filters
                        </CardTitle>
                        <CardDescription>Up to {maxDays} days. Defaults to the current month.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                            <Input
                                type="date"
                                aria-label="From"
                                value={form.from}
                                onChange={(e) => setForm((p) => ({ ...p, from: e.target.value }))}
                            />
                            <Input type="date" aria-label="To" value={form.to} onChange={(e) => setForm((p) => ({ ...p, to: e.target.value }))} />
                            {selectFilter(
                                'zone_id',
                                'Office',
                                'All offices',
                                filterOptions.zones.map((z) => ({ value: String(z.id), label: z.name })),
                            )}
                            {selectFilter(
                                'vendor_id',
                                'Vendor',
                                'All vendors',
                                filterOptions.vendors.map((v) => ({ value: String(v.id), label: v.name })),
                            )}
                            {selectFilter(
                                'vehicle_id',
                                'Vehicle',
                                'All vehicles',
                                filterOptions.vehicles.map((v) => ({ value: String(v.id), label: v.registration_number })),
                            )}
                            {selectFilter(
                                'rental_type',
                                'Rental type',
                                'All rental types',
                                filterOptions.rental_types.map((t) => ({ value: t, label: rentalLabel(t) })),
                            )}
                        </div>
                        {(errors.from || errors.to) && <p className="text-sm text-destructive">{errors.from || errors.to}</p>}
                        <Button onClick={apply}>Apply</Button>
                    </CardContent>
                </Card>

                <div className="flex gap-2 border-b">
                    {(['summary', 'details'] as const).map((t) => (
                        <button
                            key={t}
                            type="button"
                            onClick={() => setTab(t)}
                            className={cn(
                                '-mb-px border-b-2 px-4 py-2 text-sm font-medium capitalize',
                                tab === t ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {t}
                        </button>
                    ))}
                </div>

                {tab === 'summary' ? (
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Daily vehicles by purpose</CardTitle>
                                <CardDescription>
                                    Purpose rows count distinct vehicles per day. Staff Pick &amp; Drop is shown separately. Total vehicles counts
                                    each vehicle once per day.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="sticky left-0 min-w-56 bg-background">Purpose</TableHead>
                                            {dayHeaders()}
                                            <TableHead className="text-right">Total</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {report.daily.map((row) => (
                                            <TableRow key={row.key} className={cn(row.kind === 'total' && 'bg-muted/50 font-semibold')}>
                                                <TableCell className="sticky left-0 bg-background">{row.label}</TableCell>
                                                {row.values.map((v, i) => (
                                                    <TableCell key={i} className={cn('px-1 text-center', !v && 'text-muted-foreground/40')}>
                                                        {v || '·'}
                                                    </TableCell>
                                                ))}
                                                <TableCell className="text-right">{row.total}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>

                        <div className="grid gap-6 lg:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Trips by purpose</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Purpose</TableHead>
                                                <TableHead className="text-right">Vehicle-days</TableHead>
                                                <TableHead className="text-right">Trips</TableHead>
                                                <TableHead className="text-right">Share</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {report.purposes.map((p) => (
                                                <TableRow key={p.key}>
                                                    <TableCell>{p.label}</TableCell>
                                                    <TableCell className="text-right">{p.vehicle_days}</TableCell>
                                                    <TableCell className="text-right">{p.trips}</TableCell>
                                                    <TableCell className="text-right">{p.share === null ? '-' : `${p.share}%`}</TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Key numbers</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <CompareTable rows={report.key_numbers} period={report.period} />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Ad-hoc vehicles needed in a day</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Ad-hoc vehicles</TableHead>
                                                <TableHead className="text-right">Working days</TableHead>
                                                <TableHead className="text-right">Share</TableHead>
                                                <TableHead className="text-right">Ad-hoc vehicle-days</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {report.adhoc_bands.map((b) => (
                                                <TableRow key={b.label}>
                                                    <TableCell>{b.label}</TableCell>
                                                    <TableCell className="text-right">{b.working_days}</TableCell>
                                                    <TableCell className="text-right">{b.share}%</TableCell>
                                                    <TableCell className="text-right">{b.adhoc_vehicle_days}</TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">By office</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Office</TableHead>
                                                <TableHead className="text-right">Trips</TableHead>
                                                <TableHead className="text-right">Vehicle-days</TableHead>
                                                <TableHead className="text-right">Ad-hoc vehicle-days</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {report.offices.length === 0 && (
                                                <TableRow>
                                                    <TableCell colSpan={4} className="text-center text-muted-foreground">
                                                        No trips in this period
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                            {report.offices.map((o) => (
                                                <TableRow key={o.zone}>
                                                    <TableCell>{o.zone}</TableCell>
                                                    <TableCell className="text-right">{o.trips}</TableCell>
                                                    <TableCell className="text-right">{o.vehicle_days}</TableCell>
                                                    <TableCell className="text-right">{o.adhoc_vehicle_days}</TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Service quality</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <CompareTable rows={report.service} period={report.period} />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Vendor scorecard</CardTitle>
                                    <CardDescription>Late arrivals are not tracked yet.</CardDescription>
                                </CardHeader>
                                <CardContent className="overflow-x-auto">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Vendor</TableHead>
                                                <TableHead className="text-right">Trips</TableHead>
                                                <TableHead className="text-right">Vehicle-days</TableHead>
                                                <TableHead className="text-right">Breakdowns</TableHead>
                                                <TableHead className="text-right">Replacements</TableHead>
                                                <TableHead className="text-right">Complaints</TableHead>
                                                <TableHead className="text-right">Still open</TableHead>
                                                <TableHead className="text-right">Per 100 trips</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {report.vendors.length === 0 && (
                                                <TableRow>
                                                    <TableCell colSpan={8} className="text-center text-muted-foreground">
                                                        No trips in this period
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                            {report.vendors.map((v) => (
                                                <TableRow key={v.vendor} className={cn(v.vendor === 'All vendors' && 'bg-muted/50 font-semibold')}>
                                                    <TableCell>{v.vendor}</TableCell>
                                                    <TableCell className="text-right">{v.trips}</TableCell>
                                                    <TableCell className="text-right">{v.vehicle_days}</TableCell>
                                                    <TableCell className="text-right">{v.breakdowns}</TableCell>
                                                    <TableCell className="text-right">{v.replacements}</TableCell>
                                                    <TableCell className="text-right">{v.complaints}</TableCell>
                                                    <TableCell className="text-right">{v.complaints_open}</TableCell>
                                                    <TableCell className="text-right">{v.issues_per_100}</TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Vehicle by day</CardTitle>
                            <CardDescription>
                                Each cell shows where the vehicle went, with the number of trips that day in brackets. Remarks shows the pick-and-drop
                                route.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <Table className="text-xs">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-8">Sl#</TableHead>
                                        <TableHead className="sticky left-0 min-w-40 bg-background">Vehicle / Driver</TableHead>
                                        <TableHead>Vendor</TableHead>
                                        {dayHeaders(true)}
                                        <TableHead className="text-right">Trips</TableHead>
                                        <TableHead>Remarks</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {report.details.groups.map((group) => (
                                        <GroupRows key={group.key} group={group} />
                                    ))}
                                    <TableRow className="bg-muted/50 font-semibold">
                                        <TableCell colSpan={3}># of vehicles assigned per day</TableCell>
                                        {report.details.assigned_per_day.map((n, i) => (
                                            <TableCell key={i} className="px-1 text-center">
                                                {n}
                                            </TableCell>
                                        ))}
                                        <TableCell colSpan={2} />
                                    </TableRow>
                                    <TableRow className="bg-muted/50 font-semibold">
                                        <TableCell colSpan={3}># of vehicles not available (downtime)</TableCell>
                                        {report.details.down_per_day.map((n, i) => (
                                            <TableCell key={i} className="px-1 text-center">
                                                {n}
                                            </TableCell>
                                        ))}
                                        <TableCell colSpan={2} />
                                    </TableRow>
                                    <TableRow className="bg-muted/50 font-semibold">
                                        <TableCell colSpan={3}># of vehicles remaining idle per day</TableCell>
                                        {report.details.idle_per_day.map((n, i) => (
                                            <TableCell key={i} className="px-1 text-center">
                                                {n}
                                            </TableCell>
                                        ))}
                                        <TableCell colSpan={2} />
                                    </TableRow>
                                </TableBody>
                            </Table>
                            <p className="mt-2 text-xs text-muted-foreground">
                                Fleet size {report.details.fleet_size}. Idle = available but not used; vehicles with recorded downtime are counted
                                separately.
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppSidebarLayout>
    );
}

function GroupRows({ group }: { group: MonthlyVehicleReportProps['report']['details']['groups'][number] }) {
    const span = group.used_per_day.length + 5;

    return (
        <>
            <TableRow className="bg-emerald-50 hover:bg-emerald-50 dark:bg-emerald-950/40 dark:hover:bg-emerald-950/40">
                <TableCell colSpan={span} className="font-semibold">
                    {group.label} <span className="font-normal text-muted-foreground">({group.rows.length})</span>
                </TableCell>
            </TableRow>
            {group.rows.map((v) => (
                <TableRow key={v.vehicle_id}>
                    <TableCell>{v.sl}</TableCell>
                    <TableCell className="sticky left-0 bg-background">
                        <div className="font-mono font-medium">{v.registration_number}</div>
                        <div className="text-muted-foreground">
                            {v.driver ?? 'No driver'}
                            {v.phone && ` · ${v.phone}`}
                        </div>
                        <Badge variant="outline" className="mt-0.5 text-[10px] capitalize">
                            {v.vehicle_type}
                        </Badge>
                    </TableCell>
                    <TableCell>{v.vendor ?? '-'}</TableCell>
                    {v.cells.map((cell, i) => (
                        <TableCell
                            key={i}
                            className={cn(
                                'min-w-24 px-1 align-top',
                                cell?.down && 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
                            )}
                            title={cell && !cell.down ? `${cell.trips} trip(s)` : undefined}
                        >
                            {cell && (
                                <>
                                    <span>{cell.text}</span>
                                    {cell.trips > 0 && <span className="text-muted-foreground"> ({cell.trips})</span>}
                                </>
                            )}
                        </TableCell>
                    ))}
                    <TableCell className="text-right font-medium">{v.total_trips}</TableCell>
                    <TableCell>{v.remarks ?? ''}</TableCell>
                </TableRow>
            ))}
            <TableRow className="text-muted-foreground">
                <TableCell colSpan={3} className="text-right">
                    Vehicles used
                </TableCell>
                {group.used_per_day.map((n, i) => (
                    <TableCell key={i} className="px-1 text-center">
                        {n}
                    </TableCell>
                ))}
                <TableCell colSpan={2} />
            </TableRow>
        </>
    );
}
