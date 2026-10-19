<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // No location data of their own to derive a zone from (unlike
        // Factory's city or Vendor's address) — split deterministically
        // across zones so the seeded fleet is still useful for exercising
        // zone-scoped filtering/access out of the box.
        $dhakaZoneId = Zone::where('code', 'DHK')->value('id');
        $ctgZoneId = Zone::where('code', 'CTG')->value('id');

        // Create some realistic sample vehicles
        $vehicles = [
            [
                'brand' => 'Toyota',
                'model' => 'Camry',
                'color' => 'Silver',
                'registration_number' => 'ABC-123',
                'vendor_id' => null,
                'vehicle_type' => 'sedan',
                'rental_type' => 'own',
                'is_active' => true,
                'zone_id' => $dhakaZoneId,
            ],
            [
                'brand' => 'Honda',
                'model' => 'Civic',
                'color' => 'Blue',
                'registration_number' => 'DEF-456',
                'vendor_id' => null,
                'vehicle_type' => 'sedan',
                'rental_type' => 'pool',
                'is_active' => true,
                'zone_id' => $dhakaZoneId,
            ],
            [
                'brand' => 'Ford',
                'model' => 'F-150',
                'color' => 'Red',
                'registration_number' => 'GHI-789',
                'vendor_id' => null,
                'vehicle_type' => 'pickup',
                'rental_type' => 'own',
                'is_active' => true,
                'zone_id' => $dhakaZoneId,
            ],
            [
                'brand' => 'Chevrolet',
                'model' => 'Suburban',
                'color' => 'Black',
                'registration_number' => 'JKL-012',
                'vendor_id' => null,
                'vehicle_type' => 'suv',
                'rental_type' => 'pool',
                'is_active' => false,
                'zone_id' => $dhakaZoneId,
            ],
            [
                'brand' => 'Nissan',
                'model' => 'Altima',
                'color' => 'White',
                'registration_number' => 'MNO-345',
                'vendor_id' => null,
                'vehicle_type' => 'sedan',
                'rental_type' => 'rental',
                'is_active' => true,
                'zone_id' => $dhakaZoneId,
            ],
            [
                'brand' => 'BMW',
                'model' => '5 Series',
                'color' => 'Gray',
                'registration_number' => 'PQR-678',
                'vendor_id' => null,
                'vehicle_type' => 'sedan',
                'rental_type' => 'pool',
                'is_active' => true,
                'zone_id' => $dhakaZoneId,
            ],
            [
                'brand' => 'Mercedes',
                'model' => 'E-Class',
                'color' => 'Silver',
                'registration_number' => 'STU-901',
                'vendor_id' => null,
                'vehicle_type' => 'sedan',
                'rental_type' => 'pool',
                'is_active' => true,
                'zone_id' => $dhakaZoneId,
            ],
            [
                'brand' => 'Audi',
                'model' => 'A6',
                'color' => 'Black',
                'registration_number' => 'VWX-234',
                'vendor_id' => null,
                'vehicle_type' => 'sedan',
                'rental_type' => 'pool',
                'is_active' => false,
                'zone_id' => $ctgZoneId,
            ],
            [
                'brand' => 'Hyundai',
                'model' => 'Sonata',
                'color' => 'Blue',
                'registration_number' => 'YZA-567',
                'vendor_id' => null,
                'vehicle_type' => 'sedan',
                'rental_type' => 'own',
                'is_active' => true,
                'zone_id' => $ctgZoneId,
            ],
            [
                'brand' => 'Kia',
                'model' => 'Optima',
                'color' => 'Red',
                'registration_number' => 'BCD-890',
                'vendor_id' => null,
                'vehicle_type' => 'sedan',
                'rental_type' => 'pool',
                'is_active' => true,
                'zone_id' => $ctgZoneId,
            ],
        ];

        foreach ($vehicles as $vehicle) {
            Vehicle::updateOrCreate(
                ['registration_number' => $vehicle['registration_number']],
                $vehicle
            );
        }

        echo "VMS Vehicles created successfully:\n";
        echo "- 10 vehicles with different types and rental status\n";
    }
}
