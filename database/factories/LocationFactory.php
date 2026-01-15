<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        $city = fake()->city();
        $state = fake()->stateAbbr();
        $shortCode = strtoupper(fake()->lexify('??').'-'.fake()->randomElement(['N', 'S', 'E', 'W', 'C', 'DW', 'INT']));

        $locationTypes = [
            'pickup',
            'distribution_center',
            'recycling',
        ];

        return [
            'guid' => (string) Str::uuid(),
            'short_code' => $shortCode,
            'name' => fake()->company().' '.fake()->randomElement(['Facility', 'Terminal', 'Hub', 'Yard', 'Depot']),
            'address' => fake()->streetAddress(),
            'city' => $city,
            'state' => $state,
            'zip' => fake()->postcode(),
            'country' => 'US',

            'type' => fake()->randomElement($locationTypes),

            'latitude' => fake()->latitude(25, 49),
            'longitude' => fake()->longitude(-125, -66),
            'is_active' => fake()->boolean(90),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    // State: Only active Pickup locations
    public function pickup(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'pickup',
            'is_active' => true,
        ]);
    }

    // State: Distribution Center locations
    public function distribution_center(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'distribution_center',
            'short_code' => strtoupper(fake()->lexify('???')),
        ]);
    }

    // State: Inactive
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
