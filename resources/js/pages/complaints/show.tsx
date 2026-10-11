import InputError from '@/components/input-error';
import { confirmAction } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem, TripFeedback, TripFeedbackPriority, TripFeedbackStatus } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, CheckCircle, RotateCcw, UserPlus, XCircle } from 'lucide-react';
import { FormEvent } from 'react';

interface ShowComplaintProps {
    item: TripFeedback;
    categories: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Feedback & Complaints', href: '/complaints' },
    { title: 'Details', href: '#' },
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

export default function ShowComplaint({ item, categories }: ShowComplaintProps) {
    const pageProps = usePage().props as unknown as { auth?: { user?: { id: number }; permissions?: string[] } };
    const permissions = pageProps.auth?.permissions ?? [];
    const currentUserId = pageProps.auth?.user?.id;
    const canAssign = permissions.includes('assign-complaints');
    const canResolve = permissions.includes('resolve-complaints');

    const { data, setData, post, processing, errors, reset } = useForm<{ resolution_notes: string }>({
        resolution_notes: '',
    });

    const submitResolve = (e: FormEvent) => {
        e.preventDefault();
        post(route('complaints.resolve', item.id), {
            onSuccess: () => reset(),
        });
    };

    const assignToMe = () => {
        if (!currentUserId) return;
        router.post(route('complaints.assign', item.id), { assigned_to: currentUserId });
    };

    const close = async () => {
        if (await confirmAction({ title: 'Close this complaint?', description: 'A closed complaint counts as finished in the reports.', confirmLabel: 'Close' })) {
            router.post(route('complaints.close', item.id));
        }
    };
    const reopen = async () => {
        if (await confirmAction({ title: 'Reopen this complaint?', confirmLabel: 'Reopen' })) {
            router.post(route('complaints.reopen', item.id));
        }
    };

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title={item.subject} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">{item.subject}</h1>
                        <p className="text-sm text-gray-500">
                            {item.trip ? `Trip ${item.trip.trip_number}` : 'Trip'} · Submitted {new Date(item.created_at).toLocaleString()}
                        </p>
                    </div>
                    <Button variant="outline" onClick={() => router.visit(route('complaints.index'))}>
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to List
                    </Button>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant="outline" className="capitalize">{item.type}</Badge>
                                    <span className="text-sm capitalize text-gray-500">{categories[item.category] ?? item.category}</span>
                                    {getPriorityBadge(item.priority)}
                                    {getStatusBadge(item.status)}
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <Label className="text-xs text-gray-600">Description</Label>
                                    <p className="mt-1 whitespace-pre-wrap text-sm text-gray-700">{item.description}</p>
                                </div>

                                {(item.driver_rating || item.vehicle_rating) && (
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        {item.driver_rating && (
                                            <div>
                                                <Label className="text-xs text-gray-600">Driver Rating</Label>
                                                <p className="mt-1 text-sm font-medium">{item.driver_rating} / 5</p>
                                            </div>
                                        )}
                                        {item.vehicle_rating && (
                                            <div>
                                                <Label className="text-xs text-gray-600">Vehicle Rating</Label>
                                                <p className="mt-1 text-sm font-medium">{item.vehicle_rating} / 5</p>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {item.resolution_notes && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Resolution</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="whitespace-pre-wrap text-sm text-gray-700">{item.resolution_notes}</p>
                                    {item.resolver && (
                                        <p className="mt-2 text-xs text-gray-500">
                                            Resolved by {item.resolver.name}{item.resolved_at ? ` on ${new Date(item.resolved_at).toLocaleString()}` : ''}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {canResolve && ['open', 'in_review'].includes(item.status) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Resolve</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <form className="space-y-4" onSubmit={submitResolve}>
                                        <div className="space-y-2">
                                            <Label htmlFor="resolution_notes">Resolution notes</Label>
                                            <Textarea
                                                id="resolution_notes"
                                                value={data.resolution_notes}
                                                onChange={(e) => setData('resolution_notes', e.target.value)}
                                                rows={4}
                                                placeholder="Describe how this was addressed..."
                                            />
                                            <InputError message={errors.resolution_notes} />
                                        </div>
                                        <Button type="submit" disabled={processing}>
                                            <CheckCircle className="mr-2 h-4 w-4" />
                                            Mark Resolved
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Details</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <Label className="text-xs text-gray-600">Submitted By</Label>
                                    <p className="mt-1 text-sm font-medium">{item.is_anonymous ? 'Anonymous' : item.submitter?.name ?? '-'}</p>
                                </div>
                                <Separator />
                                <div>
                                    <Label className="text-xs text-gray-600">Assigned To</Label>
                                    <p className="mt-1 text-sm font-medium">{item.assignee?.name ?? 'Unassigned'}</p>
                                </div>
                            </CardContent>
                        </Card>

                        {(canAssign || canResolve) && item.status !== 'closed' && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Actions</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    {canAssign && !item.assignee && (
                                        <Button variant="outline" className="w-full" onClick={assignToMe}>
                                            <UserPlus className="mr-2 h-4 w-4" />
                                            Assign to Me
                                        </Button>
                                    )}
                                    {canResolve && item.status === 'resolved' && (
                                        <Button className="w-full bg-green-600 hover:bg-green-700" onClick={close}>
                                            <CheckCircle className="mr-2 h-4 w-4" />
                                            Confirm & Close
                                        </Button>
                                    )}
                                    {canResolve && item.status === 'resolved' && (
                                        <Button variant="outline" className="w-full" onClick={reopen}>
                                            <RotateCcw className="mr-2 h-4 w-4" />
                                            Reopen
                                        </Button>
                                    )}
                                    {canResolve && ['open', 'in_review'].includes(item.status) && (
                                        <Button variant="outline" className="w-full" onClick={close}>
                                            <XCircle className="mr-2 h-4 w-4" />
                                            Close Without Resolving
                                        </Button>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppSidebarLayout>
    );
}
