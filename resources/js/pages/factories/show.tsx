import { Head, router } from '@inertiajs/react';
import { confirmDelete } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, Building2, MapPin, Edit, Trash2, Navigation } from 'lucide-react';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { PageHeader } from '@/base-components/page-header';
import { useCan } from '@/lib/permissions';
import { BreadcrumbItem } from '@/types';

interface Factory {
    id: number;
    account_id: string;
    name: string;
    status: 'active' | 'inactive';
    address: string | null;
    city: string | null;
    latitude: number | null;
    longitude: number | null;
    mileage_km: number | null;
    created_at: string;
    updated_at: string;
}

interface ShowFactoryProps {
    factory: Factory;
}

export default function ShowFactory({ factory }: ShowFactoryProps) {
    const can = useCan();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Factories', href: '/factories' },
        { title: factory.name, href: `/factories/${factory.id}` },
    ];

    const handleDelete = async () => {
        if (await confirmDelete(`Are you sure you want to delete ${factory.name}?`)) {
            router.delete(route('factories.destroy', factory.id));
        }
    };

    const openInMaps = () => {
        if (factory.latitude && factory.longitude) {
            window.open(`https://www.google.com/maps?q=${factory.latitude},${factory.longitude}`, '_blank');
        }
    };

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title={factory.name} />

            <div className="space-y-6">
                <PageHeader
                    title={factory.name}
                    description={`Account ID: ${factory.account_id}`}
                    actions={[
                        {
                            label: 'Back to Factories',
                            icon: <ArrowLeft className="mr-2 h-4 w-4" />,
                            href: route('factories.index'),
                            variant: 'outline',
                        },
                        {
                            label: 'Edit',
                            icon: <Edit className="mr-2 h-4 w-4" />,
                            href: route('factories.edit', factory.id),
                            permission: 'edit-factories',
                        },
                    ]}
                />

                <div className="grid gap-6 md:grid-cols-2">
                    {/* Basic Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center">
                                <Building2 className="mr-2 h-5 w-5" />
                                Basic Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">Account ID</label>
                                <p className="text-lg font-mono">{factory.account_id}</p>
                            </div>

                            <div>
                                <label className="text-sm font-medium text-muted-foreground">Factory Name</label>
                                <p className="text-lg font-semibold">{factory.name}</p>
                            </div>

                            <div>
                                <label className="text-sm font-medium text-muted-foreground">Status</label>
                                <div className="mt-1">
                                    <Badge variant={factory.status === 'active' ? 'default' : 'secondary'} className="capitalize">
                                        {factory.status}
                                    </Badge>
                                </div>
                            </div>

                            {factory.mileage_km && (
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Distance (Single Way)</label>
                                    <p className="text-lg">{Number(factory.mileage_km).toFixed(2)} km</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Location Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center justify-between">
                                <span className="flex items-center">
                                    <MapPin className="mr-2 h-5 w-5" />
                                    Location Details
                                </span>
                                {factory.latitude && factory.longitude && (
                                    <Button variant="outline" size="sm" onClick={openInMaps}>
                                        <Navigation className="mr-2 h-4 w-4" />
                                        Open Map
                                    </Button>
                                )}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {factory.address && (
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Address</label>
                                    <p className="text-lg">{factory.address}</p>
                                </div>
                            )}

                            {factory.city && (
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">City</label>
                                    <p className="text-lg">{factory.city}</p>
                                </div>
                            )}

                            {factory.latitude && factory.longitude && (
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">Latitude</label>
                                        <p className="font-mono">{factory.latitude}</p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">Longitude</label>
                                        <p className="font-mono">{factory.longitude}</p>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Metadata */}
                    <Card className="md:col-span-2">
                        <CardHeader>
                            <CardTitle>Metadata</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">Created At</label>
                                <p>
                                    {new Date(factory.created_at).toLocaleString('en-US', {
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    })}
                                </p>
                            </div>

                            <div>
                                <label className="text-sm font-medium text-muted-foreground">Last Updated</label>
                                <p>
                                    {new Date(factory.updated_at).toLocaleString('en-US', {
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    })}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Actions */}
                {can('delete-factories') && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Danger Zone</CardTitle>
                            <CardDescription>Irreversible actions for this factory</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button variant="destructive" onClick={handleDelete}>
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete Factory
                            </Button>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppSidebarLayout>
    );
}
