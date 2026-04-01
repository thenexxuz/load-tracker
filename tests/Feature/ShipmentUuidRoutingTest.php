<?php

use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('administrator', 'web');
});

test('shipments index exposes guid as the public id', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $pickup = Location::factory()->pickup()->create();
    $dc = Location::factory()->distribution_center()->create();

    $shipment = Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-UUID-001',
        'status' => 'Pending',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.shipments.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Shipments/Index')
            ->where('shipments.data.0.id', $shipment->guid)
            ->where('shipments.data.0.shipment_number', 'SHIP-UUID-001')
        );
});

test('shipment show and edit pages resolve shipments by guid', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $pickup = Location::factory()->pickup()->create([
        'guid' => (string) str()->uuid(),
    ]);
    $dc = Location::factory()->distribution_center()->create([
        'guid' => (string) str()->uuid(),
    ]);

    $shipment = Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-UUID-002',
        'status' => 'Booked',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.shipments.show', $shipment->guid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Shipments/Show')
            ->where('shipment.id', $shipment->guid)
            ->where('shipment.notable_id', $shipment->id)
            ->where('shipment.shipment_number', 'SHIP-UUID-002')
        );

    $this->actingAs($admin)
        ->get(route('admin.shipments.edit', $shipment->guid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Shipments/Edit')
            ->where('shipment.id', $shipment->guid)
            ->where('shipment.pickup_location_id', $pickup->guid)
            ->where('shipment.dc_location_id', $dc->guid)
        );
});
