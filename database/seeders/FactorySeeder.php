<?php

namespace Database\Seeders;

use App\Models\Factory;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class FactorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $factories = [
            [
                'account_id' => '9178',
                'name' => 'A.K.M. KNIT WEAR LTD.',
                'status' => 'active',
                'address' => '14 No. Genda, Karnopara, Ulail, Savar',
                'city' => 'Dhaka',
                'latitude' => 23.82743,
                'longitude' => 90.25867,
                'mileage_km' => 24.00,
            ],
            [
                'account_id' => '9245',
                'name' => 'BEXIMCO TEXTILES LTD.',
                'status' => 'active',
                'address' => 'Kashipur, Narayanganj',
                'city' => 'Narayanganj',
                'latitude' => 23.63454,
                'longitude' => 90.50067,
                'mileage_km' => 18.50,
            ],
            [
                'account_id' => '9312',
                'name' => 'SQUARE TEXTILES LTD.',
                'status' => 'active',
                'address' => 'Tejgaon Industrial Area, Dhaka',
                'city' => 'Dhaka',
                'latitude' => 23.76081,
                'longitude' => 90.39724,
                'mileage_km' => 12.00,
            ],
            [
                'account_id' => '9423',
                'name' => 'APEX FOOTWEAR LTD.',
                'status' => 'active',
                'address' => 'Bhaluka, Mymensingh',
                'city' => 'Mymensingh',
                'latitude' => 24.42675,
                'longitude' => 90.46789,
                'mileage_km' => 95.30,
            ],
            [
                'account_id' => '9534',
                'name' => 'DEKKO KNITWEAR LTD.',
                'status' => 'active',
                'address' => 'Kashimpur, Gazipur',
                'city' => 'Gazipur',
                'latitude' => 24.05372,
                'longitude' => 90.39456,
                'mileage_km' => 32.75,
            ],
            [
                'account_id' => '9645',
                'name' => 'HAMEEM GROUP',
                'status' => 'active',
                'address' => 'Hemayetpur, Savar',
                'city' => 'Dhaka',
                'latitude' => 23.85123,
                'longitude' => 90.23456,
                'mileage_km' => 28.00,
            ],
            [
                'account_id' => '9756',
                'name' => 'ANANTA APPARELS LTD.',
                'status' => 'active',
                'address' => 'Noyapara, Kashimpur, Gazipur',
                'city' => 'Gazipur',
                'latitude' => 24.08956,
                'longitude' => 90.42345,
                'mileage_km' => 35.20,
            ],
            [
                'account_id' => '9867',
                'name' => 'MATEX BANGLADESH LTD.',
                'status' => 'inactive',
                'address' => 'Rupshi, Rupganj, Narayanganj',
                'city' => 'Narayanganj',
                'latitude' => 23.75234,
                'longitude' => 90.51234,
                'mileage_km' => 22.50,
            ],
            [
                'account_id' => '9978',
                'name' => 'FAKIR FASHION LTD.',
                'status' => 'active',
                'address' => 'Dhamrai, Dhaka',
                'city' => 'Dhaka',
                'latitude' => 23.91234,
                'longitude' => 90.15678,
                'mileage_km' => 38.00,
            ],
            [
                'account_id' => '10089',
                'name' => 'NASSA GROUP',
                'status' => 'active',
                'address' => 'Jamgora, Ashulia, Dhaka',
                'city' => 'Dhaka',
                'latitude' => 23.88765,
                'longitude' => 90.28901,
                'mileage_km' => 30.00,
            ],
            [
                'account_id' => '10190',
                'name' => 'PACIFIC JEANS LTD.',
                'status' => 'active',
                'address' => 'Chittagong Export Processing Zone',
                'city' => 'Chittagong',
                'latitude' => 22.27432,
                'longitude' => 91.78901,
                'mileage_km' => 245.00,
            ],
            [
                'account_id' => '10201',
                'name' => 'DBL GROUP',
                'status' => 'active',
                'address' => 'Palash, Narsingdi',
                'city' => 'Narsingdi',
                'latitude' => 23.92345,
                'longitude' => 90.71234,
                'mileage_km' => 42.00,
            ],
            [
                'account_id' => '10312',
                'name' => 'STYLECRAFT LTD.',
                'status' => 'active',
                'address' => 'Kaliakoir, Gazipur',
                'city' => 'Gazipur',
                'latitude' => 24.12345,
                'longitude' => 90.38765,
                'mileage_km' => 40.50,
            ],
            [
                'account_id' => '10423',
                'name' => 'MOHAMMADI GROUP',
                'status' => 'inactive',
                'address' => 'Bhulta, Rupganj, Narayanganj',
                'city' => 'Narayanganj',
                'latitude' => 23.79876,
                'longitude' => 90.53456,
                'mileage_km' => 25.00,
            ],
            [
                'account_id' => '10534',
                'name' => 'HAMS GARMENTS LTD.',
                'status' => 'active',
                'address' => 'Tongi, Gazipur',
                'city' => 'Gazipur',
                'latitude' => 23.89123,
                'longitude' => 90.40567,
                'mileage_km' => 20.00,
            ],
        ];

        foreach ($factories as $factory) {
            $factory['zone_id'] = Zone::idForCity($factory['city']);

            Factory::updateOrCreate(
                ['account_id' => $factory['account_id']],
                $factory
            );
        }

        $this->command->info('Created '.count($factories).' factories successfully!');
    }
}
