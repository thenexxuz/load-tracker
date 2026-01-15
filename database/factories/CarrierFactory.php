<?php

namespace Database\Factories;

use App\Models\Carrier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Carrier>
 */
class CarrierFactory extends Factory
{
    public function definition(): array
    {
        $companyName = fake()->company();

        return [
            'guid' => (string) Str::uuid(),
            'short_code' => strtoupper(fake()->lexify('???').'-'.fake()->randomElement(['TR', 'FTL', 'LTL', 'REF', 'DRY', 'INT'])),
            'name' => $companyName,
            'emails' => implode(', ', [
                fake()->safeEmail(),
                fake()->safeEmail(),
                fake()->optional(0.7)->safeEmail(), // ~70% chance of 3rd email
            ]),
            'is_active' => fake()->boolean(92), // most carriers are active
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    // State: Inactive carrier
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
