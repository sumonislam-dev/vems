<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Every permission the app uses.
     *
     * @return list<string>
     */
    public static function permissions(): array
    {
        return [
            // Product Management
            'view-products',
            'create-products',
            'edit-products',
            'delete-products',
            'export-products',

            // Stop Management
            'view-stops',
            'create-stops',

            // Vendor Management
            'view-vendors',
            'create-vendors',
            'edit-vendors',
            'delete-vendors',

            // Vehicle Management
            'view-vehicles',
            'create-vehicles',
            'edit-vehicles',
            'delete-vehicles',
            'assign-vehicles',
            'export-vehicles',
            'manage-vehicle-downtime',

            // Trip Management
            'view-trips',
            'view-own-trips',
            'create-trips',
            'edit-trips',
            'delete-trips',
            'approve-trips',
            // Approve/reject trips of the departments the user heads (departments.head_id)
            'approve-department-trips',
            'reject-trips',
            'check-in-trips',
            'check-out-trips',

            // Trip Passenger Attendance
            'capture-passenger-attendance',
            'correct-passenger-attendance',

            // Trip Feedback & Complaints
            'view-own-complaints',
            'view-complaints',
            'create-complaints',
            'assign-complaints',
            'resolve-complaints',
            'delete-complaints',

            // Driver Management
            'view-drivers',
            'create-drivers',
            'edit-drivers',
            'delete-drivers',
            'export-drivers',

            // Maintenance (planned feature)
            'view-maintenance',
            'create-maintenance',
            'edit-maintenance',
            'delete-maintenance',
            'schedule-maintenance',
            'approve-maintenance',

            // Fuel Management (planned feature)
            'view-fuel-logs',
            'create-fuel-logs',
            'edit-fuel-logs',
            'delete-fuel-logs',
            'manage-fuel-budget',

            // Reports & Analytics
            'view-reports',
            'export-reports',

            // Department Management
            'view-departments',
            'create-departments',
            'edit-departments',
            'delete-departments',
            'export-departments',
            'import-departments',

            // Zone Management
            'view-zones',
            'create-zones',
            'edit-zones',
            'delete-zones',
            // Lets a zone-assigned user (e.g. a future scoped manager role) see
            // and manage every zone's data instead of just their own — see
            // BelongsToZone. super-admin/admin get it automatically below.
            'view-all-zones',

            // User Management
            'view-users',
            'create-users',
            'edit-users',
            'delete-users',
            'export-users',
            'import-users',
            'manage-user-roles',
            'view-user-activity',

            // Role & Permission Management
            'view-roles',
            'create-roles',
            'edit-roles',
            'delete-roles',

            // Notifications
            'view-notifications',

            // Factory Management
            'view-factories',
            'create-factories',
            'edit-factories',
            'delete-factories',

            // Logistics Management
            'view-logistics',
            'create-logistics',
            'edit-logistics',
            'delete-logistics',

            // User Group Management
            'view-user-groups',
            'create-user-groups',
            'edit-user-groups',
            'delete-user-groups',

            // Route Management
            'view-routes',
            'create-routes',
            'edit-routes',
            'delete-routes',

            // Employee Attendance
            'capture-own-attendance',
            'manage-attendance',
            'view-attendance-reports',
            'export-attendance-reports',
        ];
    }

    /**
     * Seeded roles and their permissions ('*' = every permission).
     *
     * @return array<string, '*'|list<string>>
     */
    public static function roles(): array
    {
        return [
            // 1. Super Admin - Full access, plus the Gate::before bypass
            'super-admin' => '*',
            // 2. Admin - Full operational access (everything super-admin has,
            // explicitly granted rather than via the Gate::before bypass)
            'admin' => '*',
            // 3. Transport Manager - Logistics: runs trips, fleet, drivers, vendors
            // and reports day to day, without user/role/zone administration or
            // deleting records
            'transport-manager' => [
                'view-trips', 'create-trips', 'edit-trips', 'approve-trips', 'reject-trips',
                'check-in-trips', 'check-out-trips',
                'capture-passenger-attendance', 'correct-passenger-attendance',
                'view-vehicles', 'create-vehicles', 'edit-vehicles', 'assign-vehicles', 'export-vehicles',
                'manage-vehicle-downtime',
                'view-drivers', 'create-drivers', 'edit-drivers', 'export-drivers',
                'view-vendors', 'create-vendors', 'edit-vendors',
                'view-routes', 'create-routes', 'edit-routes',
                'view-stops', 'create-stops',
                'view-factories', 'create-factories', 'edit-factories',
                'view-complaints', 'assign-complaints', 'resolve-complaints',
                'view-reports', 'export-reports',
                'view-departments',
                'view-notifications',
                'capture-own-attendance',
            ],
            // 4. Management - Read-only oversight: sees trips, fleet, complaints
            // and reports across the organisation, changes nothing
            'management' => [
                'view-trips', 'view-own-trips', 'create-trips',
                'view-vehicles', 'view-drivers', 'view-vendors', 'view-routes', 'view-factories',
                'view-complaints', 'create-complaints',
                'view-departments',
                'view-reports', 'export-reports',
                'view-attendance-reports',
                'view-notifications',
                'capture-own-attendance',
            ],
            // 5. Department Head - An employee who also approves/rejects their
            // own departments' trip requests (the department's head_id)
            'department-head' => [
                'view-own-trips', 'create-trips', 'approve-department-trips',
                'create-complaints', 'view-own-complaints',
                'view-notifications',
                'capture-own-attendance',
            ],
            // 6. Employee - Regular staff who request vehicles/trips and can raise feedback
            'employee' => [
                'view-own-trips', 'create-trips',
                'create-complaints', 'view-own-complaints',
                'view-notifications',
                'capture-own-attendance',
            ],
            // 7. Driver - Operates trips: check-in/out, passenger attendance
            'driver' => [
                'view-vehicles',
                'view-own-trips', 'check-in-trips', 'check-out-trips',
                'capture-passenger-attendance',
                'create-complaints', 'view-own-complaints',
                'view-notifications',
                'capture-own-attendance',
            ],
        ];
    }

    /**
     * Run the database seeds.
     *
     * Roles: super-admin, admin, transport-manager, management,
     * department-head, employee, driver.
     * super-admin/admin both get every permission (super-admin additionally
     * bypasses all permission checks via AppServiceProvider's Gate::before —
     * see CLAUDE.md); transport-manager runs day-to-day transport operations
     * without system administration; employee/driver get only what their
     * day-to-day flows need.
     *
     * This list is the single source of truth: permissions not listed here
     * (and roles not created here) are deleted when it runs.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = self::permissions();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Remove permissions no longer listed (e.g. for features that were
        // never built), so they don't linger in the Roles screen
        Permission::where('guard_name', 'web')
            ->whereNotIn('name', $permissions)
            ->get()
            ->each(fn (Permission $permission) => $permission->delete());
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::roles() as $name => $grants) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'])
                ->syncPermissions($grants === '*' ? Permission::all() : $grants);
        }

        // Remove any previously-seeded roles this refactor no longer keeps
        Role::where('guard_name', 'web')
            ->whereNotIn('name', array_keys(self::roles()))
            ->get()
            ->each(fn (Role $role) => $role->delete());
    }
}
