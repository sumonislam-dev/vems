import { PageHeader } from '@/base-components/page-header';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { FormEventHandler } from 'react';

interface Department {
    id: number;
    name: string;
}

interface Logistic {
    id: number;
    name: string;
    department_id: number;
    description: string;
    status: string;
}

interface EditLogisticsProps {
    logistic: Logistic;
    departments: Department[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Logistics', href: '/logistics' },
    { title: 'Edit', href: '#' },
];

export default function EditLogistics({ logistic, departments }: EditLogisticsProps) {
    const { data, setData, put, processing, errors } = useForm({
        name: logistic.name,
        department_id: logistic.department_id.toString(),
        description: logistic.description || '',
        status: logistic.status,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('logistics.update', logistic.id));
    };

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Logistics - ${logistic.name}`} />

            <div className="space-y-4">
                <PageHeader
                    title={`Edit Logistics - ${logistic.name}`}
                    description=""
                    actions={[
                        {
                            label: 'Back',
                            icon: <ArrowLeft className="h-4 w-4" />,
                            onClick: () => window.history.back(),
                            variant: 'outline',
                        },
                    ]}
                />

                <form onSubmit={submit}>
                    <Card>
                        <div className="p-4">
                            <div className="space-y-2.5">
                                <div>
                                    <h3 className="text-base font-semibold">Logistics Information</h3>
                                </div>

                                <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    {/* Name Field */}
                                    <div className="space-y-1">
                                        <Label htmlFor="name" className="text-xs font-medium">
                                            Name <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            id="name"
                                            type="text"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            placeholder="e.g., Fleet Management"
                                            className={`h-9 text-sm ${errors.name ? 'border-destructive' : ''}`}
                                        />
                                        {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                                    </div>

                                    {/* Department Field */}
                                    <div className="space-y-1">
                                        <Label htmlFor="department_id" className="text-xs font-medium">
                                            Department <span className="text-destructive">*</span>
                                        </Label>
                                        <Select value={data.department_id} onValueChange={(value) => setData('department_id', value)}>
                                            <SelectTrigger className={`h-9 text-sm ${errors.department_id ? 'border-destructive' : ''}`}>
                                                <SelectValue placeholder="Select department" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {departments.map((dept) => (
                                                    <SelectItem key={dept.id} value={dept.id.toString()} className="text-sm">
                                                        {dept.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.department_id && <p className="text-xs text-destructive">{errors.department_id}</p>}
                                    </div>

                                    {/* Description Field */}
                                    <div className="space-y-1">
                                        <Label htmlFor="description" className="text-xs font-medium">
                                            Description
                                        </Label>
                                        <Textarea
                                            id="description"
                                            value={data.description}
                                            onChange={(e) => setData('description', e.target.value)}
                                            placeholder="Brief description (optional)"
                                            rows={3}
                                            className={`text-sm ${errors.description ? 'border-destructive' : ''}`}
                                        />
                                        {errors.description && <p className="text-xs text-destructive">{errors.description}</p>}
                                    </div>

                                    {/* Status Field */}
                                    <div className="space-y-1">
                                        <Label className="text-xs font-medium">
                                            Status <span className="text-destructive">*</span>
                                        </Label>
                                        <div className="flex items-center gap-4 pt-1">
                                            <label className="flex cursor-pointer items-center gap-2">
                                                <input
                                                    type="radio"
                                                    value="active"
                                                    checked={data.status === 'active'}
                                                    onChange={(e) => setData('status', e.target.value)}
                                                    className="h-4 w-4 text-primary"
                                                />
                                                <span className="text-sm">Active</span>
                                            </label>
                                            <label className="flex cursor-pointer items-center gap-2">
                                                <input
                                                    type="radio"
                                                    value="inactive"
                                                    checked={data.status === 'inactive'}
                                                    onChange={(e) => setData('status', e.target.value)}
                                                    className="h-4 w-4 text-primary"
                                                />
                                                <span className="text-sm">Inactive</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                {/* Buttons */}
                                <div className="flex justify-end gap-2 pt-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => window.history.back()}
                                        disabled={processing}
                                        className="h-9"
                                        size="sm"
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={processing} className="h-9" size="sm">
                                        {processing ? 'Updating...' : 'Update Logistics'}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </Card>
                </form>
            </div>
        </AppSidebarLayout>
    );
}
