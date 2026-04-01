<?php

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Rate;
use App\Models\Shipment;
use App\Models\Trailer;
use App\Models\User;
use Illuminate\Support\Str;
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
        'consolidation_number' => 'CONSOL-001',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.shipments.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Shipments/Index')
            ->where('shipments.data.0.id', $shipment->guid)
            ->where('shipments.data.0.shipment_number', 'SHIP-UUID-001')
            ->where('shipments.data.0.consolidation_number', 'CONSOL-001')
        );
});

test('shipments index keeps consolidated shipments adjacent within the list', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $pickup = Location::factory()->pickup()->create();
    $dc = Location::factory()->distribution_center()->create();

    Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-GROUP-A',
        'status' => 'Pending',
        'consolidation_number' => 'CONSOL-GROUP-001',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-SOLO-001',
        'status' => 'Pending',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
        'created_at' => now()->subMinutes(2),
        'updated_at' => now()->subMinutes(2),
    ]);

    Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-GROUP-B',
        'status' => 'Pending',
        'consolidation_number' => 'CONSOL-GROUP-001',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
        'created_at' => now()->subMinutes(3),
        'updated_at' => now()->subMinutes(3),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.shipments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Shipments/Index')
            ->where('shipments.data.0.shipment_number', 'SHIP-GROUP-A')
            ->where('shipments.data.1.shipment_number', 'SHIP-GROUP-B')
            ->where('shipments.data.2.shipment_number', 'SHIP-SOLO-001')
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

test('shipment show displays rates for same pickup when destination is within 60 miles of shipment dc', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $pickup = Location::factory()->pickup()->create();
    $dc = Location::factory()->distribution_center()->create([
        'city' => 'Austin',
        'state' => 'TX',
        'country' => 'US',
        'latitude' => 30.2672,
        'longitude' => -97.7431,
    ]);

    $shipment = Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-RATE-001',
        'status' => 'Booked',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
    ]);

    Location::factory()->distribution_center()->create([
        'city' => 'Far City',
        'state' => 'TX',
        'country' => 'US',
        'latitude' => 35.4676,
        'longitude' => -97.5164,
    ]);

    $matchingRate = Rate::query()->create([
        'name' => 'Linehaul',
        'type' => 'per_mile',
        'pickup_location_id' => $pickup->id,
        'destination_city' => 'Austin',
        'destination_state' => 'TX',
        'destination_country' => 'US',
        'carrier_id' => null,
        'rate' => 2.25,
    ]);

    Rate::query()->create([
        'name' => 'Linehaul Far',
        'type' => 'per_mile',
        'pickup_location_id' => $pickup->id,
        'destination_city' => 'Far City',
        'destination_state' => 'TX',
        'destination_country' => 'US',
        'carrier_id' => null,
        'rate' => 2.5,
    ]);

    $otherPickup = Location::factory()->pickup()->create();
    Rate::query()->create([
        'name' => 'Wrong Pickup',
        'type' => 'per_mile',
        'pickup_location_id' => $otherPickup->id,
        'destination_city' => 'Austin',
        'destination_state' => 'TX',
        'destination_country' => 'US',
        'carrier_id' => null,
        'rate' => 2.0,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.shipments.show', $shipment->guid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Shipments/Show')
            ->has('rates', 1)
            ->where('rates.0.id', $matchingRate->id)
        );
});

test('shipment show still includes rates without start or end lane fields', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $pickup = Location::factory()->pickup()->create();
    $dc = Location::factory()->distribution_center()->create([
        'city' => 'Austin',
        'state' => 'TX',
        'country' => 'US',
        'latitude' => 30.2672,
        'longitude' => -97.7431,
    ]);

    $shipment = Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-RATE-002',
        'status' => 'Booked',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
    ]);

    $globalRate = Rate::query()->create([
        'name' => 'Global Fallback',
        'type' => 'flat',
        'pickup_location_id' => null,
        'destination_city' => null,
        'destination_state' => null,
        'destination_country' => null,
        'carrier_id' => null,
        'rate' => 125.00,
    ]);

    $farPickupRate = Rate::query()->create([
        'name' => 'Far Pickup Only',
        'type' => 'flat',
        'pickup_location_id' => Location::factory()->pickup()->create()->id,
        'destination_city' => null,
        'destination_state' => null,
        'destination_country' => null,
        'carrier_id' => null,
        'rate' => 150.00,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.shipments.show', $shipment->guid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Shipments/Show')
            ->where('rates.0.id', $globalRate->id)
            ->where('rates.1.id', $farPickupRate->id)
        );
});

