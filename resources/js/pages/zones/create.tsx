import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { AlertCircle, ArrowLeft, Globe, Save } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/base-components/page-header';

export default function CreateZone() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        code: '',
        is_active: '1',
    });

    const generateCode = (name: string) => {
        return name
            .toUpperCase()
            .replace(/[^A-Z0-9]/g, '')
            .substring(0, 10);
    };

    const handleNameChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const name = e.target.value;
        setData('name', name);
        if (!data.code || data.code === generateCode(data.name)) {
            setData('code', generateCode(name));
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('zones.store'));
    };

    return (
        <AppLayout>
            <Head title="Create Zone" />

            <div className="space-y-6">
                <PageHeader
                    title="Create Zone"
                    description="Add a new operational zone (e.g. Dhaka, Chattogram)"
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
                        <div className="lg:col-span-2 space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center space-x-2">
                                        <Globe className="h-5 w-5" />
                                        <span>Basic Information</span>
                                    </CardTitle>
                                    <CardDescription>
                                        Enter the name and code for this zone
                                    </CardDescription>
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
                                                placeholder="e.g. Dhaka"
                                                value={data.name}
                                                onChange={handleNameChange}
                                                className={errors.name ? 'border-red-500' : ''}
                                                required
                                            />
                                            {errors.name && (
                                                <p className="text-sm text-red-600">{errors.name}</p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="code">
                                                Zone Code <span className="text-red-500">*</span>
                                            </Label>
                                            <Input
                                                id="code"
                                                type="text"
                                                placeholder="e.g. DHK"
                                                value={data.code}
                                                onChange={(e) => setData('code', e.target.value)}
                                                className={errors.code ? 'border-red-500' : ''}
                                                required
                                            />
                                            {errors.code && (
                                                <p className="text-sm text-red-600">{errors.code}</p>
                                            )}
                                            <p className="text-sm text-gray-500">
                                                Unique identifier for the zone
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <div className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Status Settings</CardTitle>
                                    <CardDescription>
                                        Configure zone status
                                    </CardDescription>
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
                                    <p className="text-sm text-gray-500 mt-2">
                                        {data.is_active === '1'
                                            ? 'Zone is active and selectable elsewhere in the app'
                                            : 'Zone is inactive and hidden from selection'}
                                    </p>
                                    {errors.is_active && (
                                        <p className="text-sm text-red-600 mt-1">{errors.is_active}</p>
                                    )}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Actions</CardTitle>
                                    <CardDescription>
                                        Save or cancel zone creation
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <Button type="submit" disabled={processing} className="w-full">
                                        <Save className="mr-2 h-4 w-4" />
                                        {processing ? 'Creating...' : 'Create Zone'}
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
                                        <CardTitle className="text-red-600 flex items-center space-x-2">
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
