<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // These are hardcoded demo/test accounts with a well-known password.
        // Never let them land on a shared or production database.
        if (app()->isProduction()) {
            $this->command?->warn(
                'Skipping UserSeeder demo accounts in production. '.
                'Create the initial admin manually (e.g. via `php artisan setup:permissions`) with a strong, unique password.'
            );

            return;
        }

        // Create Admin User
        $admin = User::updateOrCreate(
            ['email' => 'admin@vems.com'],
            [
                'name' => 'System Administrator',
                'username' => 'admin',
                'employee_id' => 'ADM001',
                'user_type' => 'admin',
                'department_id' => 3, // Administration department
                'official_phone' => '+8801711000001',
                'personal_phone' => '+8801855000001',
                'emergency_phone' => '+8801999000001',
                'emergency_contact_name' => 'Admin Emergency Contact',
                'emergency_contact_relation' => 'Family',
                'present_address' => 'Admin Residence, Dhaka',
                'permanent_address' => 'Admin Permanent Address, Dhaka',
                'joining_date' => '2020-01-01',
                'status' => 'active',
                'blood_group' => 'O+',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
        );


        $users = [
            [
                'name' => 'Tawseq Siraj Chowdhury',
                'username' => 'tawseq.siraj',
                'employee_id' => '302',
                'email' => 'tawseq.siraj@rsc-bd.org',
                'department_id' => 3, // Administration department
                'official_phone' => '+8801713369165',
                'personal_phone' => '+8801713369165',
                'emergency_phone' => '+8801713369165',
                'emergency_contact_name' => 'Tawseq Emergency Contact',
                'emergency_contact_relation' => 'Family',
                'present_address' => 'Tawseq Residence, Dhaka',
                'permanent_address' => 'Tawseq Permanent Address, Dhaka',
                'joining_date' => '2020-01-01',
                'status' => 'active',
                'blood_group' => 'O+',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Lawrence Corraya',
                'username' => 'lawrence.corraya',
                'employee_id' => '003',
                'email' => 'lawrence.corraya@rsc-bd.org',
                'department_id' => 3, // Administration department
                'official_phone' => '+8801766695905',
                'personal_phone' => '+8801766695905',
                'emergency_phone' => '+8801766695905',
                'emergency_contact_name' => 'Lawrence Emergency Contact',
                'emergency_contact_relation' => 'Family',
                'present_address' => 'Lawrence Residence, Dhaka',
                'permanent_address' => 'Lawrence Permanent Address, Dhaka',
                'joining_date' => '2020-01-01',
                'status' => 'active',
                'blood_group' => 'O+',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Md. Al-Hasan Sarker',
                'username' => 'hasan.sarker',
                'employee_id' => '281',
                'email' => 'hasan.sarker@rsc-bd.org',
                'department_id' => 3, // Administration department
                'official_phone' => '+8801700710172',
                'personal_phone' => '+8801700710172',
                'emergency_phone' => '+8801700710172',
                'emergency_contact_name' => 'Md. Al-Hasan Emergency Contact',
                'emergency_contact_relation' => 'Family',
                'present_address' => 'Md. Al-Hasan Residence, Dhaka',
                'permanent_address' => 'Md. Al-Hasan Permanent Address, Dhaka',
                'joining_date' => '2020-01-01',
                'status' => 'active',
                'blood_group' => 'O+',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Khadiza Rahman',
                'username' => 'khadiza.rahman',
                'employee_id' => '053',
                'email' => 'khadiza.rahman@rsc-bd.org',
                'department_id' => 3, // Administration department
                'official_phone' => '+8801766695906',
                'personal_phone' => '+8801766695906',
                'emergency_phone' => '+8801766695906',
                'emergency_contact_name' => 'Khadiza Emergency Contact',
                'emergency_contact_relation' => 'Family',
                'present_address' => 'Khadiza Residence, Dhaka',
                'permanent_address' => 'Khadiza Permanent Address, Dhaka',
                'joining_date' => '2020-01-01',
                'status' => 'active',
                'blood_group' => 'O+',
                'password' => Hash::make('password'),
            ],
        ];


        //Create other users
        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'username' => $userData['username'],
                    'employee_id' => $userData['employee_id'],
                    'user_type' => 'employee',
                    'department_id' => $userData['department_id'],
                    'official_phone' => $userData['official_phone'],
                    'personal_phone' => $userData['personal_phone'],
                    'emergency_phone' => $userData['emergency_phone'],
                    'emergency_contact_name' => $userData['emergency_contact_name'],
                    'emergency_contact_relation' => $userData['emergency_contact_relation'],
                    'present_address' => $userData['present_address'],
                    'permanent_address' => $userData['permanent_address'],
                    'joining_date' => $userData['joining_date'],
                    'status' => $userData['status'],
                    'blood_group' => $userData['blood_group'],
                    'email_verified_at' => now(),
                    'password' => $userData['password'],
                ]
            );
        }



        // Create Transport Manager
        $transportManager = User::updateOrCreate(
            ['email' => 'transport.manager@vems.com'],
            [
                'name' => 'Sarah Transport Manager',
                'username' => 'sarah.manager',
                'employee_id' => 'TM001',
                'user_type' => 'transport_manager',
                'department_id' => 1, // Transport department
                'official_phone' => '+8801711000002',
                'personal_phone' => '+8801855000002',
                'emergency_phone' => '+8801999000002',
                'emergency_contact_name' => 'Manager Emergency Contact',
                'emergency_contact_relation' => 'Spouse',
            'driving_license_no' => 'DL-TM-001',
            'license_class' => 'B',
            'license_issue_date' => '2018-03-15',
            'license_expiry_date' => '2026-03-15',
            'driver_status' => 'available',
            'present_address' => 'Transport Manager Residence, Chittagong',
            'permanent_address' => 'Transport Manager Permanent, Chittagong',
            'joining_date' => '2021-02-15',
            'blood_group' => 'A+',
            'total_distance_covered' => 25000.75,
            'total_trips_completed' => 200,
            'average_rating' => 4.8,
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        // Create Senior Drivers
        $seniorDrivers = [
            [
                'name' => 'Ahmed Senior Driver',
                'username' => 'ahmed.driver',
                'employee_id' => 'DRV001',
                'email' => 'ahmed.driver@vems.com',
                'license_no' => 'DL-DRV-001',
                'phone_suffix' => '003',
                'blood_group' => 'B+',
                'address' => 'Senior Driver Area, Dhaka'
            ],
            [
                'name' => 'Karim Senior Driver',
                'username' => 'karim.driver',
                'employee_id' => 'DRV002',
                'email' => 'karim.driver@vems.com',
                'license_no' => 'DL-DRV-002',
                'phone_suffix' => '004',
                'blood_group' => 'AB+',
                'address' => 'Senior Driver Area, Sylhet'
            ]
        ];

        foreach ($seniorDrivers as $driverData) {
            User::updateOrCreate(
                ['email' => $driverData['email']],
                [
                    'name' => $driverData['name'],
                    'username' => $driverData['username'],
                    'employee_id' => $driverData['employee_id'],
                    'user_type' => 'driver',
                    'department_id' => 1, // Transport department
                    'official_phone' => '+880171100000' . $driverData['phone_suffix'],
                    'personal_phone' => '+880185500000' . $driverData['phone_suffix'],
                    'emergency_phone' => '+880199900000' . $driverData['phone_suffix'],
                    'emergency_contact_name' => $driverData['name'] . ' Emergency',
                    'emergency_contact_relation' => 'Family',
                    'driving_license_no' => $driverData['license_no'],
                    'license_class' => 'B',
                    'license_issue_date' => '2019-06-01',
                    'license_expiry_date' => '2027-06-01',
                    'driver_status' => 'available',
                    'present_address' => $driverData['address'],
                    'permanent_address' => $driverData['address'] . ' (Permanent)',
                    'joining_date' => '2022-03-01',
                    'blood_group' => $driverData['blood_group'],
                    'total_distance_covered' => rand(15000, 30000),
                    'total_trips_completed' => rand(150, 300),
                    'average_rating' => round(rand(40, 50) / 10, 1),
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                ]
            );
        }

        // Create Regular Drivers
        $regularDrivers = [
            [
                'name' => 'Rahim Regular Driver',
                'username' => 'rahim.driver',
                'employee_id' => 'DRV003',
                'email' => 'rahim.driver@vems.com',
                'license_no' => 'DL-DRV-003',
                'phone_suffix' => '005',
                'blood_group' => 'O-',
                'address' => 'Driver Colony, Dhaka'
            ],
            [
                'name' => 'Hasan Regular Driver',
                'username' => 'hasan.driver',
                'employee_id' => 'DRV004',
                'email' => 'hasan.driver@vems.com',
                'license_no' => 'DL-DRV-004',
                'phone_suffix' => '006',
                'blood_group' => 'A-',
                'address' => 'Driver Area, Chittagong'
            ]
        ];

        foreach ($regularDrivers as $driverData) {
            User::updateOrCreate(
                ['email' => $driverData['email']],
                [
                    'name' => $driverData['name'],
                    'username' => $driverData['username'],
                    'employee_id' => $driverData['employee_id'],
                    'user_type' => 'driver',
                    'department_id' => 1, // Transport department
                    'official_phone' => '+880171100000' . $driverData['phone_suffix'],
                    'personal_phone' => '+880185500000' . $driverData['phone_suffix'],
                    'emergency_phone' => '+880199900000' . $driverData['phone_suffix'],
                    'emergency_contact_name' => $driverData['name'] . ' Emergency',
                    'emergency_contact_relation' => 'Family',
                    'driving_license_no' => $driverData['license_no'],
                    'license_class' => 'B',
                    'license_issue_date' => '2020-08-15',
                    'license_expiry_date' => '2028-08-15',
                    'driver_status' => 'available',
                    'present_address' => $driverData['address'],
                    'permanent_address' => $driverData['address'] . ' (Permanent)',
                    'joining_date' => '2023-01-15',
                    'blood_group' => $driverData['blood_group'],
                    'total_distance_covered' => rand(8000, 15000),
                    'total_trips_completed' => rand(80, 150),
                    'average_rating' => round(rand(35, 45) / 10, 1),
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                ]
            );
        }

        // Create Employees from different departments
        $employees = [
            [
                'name' => 'John HR Employee',
                'username' => 'john.hr',
                'employee_id' => 'HR001',
                'email' => 'john.hr@vems.com',
                'department_id' => 2, // HR department
                'phone_suffix' => '007',
                'address' => 'HR Staff Quarters, Dhaka'
            ],
            [
                'name' => 'Lisa Finance Employee',
                'username' => 'lisa.finance',
                'employee_id' => 'FIN001',
                'email' => 'lisa.finance@vems.com',
                'department_id' => 5, // Finance department
                'phone_suffix' => '008',
                'address' => 'Finance Staff Area, Dhaka'
            ],
            [
                'name' => 'David Operations Employee',
                'username' => 'david.operations',
                'employee_id' => 'OPS001',
                'email' => 'david.operations@vems.com',
                'department_id' => 4, // Operations department
                'phone_suffix' => '009',
                'address' => 'Operations Staff Area, Chittagong'
            ],
            [
                'name' => 'Maria Admin Employee',
                'username' => 'maria.admin',
                'employee_id' => 'ADM002',
                'email' => 'maria.admin@vems.com',
                'department_id' => 3, // Administration department
                'phone_suffix' => '010',
                'address' => 'Admin Staff Area, Sylhet'
            ]
        ];

        foreach ($employees as $empData) {
            User::updateOrCreate(
                ['email' => $empData['email']],
                [
                    'name' => $empData['name'],
                    'username' => $empData['username'],
                    'employee_id' => $empData['employee_id'],
                    'user_type' => 'employee',
                    'department_id' => $empData['department_id'],
                    'official_phone' => '+880171100000' . $empData['phone_suffix'],
                    'personal_phone' => '+880185500000' . $empData['phone_suffix'],
                    'emergency_phone' => '+880199900000' . $empData['phone_suffix'],
                    'emergency_contact_name' => $empData['name'] . ' Emergency',
                    'emergency_contact_relation' => 'Family',
                    'present_address' => $empData['address'],
                    'permanent_address' => $empData['address'] . ' (Permanent)',
                    'joining_date' => '2023-' . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT),
                    'blood_group' => ['A+', 'B+', 'AB+', 'O+', 'A-', 'B-', 'AB-', 'O-'][rand(0, 7)],
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                ]
            );
        }

        // Assign Spatie Roles (if roles exist)
        try {
            if (Role::where('name', 'super-admin')->exists()) {
                $admin->assignRole('super-admin');
            }

            if (Role::where('name', 'admin')->exists()) {
                $transportManager->assignRole('admin');
            }


            if (Role::where('name', 'driver')->exists()) {
                $drivers = User::where('user_type', 'driver')->get();
                foreach ($drivers as $driver) {
                    $driver->assignRole('driver');
                }
            }

            if (Role::where('name', 'employee')->exists()) {
                $employees = User::where('user_type', 'employee')->get();
                foreach ($employees as $employee) {
                    $employee->assignRole('employee');
                }
            }
        } catch (\Exception $e) {
            // Roles might not exist yet, continue without assigning
        }

        echo "vems Users created successfully:\n";
        echo "- 1 Admin (admin/password)\n";
        echo "- 1 Transport Manager (sarah.manager/password)\n";
        echo "- 4 Drivers (ahmed.driver, karim.driver, rahim.driver, hasan.driver/password)\n";
        echo "- 4 Employees from different departments (john.hr, lisa.finance, david.operations, maria.admin/password)\n";
    }
}