test('administrators can consolidate and unconsolidate shipments on the same lane', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $pickup = Location::factory()->pickup()->create();
    $dc = Location::factory()->distribution_center()->create();

    $carrier = Carrier::factory()->create();
    $trailer = Trailer::query()->create([
        'guid' => (string) Str::uuid(),
        'number' => 'TRL-1001',
        'carrier_id' => $carrier->id,
        'status' => 'available',
        'is_active' => true,
    ]);

    $shipmentA = Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-CONSOL-A',
        'status' => 'Booked',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
        'carrier_id' => $carrier->id,
        'trailer_id' => $trailer->id,
        'trailer' => 'TRL-1001',
        'rack_qty' => 3,
        'load_bar_qty' => 5,
        'strap_qty' => 8,
    ]);

    $shipmentB = Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-CONSOL-B',
        'status' => 'Booked',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
        'rack_qty' => 4,
        'load_bar_qty' => 6,
        'strap_qty' => 9,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.shipments.update-consolidation', $shipmentA->guid), [
            'consolidated_shipment_ids' => [$shipmentB->guid],
        ])
        ->assertRedirect(route('admin.shipments.show', $shipmentA->guid));

    $shipmentA->refresh();
    $shipmentB->refresh();

    expect($shipmentA->consolidation_number)->not->toBeNull();
    expect($shipmentB->consolidation_number)->toBe($shipmentA->consolidation_number)
        ->and($shipmentB->carrier_id)->toBe($shipmentA->carrier_id)
        ->and($shipmentB->trailer_id)->toBe($shipmentA->trailer_id);

    $this->actingAs($admin)
        ->get(route('admin.shipments.show', $shipmentB->guid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('consolidationData.members.0.shipment_number', 'SHIP-CONSOL-A')
            ->where('consolidationData.members.1.shipment_number', 'SHIP-CONSOL-B')
            ->where('consolidationData.totals.rack_qty', 7)
            ->where('consolidationData.totals.load_bar_qty', 11)
            ->where('consolidationData.totals.strap_qty', 17)
        );

    $this->actingAs($admin)
        ->put(route('admin.shipments.update', $shipmentB->guid), [
            'shipment_number' => $shipmentB->shipment_number,
            'bol' => $shipmentB->bol,
            'po_number' => $shipmentB->po_number,
            'status' => $shipmentB->status,
            'pickup_location_id' => $pickup->guid,
            'dc_location_id' => $dc->guid,
            'carrier_id' => $shipmentB->carrier_id,
            'offered_carrier_ids' => [],
            'trailer_id' => $shipmentB->trailer_id,
            'loaned_from_trailer_id' => $shipmentB->loaned_from_trailer_id,
            'drop_date' => $shipmentB->drop_date?->format('Y-m-d'),
            'pickup_date' => $shipmentB->pickup_date?->format('Y-m-d H:i:s'),
            'delivery_date' => $shipmentB->delivery_date?->format('Y-m-d H:i:s'),
            'rack_qty' => $shipmentB->rack_qty,
            'load_bar_qty' => $shipmentB->load_bar_qty,
            'strap_qty' => $shipmentB->strap_qty,
            'trailer' => $shipmentB->trailer,
            'consolidation_number' => 'MANUAL-CONSOL-001',
            'drayage' => $shipmentB->drayage,
            'on_site' => $shipmentB->on_site,
            'shipped' => $shipmentB->shipped,
            'recycling_sent' => $shipmentB->recycling_sent,
            'paperwork_sent' => $shipmentB->paperwork_sent,
            'delivery_alert_sent' => $shipmentB->delivery_alert_sent,
            'crossed' => $shipmentB->crossed,
            'seal_number' => $shipmentB->seal_number,
            'drivers_id' => $shipmentB->drivers_id,
        ])
        ->assertRedirect(route('admin.shipments.show', $shipmentB->guid));

    $shipmentA->refresh();
    $shipmentB->refresh();

    expect($shipmentA->consolidation_number)->toBe('MANUAL-CONSOL-001')
        ->and($shipmentB->consolidation_number)->toBe('MANUAL-CONSOL-001');

    $this->actingAs($admin)
        ->patch(route('admin.shipments.update-consolidation', $shipmentA->guid), [
            'clear_consolidation' => true,
        ])
        ->assertRedirect(route('admin.shipments.show', $shipmentA->guid));

    $shipmentA->refresh();
    $shipmentB->refresh();

    expect($shipmentA->consolidation_number)->toBeNull()
        ->and($shipmentB->consolidation_number)->toBeNull();
});
