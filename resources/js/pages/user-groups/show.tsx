import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { ArrowLeft, Pencil, Users, UserPlus, UserMinus, Search } from 'lucide-react';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { PageHeader } from '@/base-components/page-header';
import { useCan } from '@/lib/permissions';
import axios from 'axios';
import type { BreadcrumbItem } from '@/types';

interface Member {
  id: number;
  name: string;
  email: string;
  user_type: string;
  status: string;
  department: string | null;
  added_at: string;
  added_by: number;
}

interface UserGroup {
  id: number;
  name: string;
  description: string;
  status: 'active' | 'inactive';
  total_members: number;
  creator: {
    id: number;
    name: string;
  } | null;
  created_at: string;
  members: Member[];
}

interface ShowUserGroupProps {
  group: UserGroup;
}

interface AvailableUser {
  id: number;
  name: string;
  email: string;
  employee_id: string;
  user_type: string;
}

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'User Groups', href: '/user-groups' },
  { title: 'Details', href: '#' },
];

export default function ShowUserGroup({ group }: ShowUserGroupProps) {
  const can = useCan();
  const [showAddDialog, setShowAddDialog] = useState(false);
  const [availableUsers, setAvailableUsers] = useState<AvailableUser[]>([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedUsers, setSelectedUsers] = useState<number[]>([]);
  const [loading, setLoading] = useState(false);
  const [processing, setProcessing] = useState(false);

  const fetchAvailableUsers = async (search: string = '') => {
    setLoading(true);
    try {
      const response = await axios.get(route('user-groups.available-users', group.id), {
        params: { search },
      });
      setAvailableUsers(response.data);
    } catch {
      // available-users fetch failure is non-critical
    } finally {
      setLoading(false);
    }
  };

  const handleOpenDialog = () => {
    setShowAddDialog(true);
    fetchAvailableUsers();
  };

  const handleSearch = (value: string) => {
    setSearchTerm(value);
    fetchAvailableUsers(value);
  };

  const toggleUserSelection = (userId: number) => {
    setSelectedUsers((prev) =>
      prev.includes(userId) ? prev.filter((id) => id !== userId) : [...prev, userId]
    );
  };

  const handleAddMembers = () => {
    if (selectedUsers.length === 0) return;

    setProcessing(true);
    router.post(route('user-groups.add-members', group.id),
      { user_ids: selectedUsers },
      {
        preserveScroll: true,
        onSuccess: () => {
          setShowAddDialog(false);
          setSelectedUsers([]);
          setSearchTerm('');
          setProcessing(false);
        },
        onError: () => {
          setProcessing(false);
        },
      }
    );
  };

  const handleRemoveMember = (userId: number) => {
    if (confirm('Are you sure you want to remove this member from the group?')) {
      router.delete(route('user-groups.remove-member', [group.id, userId]), {
        preserveScroll: true,
      });
    }
  };

  return (
    <AppSidebarLayout breadcrumbs={breadcrumbs}>
      <Head title={`${group.name} - Group Details`} />

      <div className="space-y-6">
        <PageHeader
          title={group.name}
          description="View and manage group members"
          actions={[
            {
              label: 'Back to Groups',
              icon: <ArrowLeft className="h-4 w-4" />,
              href: route('user-groups.index'),
              variant: 'outline',
            },
            {
              label: 'Edit Group',
              icon: <Pencil className="h-4 w-4" />,
              href: route('user-groups.edit', group.id),
              variant: 'outline',
              permission: 'edit-user-groups',
            },
          ]}
        />

        {/* Group Info Card */}
        <Card>
          <CardHeader>
            <CardTitle>Group Information</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <p className="text-sm font-medium text-muted-foreground">Group Name</p>
                <p className="text-lg font-semibold">{group.name}</p>
              </div>
              <div>
                <p className="text-sm font-medium text-muted-foreground">Status</p>
                <Badge variant={group.status === 'active' ? 'default' : 'secondary'}>
                  {group.status}
                </Badge>
              </div>
              <div>
                <p className="text-sm font-medium text-muted-foreground">Total Members</p>
                <p className="text-lg font-semibold flex items-center gap-2">
                  <Users className="h-5 w-5" />
                  {group.total_members}
                </p>
              </div>
              <div>
                <p className="text-sm font-medium text-muted-foreground">Created By</p>
                <p className="text-lg">{group.creator?.name || '—'}</p>
              </div>
              {group.description && (
                <div className="col-span-full">
                  <p className="text-sm font-medium text-muted-foreground">Description</p>
                  <p className="text-base">{group.description}</p>
                </div>
              )}
            </div>
          </CardContent>
        </Card>

        {/* Members Card */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle>Group Members ({group.members.length})</CardTitle>
              {can('edit-user-groups') && (
                <Dialog open={showAddDialog} onOpenChange={setShowAddDialog}>
                  <DialogTrigger asChild>
                    <Button onClick={handleOpenDialog}>
                      <UserPlus className="h-4 w-4 mr-2" />
                      Add Members
                    </Button>
                  </DialogTrigger>
                  <DialogContent className="max-w-2xl max-h-[600px] overflow-y-auto">
                    <DialogHeader>
                      <DialogTitle>Add Members to {group.name}</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4">
                      <div className="relative">
                        <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input
                          type="text"
                          placeholder="Search users..."
                          value={searchTerm}
                          onChange={(e) => handleSearch(e.target.value)}
                          className="pl-10"
                        />
                      </div>

                      {loading ? (
                        <div className="text-center py-8 text-muted-foreground">Loading users...</div>
                      ) : availableUsers.length === 0 ? (
                        <div className="text-center py-8 text-muted-foreground">
                          No available users found
                        </div>
                      ) : (
                        <div className="space-y-2 max-h-[400px] overflow-y-auto">
                          {availableUsers.map((user) => (
                            <div
                              key={user.id}
                              className={`flex items-center justify-between p-3 border rounded-lg cursor-pointer hover:bg-gray-50 ${
                                selectedUsers.includes(user.id) ? 'border-blue-500 bg-blue-50' : ''
                              }`}
                              onClick={() => toggleUserSelection(user.id)}
                            >
                              <div>
                                <p className="font-medium">{user.name}</p>
                                <p className="text-sm text-muted-foreground">{user.email}</p>
                                <div className="flex gap-2 mt-1">
                                  <Badge variant="outline">{user.user_type}</Badge>
                                  {user.employee_id && (
                                    <Badge variant="secondary">{user.employee_id}</Badge>
                                  )}
                                </div>
                              </div>
                              <input
                                type="checkbox"
                                checked={selectedUsers.includes(user.id)}
                                onChange={() => {}}
                                className="h-4 w-4"
                              />
                            </div>
                          ))}
                        </div>
                      )}

                      <div className="flex justify-between items-center pt-4 border-t">
                        <p className="text-sm text-muted-foreground">
                          {selectedUsers.length} user(s) selected
                        </p>
                        <div className="flex gap-2">
                          <Button
                            variant="outline"
                            onClick={() => {
                              setShowAddDialog(false);
                              setSelectedUsers([]);
                            }}
                          >
                            Cancel
                          </Button>
                          <Button
                            onClick={handleAddMembers}
                            disabled={selectedUsers.length === 0 || processing}
                          >
                            {processing ? 'Adding...' : `Add ${selectedUsers.length} Member(s)`}
                          </Button>
                        </div>
                      </div>
                    </div>
                  </DialogContent>
                </Dialog>
              )}
            </div>
          </CardHeader>
          <CardContent>
            {group.members.length === 0 ? (
              <div className="text-center py-12">
                <Users className="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                <p className="text-lg font-medium text-muted-foreground">No members yet</p>
                <p className="text-sm text-muted-foreground mb-4">
                  Add members to this group to get started
                </p>
              </div>
            ) : (
              <div className="space-y-2">
                {group.members.map((member) => (
                  <div
                    key={member.id}
                    className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50"
                  >
                    <div className="flex-1">
                      <div className="flex items-center gap-3">
                        <div>
                          <p className="font-medium">{member.name}</p>
                          <p className="text-sm text-muted-foreground">{member.email}</p>
                        </div>
                      </div>
                      <div className="flex gap-2 mt-2">
                        <Badge variant="outline">{member.user_type}</Badge>
                        {member.department && (
                          <Badge variant="secondary">{member.department}</Badge>
                        )}
                        <Badge variant={member.status === 'active' ? 'default' : 'secondary'}>
                          {member.status}
                        </Badge>
                      </div>
                      <p className="text-xs text-muted-foreground mt-2">
                        Added on {new Date(member.added_at).toLocaleDateString()}
                      </p>
                    </div>
                    {can('edit-user-groups') && (
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleRemoveMember(member.id)}
                      >
                        <UserMinus className="h-4 w-4 text-destructive" />
                      </Button>
                    )}
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AppSidebarLayout>
  );
}
