<?php

namespace Database\Seeders;

use App\Models\Vendor;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class VendorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vendors = [
            [
                'name' => 'AutoCare Services Ltd.',
                'address' => '123 Main Street, Dhaka-1000, Bangladesh',
                'phone' => '+880-2-9876543',
                'email' => 'info@autocare.com.bd',
                'website' => 'https://www.autocare.com.bd',
                'description' => 'Complete automotive service provider specializing in vehicle maintenance and repairs.',
                'status' => 'active',
                'contacts' => [
                    [
                        'name' => 'Ahmed Rahman',
                        'position' => 'Service Manager',
                        'phone' => '+880-1711-123456',
                        'email' => 'ahmed@autocare.com.bd',
                        'is_primary' => true,
                        'notes' => 'Primary contact for all service inquiries',
                    ],
                    [
                        'name' => 'Fatima Khan',
                        'position' => 'Customer Relations Officer',
                        'phone' => '+880-1722-234567',
                        'email' => 'fatima@autocare.com.bd',
                        'is_primary' => false,
                        'notes' => 'Handles customer complaints and feedback',
                    ],
                ],
            ],
            [
                'name' => 'Metro Vehicle Solutions',
                'address' => '456 Commercial Area, Chittagong-4000, Bangladesh',
                'phone' => '+880-31-654321',
                'email' => 'contact@metrovehicle.com',
                'website' => 'https://www.metrovehicle.com',
                'description' => 'Professional vehicle maintenance and fleet management services.',
                'status' => 'active',
                'contacts' => [
                    [
                        'name' => 'Mohammad Ali',
                        'position' => 'Operations Director',
                        'phone' => '+880-1733-345678',
                        'email' => 'ali@metrovehicle.com',
                        'is_primary' => true,
                        'notes' => 'Main point of contact for operations',
                    ],
                    [
                        'name' => 'Rashida Begum',
                        'position' => 'Fleet Coordinator',
                        'phone' => '+880-1744-456789',
                        'email' => 'rashida@metrovehicle.com',
                        'is_primary' => false,
                        'notes' => 'Coordinates fleet maintenance schedules',
                    ],
                ],
            ],
            [
                'name' => 'Express Auto Workshop',
                'address' => '789 Industrial Zone, Sylhet-3100, Bangladesh',
                'phone' => '+880-821-987654',
                'email' => 'service@expressauto.bd',
                'website' => null,
                'description' => 'Quick and reliable automotive repair services.',
                'status' => 'active',
                'contacts' => [
                    [
                        'name' => 'Karim Uddin',
                        'position' => 'Workshop Supervisor',
                        'phone' => '+880-1755-567890',
                        'email' => 'karim@expressauto.bd',
                        'is_primary' => true,
                        'notes' => 'Supervises all workshop activities',
                    ],
                    [
                        'name' => 'Nasir Ahmed',
                        'position' => 'Technical Specialist',
                        'phone' => '+880-1766-678901',
                        'email' => 'nasir@expressauto.bd',
                        'is_primary' => false,
                        'notes' => 'Handles complex technical repairs',
                    ],
                ],
            ],
            [
                'name' => 'City Motors & Services',
                'address' => '321 Service Road, Rajshahi-6000, Bangladesh',
                'phone' => '+880-721-456789',
                'email' => 'info@citymotors.com.bd',
                'website' => 'https://www.citymotors.com.bd',
                'description' => 'Comprehensive vehicle servicing and parts supply.',
                'status' => 'active',
                'contacts' => [
                    [
                        'name' => 'Salma Khatun',
                        'position' => 'General Manager',
                        'phone' => '+880-1777-789012',
                        'email' => 'salma@citymotors.com.bd',
                        'is_primary' => true,
                        'notes' => 'Overall management and client relations',
                    ],
                    [
                        'name' => 'Ibrahim Hossain',
                        'position' => 'Parts Manager',
                        'phone' => '+880-1788-890123',
                        'email' => 'ibrahim@citymotors.com.bd',
                        'is_primary' => false,
                        'notes' => 'Manages parts inventory and procurement',
                    ],
                ],
            ],
            [
                'name' => 'Premium Auto Care',
                'address' => '654 Highway Plaza, Khulna-9000, Bangladesh',
                'phone' => '+880-41-321654',
                'email' => 'premium@autocare.net',
                'website' => null,
                'description' => 'Premium vehicle maintenance services for luxury cars.',
                'status' => 'inactive',
                'contacts' => [
                    [
                        'name' => 'Reza Khan',
                        'position' => 'Service Advisor',
                        'phone' => '+880-1799-901234',
                        'email' => 'reza@autocare.net',
                        'is_primary' => true,
                        'notes' => 'Advises on premium service packages',
                    ],
                    [
                        'name' => 'Nadia Rahman',
                        'position' => 'Quality Controller',
                        'phone' => '+880-1800-012345',
                        'email' => 'nadia@autocare.net',
                        'is_primary' => false,
                        'notes' => 'Ensures service quality standards',
                    ],
                ],
            ],
        ];

        foreach ($vendors as $vendorData) {
            $contacts = $vendorData['contacts'];
            unset($vendorData['contacts']);
            $vendorData['zone_id'] = Zone::idForCity($vendorData['address']);

            $vendor = Vendor::updateOrCreate(
                ['email' => $vendorData['email']],
                $vendorData
            );

            // Delete existing contacts and recreate to avoid duplicates
            $vendor->contactPersons()->delete();

            foreach ($contacts as $contactData) {
                $vendor->contactPersons()->create($contactData);
            }
        }
    }
}
