import { PageHeader } from '@/base-components/page-header';
import { LogisticsForm, LogisticsFormBody } from '@/components/logistics-form-body';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { FormEventHandler } from 'react';

interface Logistic {
    id: number;
    name: string;
    department_id: number;
    description: string;
    status: string;
}

interface EditLogisticsProps {
    logistic: Logistic;
    departments: { id: number; name: string }[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Logistics', href: '/logistics' },
    { title: 'Edit', href: '#' },
];

export default function EditLogistics({ logistic, departments }: EditLogisticsProps) {
    const { data, setData, put, processing, errors } = useForm<LogisticsForm>({
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
                    description="Update the logistics team"
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
                    description="Update the logistics information below"
                    submitLabel="Update Logistics"
                    processingLabel="Updating..."
                />
            </div>
        </AppSidebarLayout>
    );
}
