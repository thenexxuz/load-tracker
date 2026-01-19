<?php

namespace Database\Factories;

use App\Models\Carrier;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ShipmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'guid' => (string) Str::uuid(),
            'shipment_number' => 'SHIP-'.$this->faker->unique()->numberBetween(10000, 99999),
            'bol' => $this->faker->optional()->ean13,
            'po_number' => $this->faker->optional()->numerify('PO-#####'),
            'status' => $this->faker->randomElement(['pending', 'picked_up', 'in_transit', 'delivered']),
            'shipper_location_id' => Location::factory(),
            'dc_location_id' => Location::factory(),
            'carrier_id' => Carrier::factory(),
            'drop_date' => $this->faker->optional()->date(),
            'pickup_date' => $this->faker->optional()->dateTimeThisYear(),
            'delivery_date' => $this->faker->optional()->dateTimeThisYear(),
            'rack_qty' => $this->faker->numberBetween(0, 200),
            'load_bar_qty' => $this->faker->numberBetween(0, 50),
            'strap_qty' => $this->faker->numberBetween(0, 100),
            'trailer' => $this->faker->optional()->word.$this->faker->numberBetween(1000, 9999),
            'drayage' => $this->faker->boolean(30),
            'on_site' => $this->faker->boolean(20),
            'shipped' => $this->faker->boolean(40),
            'recycling_sent' => $this->faker->boolean(10),
            'paperwork_sent' => $this->faker->boolean(15),
            'delivery_alert_sent' => $this->faker->boolean(25),
        ];
    }
}
