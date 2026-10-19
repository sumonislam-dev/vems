import { PageHeader } from '@/base-components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, Building2, Search, UserPlus, X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface User {
    id: number;
    name: string;
    email: string;
    user_type: string;
    employee_id: string;
    department?: string;
    phone?: string;
}

interface UserGroup {
    id: number;
    name: string;
    description: string;
    status: 'active' | 'inactive';
    member_ids: number[];
}

interface EditUserGroupProps {
    group: UserGroup;
    users: User[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'User Groups', href: '/user-groups' },
    { title: 'Edit', href: '#' },
];

export default function EditUserGroup({ group, users }: EditUserGroupProps) {
    const { data, setData, put, processing, errors } = useForm({
        name: group.name,
        description: group.description || '',
        status: group.status,
        user_ids: group.member_ids || ([] as number[]),
    });

    const [searchTerm, setSearchTerm] = useState('');
    const [filterDepartment, setFilterDepartment] = useState<string>('all');
    const [isSheetOpen, setIsSheetOpen] = useState(false);

    // Get unique departments
    const departments = Array.from(new Set(users.map((u) => u.department).filter(Boolean))) as string[];

    // Check if filters are active
    const hasActiveFilters = searchTerm !== '' || filterDepartment !== 'all';

    // Clear all filters
    const clearFilters = () => {
        setSearchTerm('');
        setFilterDepartment('all');
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('user-groups.update', group.id));
    };

    // Get selected users details
    const selectedUsers = users.filter((user) => data.user_ids.includes(user.id));

    // Get available users (not selected)
    const availableUsers = users.filter((user) => !data.user_ids.includes(user.id));

    // Filter available users based on search and department
    const filteredUsers = availableUsers.filter((user) => {
        const matchesSearch =
            user.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            user.email.toLowerCase().includes(searchTerm.toLowerCase()) ||
            user.employee_id.toLowerCase().includes(searchTerm.toLowerCase());
        const matchesDepartment = filterDepartment === 'all' || user.department === filterDepartment;
        return matchesSearch && matchesDepartment;
    });

    // Add user to selection
    const addUser = (userId: number) => {
        if (!data.user_ids.includes(userId)) {
            setData('user_ids', [...data.user_ids, userId]);
        }
    };

    // Remove user from selection
    const removeUser = (userId: number) => {
        setData(
            'user_ids',
            data.user_ids.filter((id) => id !== userId),
        );
    };

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Group - ${group.name}`} />

            <div className="space-y-4">
                <PageHeader
                    title={`Edit Group - ${group.name}`}
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
                        <div className="grid grid-cols-1 lg:grid-cols-5">
                            {/* Left Column - Group Details */}
                            <div className="border-r p-4 lg:col-span-2">
                                <div className="space-y-2.5">
                                    <div>
                                        <h3 className="text-base font-semibold">Group Information</h3>
                                    </div>

                                    {/* Name Field */}
                                    <div className="space-y-1">
                                        <Label htmlFor="name" className="text-xs font-medium">
                                            Group Name <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            id="name"
                                            type="text"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            placeholder="e.g., Sales Team"
                                            className={`h-9 text-sm ${errors.name ? 'border-destructive' : ''}`}
                                        />
                                        {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
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
                                        <Label htmlFor="status" className="text-xs font-medium">
                                            Status <span className="text-destructive">*</span>
                                        </Label>
                                        <Select value={data.status} onValueChange={(value) => setData('status', value as 'active' | 'inactive')}>
                                            <SelectTrigger className={`h-9 text-sm ${errors.status ? 'border-destructive' : ''}`}>
                                                <SelectValue placeholder="Select status" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="active" className="text-sm">
                                                    Active
                                                </SelectItem>
                                                <SelectItem value="inactive" className="text-sm">
                                                    Inactive
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {errors.status && <p className="text-xs text-destructive">{errors.status}</p>}
                                    </div>

                                    {/* Submit Button */}
                                    <div className="pt-2">
                                        <Button type="submit" disabled={processing} className="h-9 w-full" size="sm">
                                            {processing ? 'Updating...' : 'Update Group'}
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            {/* Right Column - Selected Users */}
                            <div className="flex flex-col p-4 lg:col-span-3">
                                <div className="mb-2 flex items-center justify-between">
                                    <div>
                                        <h3 className="text-base font-semibold">Selected Users</h3>
                                        <p className="text-[10px] text-muted-foreground">{selectedUsers.length} selected</p>
                                    </div>
                                    <Sheet open={isSheetOpen} onOpenChange={setIsSheetOpen}>
                                        <SheetTrigger asChild>
                                            <Button variant="outline" size="sm" className="h-8">
                                                <UserPlus className="mr-1.5 h-3.5 w-3.5" />
                                                Add Users
                                            </Button>
                                        </SheetTrigger>
                                        <SheetContent className="flex w-full flex-col p-0 sm:max-w-md">
                                            {/* Header */}
                                            <div className="border-b bg-muted/30 px-3 py-2">
                                                <div className="flex items-center justify-between gap-2 pr-8">
                                                    <div className="min-w-0 flex-1">
                                                        <h3 className="text-sm font-semibold">Add Users</h3>
                                                        <p className="text-[10px] text-muted-foreground">Search and select</p>
                                                    </div>
                                                    {hasActiveFilters && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-6 flex-shrink-0 px-2 text-[10px]"
                                                            onClick={clearFilters}
                                                        >
                                                            Clear
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>

                                            {/* Filters Section */}
                                            <div className="space-y-1.5 border-b px-3 py-2">
                                                {/* Search and Filter - Combined Row */}
                                                <div className="flex gap-1.5">
                                                    <div className="relative flex-1">
                                                        <Search className="absolute top-1/2 left-2 h-3 w-3 -translate-y-1/2 text-muted-foreground" />
                                                        <Input
                                                            type="text"
                                                            placeholder="Search..."
                                                            value={searchTerm}
                                                            onChange={(e) => setSearchTerm(e.target.value)}
                                                            className="h-7 pl-7 text-xs"
                                                        />
                                                    </div>
                                                    <div className="w-32">
                                                        <Select value={filterDepartment} onValueChange={setFilterDepartment}>
                                                            <SelectTrigger className="h-7 text-xs">
                                                                <SelectValue placeholder="Dept" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="all" className="text-xs">
                                                                    All ({availableUsers.length})
                                                                </SelectItem>
                                                                {departments.map((dept) => {
                                                                    const count = availableUsers.filter((u) => u.department === dept).length;
                                                                    return (
                                                                        <SelectItem key={dept} value={dept} className="text-xs">
                                                                            {dept} ({count})
                                                                        </SelectItem>
                                                                    );
                                                                })}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Results Header - Simplified */}
                                            <div className="border-b bg-muted/20 px-3 py-1">
                                                <div className="flex items-center justify-between">
                                                    <span className="text-[10px] text-muted-foreground">{filteredUsers.length} available</span>
                                                </div>
                                            </div>

                                            {/* Users List */}
                                            <div className="flex-1 overflow-y-auto px-3 py-2">
                                                {filteredUsers.length === 0 ? (
                                                    <div className="flex flex-col items-center justify-center py-6 text-center">
                                                        <UserPlus className="mb-1 h-8 w-8 text-muted-foreground/40" />
                                                        <p className="text-xs font-medium">{hasActiveFilters ? 'No match' : 'No users'}</p>
                                                    </div>
                                                ) : (
                                                    <div className="space-y-1">
                                                        {filteredUsers.map((user) => (
                                                            <div
                                                                key={user.id}
                                                                className="group flex cursor-pointer items-center gap-1.5 rounded border p-1.5 transition-all hover:border-primary/30 hover:bg-accent"
                                                                onClick={() => addUser(user.id)}
                                                            >
                                                                {/* User Avatar */}
                                                                <div className="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-primary/10">
                                                                    <span className="text-[10px] font-semibold text-primary">
                                                                        {user.name.charAt(0).toUpperCase()}
                                                                    </span>
                                                                </div>

                                                                {/* User Info */}
                                                                <div className="min-w-0 flex-1">
                                                                    <div className="flex items-center gap-1">
                                                                        <p className="truncate text-xs font-medium">{user.name}</p>
                                                                        <Badge variant="outline" className="px-1 text-[8px]">
                                                                            {user.user_type}
                                                                        </Badge>
                                                                    </div>
                                                                    <p className="truncate text-[9px] leading-tight text-muted-foreground">
                                                                        {user.email}
                                                                    </p>
                                                                </div>

                                                                {/* Add Icon */}
                                                                <UserPlus className="h-3.5 w-3.5 flex-shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>

                                            {/* Footer */}
                                            {selectedUsers.length > 0 && (
                                                <div className="border-t bg-muted/30 px-3 py-1.5">
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-[10px] text-muted-foreground">{selectedUsers.length} selected</span>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            className="h-6 px-3 text-xs"
                                                            onClick={() => setIsSheetOpen(false)}
                                                        >
                                                            Done
                                                        </Button>
                                                    </div>
                                                </div>
                                            )}
                                        </SheetContent>
                                    </Sheet>
                                </div>

                                {errors.user_ids && (
                                    <div className="mb-2 rounded-md border border-destructive/20 bg-destructive/10 p-2">
                                        <p className="text-[10px] text-destructive">{errors.user_ids}</p>
                                    </div>
                                )}

                                <div className="flex-1 overflow-y-auto pr-2" style={{ maxHeight: 'calc(100vh - 280px)' }}>
                                    {selectedUsers.length === 0 ? (
                                        <div className="py-6 text-center text-muted-foreground">
                                            <UserPlus className="mx-auto mb-1.5 h-8 w-8 opacity-40" />
                                            <p className="text-xs font-medium">No users selected</p>
                                            <p className="mt-0.5 text-[10px]">Click "Add Users" to get started</p>
                                        </div>
                                    ) : (
                                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                            {selectedUsers.map((user) => (
                                                <div
                                                    key={user.id}
                                                    className="group relative flex items-start gap-2 rounded-md border p-2 transition-colors hover:bg-accent"
                                                >
                                                    <div className="min-w-0 flex-1">
                                                        <div className="mb-0.5 flex items-center gap-1">
                                                            <p className="truncate text-xs font-medium">{user.name}</p>
                                                            <Badge variant="outline" className="px-1 text-[8px]">
                                                                {user.user_type}
                                                            </Badge>
                                                        </div>
                                                        <p className="truncate text-[10px] text-muted-foreground">{user.email}</p>
                                                        {user.department && (
                                                            <p className="mt-0.5 truncate text-[10px] text-muted-foreground">
                                                                <Building2 className="mr-0.5 inline h-2 w-2" />
                                                                {user.department}
                                                            </p>
                                                        )}
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        className="h-6 w-6 flex-shrink-0 p-0 opacity-0 transition-opacity group-hover:opacity-100"
                                                        onClick={() => removeUser(user.id)}
                                                    >
                                                        <X className="h-3 w-3" />
                                                    </Button>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </Card>
                </form>
            </div>
        </AppSidebarLayout>
    );
}
