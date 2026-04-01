<?php

use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('administrator', 'web');
});

test('locations index exposes guid as the public id', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $location = Location::factory()->pickup()->create([
        'guid' => (string) str()->uuid(),
        'short_code' => 'ATX',
        'name' => 'Austin Pickup',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.locations.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Locations/Index')
            ->where('locations.data.0.id', $location->guid)
            ->where('locations.data.0.short_code', 'ATX')
            ->where('locations.data.0.name', 'Austin Pickup')
        );
});

test('location show and edit pages resolve locations by guid', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $location = Location::factory()->pickup()->create([
        'guid' => (string) str()->uuid(),
        'short_code' => 'DAL',
        'name' => 'Dallas Pickup',
    ]);

    $dc = Location::factory()->distribution_center()->create();

    $shipment = Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-LOC-001',
        'status' => 'Pending',
        'pickup_location_id' => $location->id,
        'dc_location_id' => $dc->id,
        'consolidation_number' => 'CONSOL-LOC-001',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.locations.show', $location->guid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Locations/Show')
            ->where('location.id', $location->guid)
            ->where('location.short_code', 'DAL')
            ->where('shipments.0.id', $shipment->guid)
            ->where('shipments.0.consolidation_number', 'CONSOL-LOC-001')
        );

    $this->actingAs($admin)
        ->get(route('admin.locations.edit', $location->guid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Locations/Edit')
            ->where('location.id', $location->guid)
            ->where('location.short_code', 'DAL')
        );
});

test('location show keeps consolidated pickup shipments adjacent', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $location = Location::factory()->pickup()->create();
    $dc = Location::factory()->distribution_center()->create();

    Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-LOC-GROUP-A',
        'status' => 'Pending',
        'consolidation_number' => 'CONSOL-LOC-GROUP-001',
        'pickup_location_id' => $location->id,
        'dc_location_id' => $dc->id,
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-LOC-SOLO-001',
        'status' => 'Pending',
        'pickup_location_id' => $location->id,
        'dc_location_id' => $dc->id,
        'created_at' => now()->subMinutes(2),
        'updated_at' => now()->subMinutes(2),
    ]);

    Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-LOC-GROUP-B',
        'status' => 'Pending',
        'consolidation_number' => 'CONSOL-LOC-GROUP-001',
        'pickup_location_id' => $location->id,
        'dc_location_id' => $dc->id,
        'created_at' => now()->subMinutes(3),
        'updated_at' => now()->subMinutes(3),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.locations.show', $location->guid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Locations/Show')
            ->where('shipments.0.shipment_number', 'SHIP-LOC-GROUP-A')
            ->where('shipments.1.shipment_number', 'SHIP-LOC-GROUP-B')
            ->where('shipments.2.shipment_number', 'SHIP-LOC-SOLO-001')
        );
});
