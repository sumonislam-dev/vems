<?php

namespace Database\Seeders;

use App\Models\RouteStop;
use App\Models\Stop;
use App\Models\VehicleRoute;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class RouteStopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data - disable foreign key checks for truncate
        $driver = \DB::getDriverName();

        if ($driver === 'mysql') {
            \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        } elseif ($driver === 'sqlite') {
            \DB::statement('PRAGMA foreign_keys = OFF;');
        }

        RouteStop::truncate();
        VehicleRoute::truncate();
        Stop::truncate();

        if ($driver === 'mysql') {
            \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } elseif ($driver === 'sqlite') {
            \DB::statement('PRAGMA foreign_keys = ON;');
        }

        // Create 30 unique stops (enough for variety)
        $this->command->info('Creating stops...');
        $stops = Stop::factory()->count(30)->create();

        // Create 10 routes, split between the offices (every third in
        // Chattogram) so zone-limited users see routes; stops stay unzoned,
        // which every zone can pick
        $this->command->info('Creating routes...');
        $dhaka = Zone::idForCity('Dhaka');
        $chattogram = Zone::idForCity('Chittagong');
        $routes = VehicleRoute::factory()->count(10)->create()->each(
            fn (VehicleRoute $route, int $i) => $route->update(['zone_id' => $i % 3 === 2 ? $chattogram : $dhaka])
        );

        $this->command->info('Creating route-stop relationships...');

        // For each route, assign 5 unique stops with proper timing
        foreach ($routes as $routeIndex => $route) {
            // Get 5 unique stops for this route by shuffling and taking 5
            $routeStops = $stops->shuffle()->take(5);

            // Create route stops with proper ordering and realistic timing
            foreach ($routeStops as $order => $stop) {
                // Generate realistic timing progression
                // Start between 6-8 AM, then progress every 1-3 hours
                $baseHour = fake()->numberBetween(6, 8);
                $hourIncrement = $order * fake()->numberBetween(1, 3);
                $currentHour = $baseHour + $hourIncrement;

                // Keep hours within 24-hour format
                if ($currentHour >= 24) {
                    $currentHour = $currentHour - 24;
                }

                $minute = fake()->randomElement([0, 15, 30, 45]);
                $arrivalTime = sprintf('%02d:%02d', $currentHour, $minute);

                // Departure is 2-8 minutes after arrival
                $departureMinutes = $minute + fake()->numberBetween(2, 8);
                $departureHour = $currentHour;

                if ($departureMinutes >= 60) {
                    $departureMinutes -= 60;
                    $departureHour += 1;
                    if ($departureHour >= 24) {
                        $departureHour = 0;
                    }
                }

                $departureTime = sprintf('%02d:%02d', $departureHour, $departureMinutes);

                RouteStop::create([
                    'vehicle_route_id' => $route->id,
                    'stop_id' => $stop->id,
                    'stop_order' => $order + 1,
                    'arrival_time' => fake()->boolean(85) ? $arrivalTime : null, // 85% chance of having arrival time
                    'departure_time' => fake()->boolean(85) ? $departureTime : null, // 85% chance of having departure time
                ]);
            }
        }

        $routeCount = VehicleRoute::count();
        $stopCount = Stop::count();
        $routeStopCount = RouteStop::count();

        $this->command->info('Successfully created:');
        $this->command->info("- {$routeCount} routes");
        $this->command->info("- {$stopCount} stops");
        $this->command->info("- {$routeStopCount} route-stop relationships");
        $this->command->info('- Each route has exactly 5 unique stops');
    }
}
