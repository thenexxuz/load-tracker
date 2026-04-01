<?php

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Rate;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('administrator', 'web');
});

test('rates index search matches across destination pickup and carrier fields', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $pickup = Location::factory()->pickup()->create([
        'short_code' => 'DAL-PU',
        'name' => 'Dallas Pickup',
    ]);

    $carrier = Carrier::factory()->create([
        'name' => 'Northwind Freight',
        'short_code' => 'NWF',
    ]);

    $matchingRate = Rate::query()->create([
        'name' => 'Primary Dallas Lane',
        'type' => 'per_mile',
        'pickup_location_id' => $pickup->id,
        'destination_city' => 'Dallas',
        'destination_state' => 'TX',
        'destination_country' => 'US',
        'carrier_id' => $carrier->id,
        'rate' => 2.50,
    ]);

    Rate::query()->create([
        'name' => 'Other Lane',
        'type' => 'flat',
        'pickup_location_id' => Location::factory()->pickup()->create()->id,
        'destination_city' => 'Chicago',
        'destination_state' => 'IL',
        'destination_country' => 'US',
        'carrier_id' => null,
        'rate' => 100.00,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.rates.index', ['search' => 'Dallas']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Rates/Index')
            ->has('rates.data', 1)
            ->where('rates.data.0.id', $matchingRate->id)
            ->where('filters.search', 'Dallas')
        );

    $this->actingAs($admin)
        ->get(route('admin.rates.index', ['search' => 'NWF']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Rates/Index')
            ->has('rates.data', 1)
            ->where('rates.data.0.id', $matchingRate->id)
        );

    $this->actingAs($admin)
        ->get(route('admin.rates.index', ['search' => 'DAL-PU']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Rates/Index')
            ->has('rates.data', 1)
            ->where('rates.data.0.id', $matchingRate->id)
        );
});
