<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;

class CheckUserPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:check-permissions {email?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and assign Super Admin permissions to a user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');

        if (!$email) {
            // Get the first user if no email provided
            $user = User::first();
            if (!$user) {
                $this->error('No users found in the database!');
                return;
            }
        } else {
            $user = User::where('email', $email)->first();
            if (!$user) {
                $this->error("User with email {$email} not found!");
                return;
            }
        }

        $this->info("User: {$user->name}");
        $this->info("Email: {$user->email}");

        // Show current roles
        $currentRoles = $user->roles->pluck('name')->toArray();
        $this->info("Current roles: " . (empty($currentRoles) ? 'None' : implode(', ', $currentRoles)));

        // Check if super-admin role exists
        $superAdminRole = Role::where('name', 'super-admin')->first();
        if (!$superAdminRole) {
            $this->error('super-admin role not found! Please run: php artisan setup:permissions');
            return;
        }

        // Assign super-admin role if not already assigned
        if (!$user->hasRole('super-admin')) {
            $this->info('Assigning super-admin role...');
            $user->assignRole('super-admin');
            $this->info('✅ super-admin role assigned successfully!');
        } else {
            $this->info('✅ User already has super-admin role');
        }

        // Check department permissions
        $departmentPermissions = [
            'view-departments',
            'create-departments',
            'edit-departments',
            'delete-departments'
        ];

        $this->info("\nDepartment permissions check:");
        foreach ($departmentPermissions as $permission) {
            $hasPermission = $user->can($permission);
            $status = $hasPermission ? '✅' : '❌';
            $this->info("  {$status} {$permission}");
        }

        if ($user->can('view-departments')) {
            $this->info("\n🎉 User can access departments module!");
        } else {
            $this->error("\n❌ User cannot access departments module. Please check role permissions.");
        }
    }
}
