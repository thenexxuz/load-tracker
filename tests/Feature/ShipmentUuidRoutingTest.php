<?php

use App\Models\Carrier;
use App\Models\Location;
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
