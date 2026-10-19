import { BaseForm, FormField, FormSelect, FormTextarea } from '@/base-components/base-form';
import { PageHeader } from '@/base-components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, MessageSquareWarning, Plus } from 'lucide-react';
import { FormEventHandler } from 'react';

type TripOption = {
    id: number;
    trip_number: string;
    scheduled_date: string;
    description?: string | null;
};

type ComplaintForm = {
    trip_id: string;
    type: string;
    category: string;
    subject: string;
    description: string;
    driver_rating: string;
    vehicle_rating: string;
    priority: string;
    is_anonymous: boolean;
};

interface CreateComplaintProps {
    trips: TripOption[];
    defaultTripId: number | null;
    categories: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Feedback & Complaints', href: '/complaints' },
    { title: 'Submit', href: '/complaints/create' },
];

const typeOptions = [
    { label: 'Feedback', value: 'feedback' },
    { label: 'Complaint', value: 'complaint' },
];

const priorityOptions = [
    { label: 'Low', value: 'low' },
    { label: 'Medium', value: 'medium' },
    { label: 'High', value: 'high' },
    { label: 'Critical', value: 'critical' },
];

const ratingOptions = [1, 2, 3, 4, 5].map((n) => ({ label: `${n} star${n > 1 ? 's' : ''}`, value: String(n) }));

export default function CreateComplaint({ trips, defaultTripId, categories }: CreateComplaintProps) {
    const { data, setData, post, processing, errors } = useForm<ComplaintForm>({
        trip_id: defaultTripId ? String(defaultTripId) : '',
        type: 'feedback',
        category: 'other',
        subject: '',
        description: '',
        driver_rating: '',
        vehicle_rating: '',
        priority: 'low',
        is_anonymous: false,
    });

    const tripOptions = trips.map((trip) => ({
        label: `${trip.trip_number} — ${trip.scheduled_date}${trip.description ? ` (${trip.description})` : ''}`,
        value: String(trip.id),
    }));

    const categoryOptions = Object.entries(categories).map(([value, label]) => ({ label, value }));

    const isComplaint = data.type === 'complaint';

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('complaints.store'));
    };

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Submit Feedback or Complaint" />

            <div className="space-y-6">
                <PageHeader
                    title="Submit Feedback or Complaint"
                    description="Let us know how a trip went, or report an issue that needs attention"
                    actions={[
                        {
                            label: 'Back to List',
                            icon: <ArrowLeft className="mr-2 h-4 w-4" />,
                            href: route('complaints.index'),
                            variant: 'outline',
                        },
                    ]}
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center">
                            <MessageSquareWarning className="mr-2 h-5 w-5" />
                            Details
                        </CardTitle>
                        <CardDescription>Select the trip this is about and describe what happened</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <BaseForm onSubmit={submit} processing={processing}>
                            <div className="grid gap-6 md:grid-cols-2">
                                <FormSelect
                                    label="Trip"
                                    name="trip_id"
                                    value={data.trip_id}
                                    onChange={(value) => setData('trip_id', value)}
                                    error={errors.trip_id}
                                    options={tripOptions}
                                    placeholder="Select the trip..."
                                    required
                                />

                                <FormSelect
                                    label="Type"
                                    name="type"
                                    value={data.type}
                                    onChange={(value) => setData('type', value)}
                                    error={errors.type}
                                    options={typeOptions}
                                    required
                                />

                                <FormSelect
                                    label="Category"
                                    name="category"
                                    value={data.category}
                                    onChange={(value) => setData('category', value)}
                                    error={errors.category}
                                    options={categoryOptions}
                                    required
                                />

                                {isComplaint && (
                                    <FormSelect
                                        label="Priority"
                                        name="priority"
                                        value={data.priority}
                                        onChange={(value) => setData('priority', value)}
                                        error={errors.priority}
                                        options={priorityOptions}
                                        description="How urgently does this need attention?"
                                    />
                                )}
                            </div>

                            <FormField
                                label="Subject"
                                name="subject"
                                value={data.subject}
                                onChange={(value) => setData('subject', value)}
                                error={errors.subject}
                                placeholder="Short summary"
                                required
                            />

                            <FormTextarea
                                label="Description"
                                name="description"
                                value={data.description}
                                onChange={(value) => setData('description', value)}
                                error={errors.description}
                                placeholder="Describe what happened in detail..."
                                rows={5}
                                required
                            />

                            {!isComplaint && (
                                <div className="grid gap-6 md:grid-cols-2">
                                    <FormSelect
                                        label="Driver Rating"
                                        name="driver_rating"
                                        value={data.driver_rating}
                                        onChange={(value) => setData('driver_rating', value)}
                                        error={errors.driver_rating}
                                        options={ratingOptions}
                                        placeholder="Not rated"
                                    />

                                    <FormSelect
                                        label="Vehicle Rating"
                                        name="vehicle_rating"
                                        value={data.vehicle_rating}
                                        onChange={(value) => setData('vehicle_rating', value)}
                                        error={errors.vehicle_rating}
                                        options={ratingOptions}
                                        placeholder="Not rated"
                                    />
                                </div>
                            )}

                            {isComplaint && (
                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="is_anonymous"
                                        checked={data.is_anonymous}
                                        onCheckedChange={(checked) => setData('is_anonymous', checked === true)}
                                    />
                                    <Label htmlFor="is_anonymous" className="text-sm font-normal">
                                        Keep my identity hidden from the driver
                                    </Label>
                                </div>
                            )}

                            <div className="flex justify-end space-x-4">
                                <Button type="button" variant="outline" onClick={() => window.history.back()} disabled={processing}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    {processing ? 'Submitting...' : isComplaint ? 'Submit Complaint' : 'Submit Feedback'}
                                </Button>
                            </div>
                        </BaseForm>
                    </CardContent>
                </Card>
            </div>
        </AppSidebarLayout>
    );
}
