import { BaseForm, FormField, FormSelect, FormTextarea } from '@/base-components/base-form';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Truck } from 'lucide-react';

/** The logistics form shared by pages/logistics/create.tsx and edit.tsx */

export type LogisticsForm = {
    name: string;
    department_id: string;
    description: string;
    status: string;
};

const STATUS_OPTIONS = [
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
];

interface LogisticsFormBodyProps {
    data: LogisticsForm;
    setData: <K extends keyof LogisticsForm>(field: K, value: LogisticsForm[K]) => void;
    errors: Partial<Record<keyof LogisticsForm, string>>;
    processing: boolean;
    onSubmit: (e: React.FormEvent) => void;
    departments: { id: number; name: string }[];
    description: string;
    submitLabel: string;
    processingLabel: string;
}

export function LogisticsFormBody({
    data,
    setData,
    errors,
    processing,
    onSubmit,
    departments,
    description,
    submitLabel,
    processingLabel,
}: LogisticsFormBodyProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Truck className="h-5 w-5" />
                    Logistics Information
                </CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent>
                <BaseForm onSubmit={onSubmit}>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="sm:col-span-2">
                            <FormField
                                label="Name"
                                name="name"
                                value={data.name}
                                onChange={(value) => setData('name', value)}
                                error={errors.name}
                                required
                                placeholder="e.g., Fleet Management"
                            />
                        </div>

                        <SearchableSelect
                            label="Department"
                            name="department_id"
                            value={data.department_id}
                            onChange={(value) => setData('department_id', value === 'none' ? '' : value)}
                            options={departments.map((dept) => ({ label: dept.name, value: dept.id.toString() }))}
                            error={errors.department_id}
                            placeholder="Search department..."
                            required
                        />

                        <FormSelect
                            label="Status"
                            name="status"
                            value={data.status}
                            onChange={(value) => setData('status', value)}
                            options={STATUS_OPTIONS}
                            error={errors.status}
                            required
                        />

                        <div className="sm:col-span-2 lg:col-span-4">
                            <FormTextarea
                                label="Description"
                                name="description"
                                value={data.description}
                                onChange={(value) => setData('description', value)}
                                error={errors.description}
                                placeholder="Brief description (optional)"
                                rows={2}
                            />
                        </div>
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" size="sm" onClick={() => window.history.back()} disabled={processing}>
                            Cancel
                        </Button>
                        <Button type="submit" size="sm" disabled={processing}>
                            {processing ? processingLabel : submitLabel}
                        </Button>
                    </div>
                </BaseForm>
            </CardContent>
        </Card>
    );
}
