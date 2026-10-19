<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Production gets only roles/permissions and zones; everything else is
     * demo data (well-known passwords, fake vehicles and trips).
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class, // Must run first to create roles
            ZoneSeeder::class, // Must run before any seeder assigning zone_id
        ]);

        if (app()->isProduction()) {
            $this->command?->warn('Production: skipped demo data. Create the first admin with `php artisan vems:create-admin`.');

            return;
        }

        $this->call([
            DepartmentSeeder::class,
            UserSeeder::class, // UserSeeder should handle VMS user fields
            VehicleSeeder::class,
            VendorSeeder::class,
            FactorySeeder::class,
            RouteStopSeeder::class,
            TripSeeder::class,
            AttendanceSeeder::class,
        ]);
    }
}
