<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Role model is intentionally flat: super-admin, admin, employee, driver.
     * super-admin/admin both get every permission (super-admin additionally
     * bypasses all permission checks via AppServiceProvider's Gate::before —
     * see CLAUDE.md); employee/driver get only what their day-to-day flows need.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Permissions
        $permissions = [
            // Product Management
            'view-products',
            'create-products',
            'edit-products',
            'delete-products',

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
            'manage-vehicle-documents',

            // Trip Management
            'view-trips',
            'view-own-trips',
            'create-trips',
            'edit-trips',
            'delete-trips',
            'approve-trips',
            'reject-trips',
            'assign-drivers',
            'check-in-trips',
            'check-out-trips',

            // Trip Passenger Attendance
            'capture-passenger-attendance',
            'correct-passenger-attendance',
            'view-passenger-events',

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
            'manage-driver-documents',
            'view-driver-performance',

            // Scheduling
            'view-schedules',
            'create-schedules',
            'edit-schedules',
            'delete-schedules',
            'manage-recurring-schedules',

            // Maintenance
            'view-maintenance',
            'create-maintenance',
            'edit-maintenance',
            'delete-maintenance',
            'schedule-maintenance',
            'approve-maintenance',

            // Fuel Management
            'view-fuel-logs',
            'create-fuel-logs',
            'edit-fuel-logs',
            'delete-fuel-logs',
            'manage-fuel-budget',

            // Reports & Analytics
            'view-reports',
            'create-reports',
            'export-reports',
            'view-analytics',
            'view-cost-analysis',

            // Department Management
            'view-departments',
            'create-departments',
            'edit-departments',
            'delete-departments',
            'manage-department-budget',

            // User Management
            'view-users',
            'create-users',
            'edit-users',
            'delete-users',
            'manage-user-roles',
            'view-user-activity',

            // Role & Permission Management
            'view-roles',
            'create-roles',
            'edit-roles',
            'delete-roles',

            // System Settings
            'view-settings',
            'edit-settings',
            'manage-system-config',
            'view-system-logs',
            'backup-system',

            // Notifications
            'view-notifications',
            'send-notifications',
            'manage-notification-settings',

            // GPS Tracking (Optional)
            'view-live-tracking',
            'manage-tracking-settings',
            'view-route-history',

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

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // 1. Super Admin - Full access, plus the Gate::before bypass
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        // 2. Admin - Full operational access (everything super-admin has,
        // explicitly granted rather than via the Gate::before bypass)
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::all());

        // 3. Employee - Regular staff who request vehicles/trips and can raise feedback
        $employee = Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
        $employee->syncPermissions([
            'view-vehicles',
            'view-own-trips', 'create-trips',
            'view-schedules',
            'view-departments',
            'create-complaints', 'view-own-complaints',
            'view-notifications',
            'capture-own-attendance',
        ]);

        // 4. Driver - Operates trips: check-in/out, attendance capture, own fuel logs
        $driver = Role::firstOrCreate(['name' => 'driver', 'guard_name' => 'web']);
        $driver->syncPermissions([
            'view-vehicles',
            'view-own-trips', 'check-in-trips', 'check-out-trips',
            'capture-passenger-attendance', 'view-passenger-events',
            'view-schedules',
            'view-fuel-logs', 'create-fuel-logs',
            'create-complaints', 'view-own-complaints',
            'view-notifications',
            'capture-own-attendance',
        ]);

        // Remove any previously-seeded roles this refactor no longer keeps
        Role::where('guard_name', 'web')
            ->whereNotIn('name', ['super-admin', 'admin', 'employee', 'driver'])
            ->get()
            ->each(fn (Role $role) => $role->delete());
    }
}
