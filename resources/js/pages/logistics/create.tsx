import { PageHeader } from '@/base-components/page-header';
import { LogisticsForm, LogisticsFormBody } from '@/components/logistics-form-body';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { FormEventHandler } from 'react';

interface CreateLogisticsProps {
    departments: { id: number; name: string }[];
    zones: { id: number; name: string }[];
    defaultZoneId: number | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Logistics', href: '/logistics' },
    { title: 'Create', href: '/logistics/create' },
];

export default function CreateLogistics({ departments, zones, defaultZoneId }: CreateLogisticsProps) {
    const { data, setData, post, processing, errors } = useForm<LogisticsForm>({
        name: '',
        department_id: '',
        description: '',
        status: 'active',
        zone_id: defaultZoneId?.toString() || '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('logistics.store'));
    };

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Logistics" />

            <div className="space-y-4">
                <PageHeader
                    title="Create Logistics"
                    description="Add a logistics team that trips can be linked to"
                    actions={[
                        {
                            label: 'Back to Logistics',
                            icon: <ArrowLeft className="mr-1 h-4 w-4" />,
                            href: route('logistics.index'),
                            variant: 'outline',
                        },
                    ]}
                />

                <LogisticsFormBody
                    data={data}
                    setData={setData}
                    errors={errors}
                    processing={processing}
                    onSubmit={submit}
                    departments={departments}
                    zones={zones}
                    description="Enter the logistics information below"
                    submitLabel="Create Logistics"
                    processingLabel="Creating..."
                />
            </div>
        </AppSidebarLayout>
    );
}
