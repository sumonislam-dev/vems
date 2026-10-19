<?php

namespace Database\Seeders;

use App\Models\Zone;
use Illuminate\Database\Seeder;

class ZoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Must run before any seeder that assigns zone_id (Users, Vehicles,
     * Vendors, Factories) — see DatabaseSeeder's call order.
     */
    public function run(): void
    {
        $zones = [
            ['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true],
            ['name' => 'Chittagong', 'code' => 'CTG', 'is_active' => true],
        ];

        foreach ($zones as $zone) {
            Zone::updateOrCreate(['code' => $zone['code']], $zone);
        }

        $this->command?->info('Created '.count($zones).' zones successfully!');
    }
}
