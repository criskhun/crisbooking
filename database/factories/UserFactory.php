<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
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
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'password_set_at' => now(),
            'remember_token' => Str::random(10),
            'is_admin' => false,
            'is_active' => true,
            'role' => 'client',
            'phone' => '09171234567',
            'date_of_birth' => '1990-01-01',
            'nationality' => 'Filipino',
            'address' => '123 Test Street',
            'country' => 'Philippines',
            'province' => 'Metro Manila',
            'city' => 'Manila',
            'barangay' => 'Ermita',
            'bio' => 'A responsible member of the booking community.',
            'emergency_contact_name' => 'Emergency Contact',
            'emergency_contact_phone' => '09179876543',
            'government_id_type' => 'drivers_license',
            'government_id_number' => 'TEST-'.Str::random(8),
            'government_id_path' => 'identity-documents/testing/sample-id.jpg',
            'profile_completed_at' => now(),
        ];
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

    public function host(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'host']);
    }

    public function incompleteProfile(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => null,
            'date_of_birth' => null,
            'nationality' => null,
            'address' => null,
            'country' => null,
            'province' => null,
            'city' => null,
            'barangay' => null,
            'bio' => null,
            'emergency_contact_name' => null,
            'emergency_contact_phone' => null,
            'government_id_type' => null,
            'government_id_number' => null,
            'government_id_path' => null,
            'profile_completed_at' => null,
        ]);
    }
}
