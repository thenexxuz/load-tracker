<?php

namespace Database\Factories;

use App\Models\AutomatedItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AutomatedItem>
 */
class AutomatedItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $monitorableKeys = array_keys(AutomatedItem::monitorableMap());
        $monitorableKey = fake()->randomElement($monitorableKeys);
        $monitorableClass = AutomatedItem::classForKey($monitorableKey);
        $availableFields = AutomatedItem::monitorableFieldsByKey()[$monitorableKey] ?? [];

        return [
            'name' => fake()->words(3, true),
            'monitorable_type' => $monitorableClass,
            'monitored_fields' => fake()->randomElements($availableFields, max(1, min(2, count($availableFields)))),
            'role_name' => 'administrator',
            'is_active' => true,
        ];
    }
}
