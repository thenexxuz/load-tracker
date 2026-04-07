<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) str()->uuid(),
            'type' => 'system',
            'data' => [
                'subject' => $this->faker->sentence(),
                'message' => $this->faker->paragraph(),
            ],
            'read_at' => null,
            'notifiable_type' => User::class,
            'notifiable_id' => 1,
            'created_at' => $this->faker->dateTimeBetween('-30 days'),
            'updated_at' => now(),
        ];
    }
}
