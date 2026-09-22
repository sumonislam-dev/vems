<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $userType = fake()->randomElement(['admin', 'manager', 'employee', 'driver']);

        $data = [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'official_phone' => fake()->phoneNumber(),
            'personal_phone' => fake()->optional()->phoneNumber(),
            'emergency_phone' => fake()->optional()->phoneNumber(),
            'user_type' => $userType,
            'blood_group' => fake()->randomElement(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']),
            'status' => fake()->randomElement(['active', 'inactive', 'suspended']),
            'present_address' => fake()->optional()->address(),
            'permanent_address' => fake()->optional()->address(),
            'whatsapp_id' => fake()->optional()->phoneNumber(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];

        // Add driver-specific fields if user is a driver
        if ($userType === 'driver') {
            $data['driving_license_no'] = 'DL-' . fake()->unique()->numerify('########');
            $data['nid_number'] = fake()->unique()->numerify('#############');
            $data['present_address'] = fake()->address();
            $data['permanent_address'] = fake()->address();
            $data['emergency_contact_name'] = fake()->name();
            $data['emergency_phone'] = fake()->phoneNumber();
            $data['emergency_contact_relation'] = fake()->randomElement(['Father', 'Mother', 'Spouse', 'Brother', 'Sister', 'Friend']);
        }

        return $data;
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
