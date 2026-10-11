import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ArrowLeft, Pencil } from 'lucide-react';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { PageHeader } from '@/base-components/page-header';
import type { BreadcrumbItem } from '@/types';

interface LogisticDetails {
  id: number;
  name: string;
  description: string | null;
  status: 'active' | 'inactive';
  department: { id: number; name: string; code: string | null } | null;
  creator: { id: number; name: string } | null;
  created_at: string;
  updated_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Logistics', href: '/logistics' },
  { title: 'Details', href: '#' },
];

export default function ShowLogistics({ logistic }: { logistic: LogisticDetails }) {
  return (
    <AppSidebarLayout breadcrumbs={breadcrumbs}>
      <Head title={`${logistic.name} - Logistics`} />

      <div className="space-y-6">
        <PageHeader
          title={logistic.name}
          description="Logistics details"
          actions={[
            {
              label: 'Back to Logistics',
              icon: <ArrowLeft className="h-4 w-4" />,
              href: route('logistics.index'),
              variant: 'outline',
            },
            {
              label: 'Edit',
              icon: <Pencil className="h-4 w-4" />,
              href: route('logistics.edit', logistic.id),
              variant: 'outline',
              permission: 'edit-logistics',
            },
          ]}
        />

        <Card>
          <CardHeader>
            <CardTitle>Information</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div>
                <p className="text-sm font-medium text-muted-foreground">Name</p>
                <p className="text-lg font-semibold">{logistic.name}</p>
              </div>
              <div>
                <p className="text-sm font-medium text-muted-foreground">Status</p>
                <Badge variant={logistic.status === 'active' ? 'default' : 'secondary'}>{logistic.status}</Badge>
              </div>
              <div>
                <p className="text-sm font-medium text-muted-foreground">Department</p>
                <p className="text-lg">
                  {logistic.department ? `${logistic.department.name}${logistic.department.code ? ` (${logistic.department.code})` : ''}` : '—'}
                </p>
              </div>
              <div>
                <p className="text-sm font-medium text-muted-foreground">Created By</p>
                <p className="text-lg">{logistic.creator?.name || '—'}</p>
              </div>
              <div>
                <p className="text-sm font-medium text-muted-foreground">Created</p>
                <p className="text-base">{logistic.created_at}</p>
              </div>
              <div>
                <p className="text-sm font-medium text-muted-foreground">Last Updated</p>
                <p className="text-base">{logistic.updated_at}</p>
              </div>
              {logistic.description && (
                <div className="col-span-full">
                  <p className="text-sm font-medium text-muted-foreground">Description</p>
                  <p className="text-base whitespace-pre-line">{logistic.description}</p>
                </div>
              )}
            </div>
          </CardContent>
        </Card>
      </div>
    </AppSidebarLayout>
  );
}
