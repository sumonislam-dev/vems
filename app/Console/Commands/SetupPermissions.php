<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

class SetupPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setup:permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up roles and permissions for the application';

    /**
     * Execute the console command.
     *
     * This delegates to RolePermissionSeeder — the same seeder DatabaseSeeder
     * runs and that AppServiceProvider's Gate::before(hasRole('super-admin'))
     * depends on — rather than defining a second, disagreeing role/permission
     * set (see docs/PROJECT_IMPROVEMENTS.md 1.5).
     */
    public function handle()
    {
        $this->info('Setting up roles and permissions...');

        (new RolePermissionSeeder())->run();

        $this->info('Roles and permissions created.');

        // Assign super-admin role to the first user
        $firstUser = User::first();
        if ($firstUser) {
            $firstUser->assignRole('super-admin');
            $this->info("super-admin role assigned to user: {$firstUser->name}");
        }

        $this->info('Permissions setup completed successfully!');
    }
}
