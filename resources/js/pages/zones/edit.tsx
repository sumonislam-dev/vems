import { PageHeader } from '@/base-components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, Globe, Save } from 'lucide-react';

interface Zone {
    id: number;
    name: string;
    code: string;
    is_active: boolean;
}

interface EditZoneProps {
    zone: Zone;
}

export default function EditZone({ zone }: EditZoneProps) {
    const { data, setData, patch, processing, errors } = useForm({
        name: zone.name || '',
        code: zone.code || '',
        is_active: zone.is_active ? '1' : '0',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(route('zones.update', zone.id));
    };

    return (
        <AppLayout>
            <Head title="Edit Zone" />

            <div className="space-y-6">
                <PageHeader
                    title="Edit Zone"
                    description="Update this operational zone"
                    actions={[
                        {
                            label: 'Back to Zones',
                            icon: <ArrowLeft className="mr-2 h-4 w-4" />,
                            href: route('zones.index'),
                            variant: 'outline',
                        },
                    ]}
                />

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid gap-6 lg:grid-cols-3">
                        <div className="space-y-6 lg:col-span-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center space-x-2">
                                        <Globe className="h-5 w-5" />
                                        <span>Basic Information</span>
                                    </CardTitle>
                                    <CardDescription>Update the name and code for this zone</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="name">
                                                Zone Name <span className="text-red-500">*</span>
                                            </Label>
                                            <Input
                                                id="name"
                                                type="text"
                                                value={data.name}
                                                onChange={(e) => setData('name', e.target.value)}
                                                className={errors.name ? 'border-red-500' : ''}
                                                required
                                            />
                                            {errors.name && <p className="text-sm text-red-600">{errors.name}</p>}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="code">
                                                Zone Code <span className="text-red-500">*</span>
                                            </Label>
                                            <Input
                                                id="code"
                                                type="text"
                                                value={data.code}
                                                onChange={(e) => setData('code', e.target.value)}
                                                className={errors.code ? 'border-red-500' : ''}
                                                required
                                            />
                                            {errors.code && <p className="text-sm text-red-600">{errors.code}</p>}
                                            <p className="text-sm text-gray-500">Unique identifier for the zone</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <div className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Status Settings</CardTitle>
                                    <CardDescription>Configure zone status</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="is_active"
                                            checked={data.is_active === '1'}
                                            onCheckedChange={(checked: boolean) => setData('is_active', checked ? '1' : '0')}
                                        />
                                        <Label htmlFor="is_active">Active Zone</Label>
                                    </div>
                                    <p className="mt-2 text-sm text-gray-500">
                                        {data.is_active === '1'
                                            ? 'Zone is active and selectable elsewhere in the app'
                                            : 'Zone is inactive and hidden from selection'}
                                    </p>
                                    {errors.is_active && <p className="mt-1 text-sm text-red-600">{errors.is_active}</p>}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Actions</CardTitle>
                                    <CardDescription>Save or cancel your changes</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <Button type="submit" disabled={processing} className="w-full">
                                        <Save className="mr-2 h-4 w-4" />
                                        {processing ? 'Saving...' : 'Save Changes'}
                                    </Button>

                                    <Link href={route('zones.index')}>
                                        <Button variant="outline" className="w-full">
                                            Cancel
                                        </Button>
                                    </Link>
                                </CardContent>
                            </Card>

                            {Object.keys(errors).length > 0 && (
                                <Card className="border-red-200">
                                    <CardHeader>
                                        <CardTitle className="flex items-center space-x-2 text-red-600">
                                            <AlertCircle className="h-5 w-5" />
                                            <span>Validation Errors</span>
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <ul className="space-y-1 text-sm">
                                            {Object.entries(errors).map(([field, message]) => (
                                                <li key={field} className="text-red-600">
                                                    • {message}
                                                </li>
                                            ))}
                                        </ul>
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
