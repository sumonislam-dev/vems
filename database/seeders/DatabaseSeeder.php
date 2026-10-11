<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class, // Must run first to create roles
            ZoneSeeder::class, // Must run before any seeder assigning zone_id
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
