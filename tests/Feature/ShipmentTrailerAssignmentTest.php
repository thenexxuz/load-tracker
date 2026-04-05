<?php

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\Trailer;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::create(['name' => 'administrator']);
});

it('clears the trailer assignment and parks the trailer at the pickup location when the carrier is removed', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $carrier = Carrier::factory()->create();
    $pickupLocation = Location::factory()->pickup()->create();
    $dcLocation = Location::factory()->distribution_center()->create();
    $trailer = Trailer::query()->create([
        'guid' => (string) str()->uuid(),
        'number' => 'TRL-100',
        'carrier_id' => $carrier->id,
        'status' => 'in_use',
        'is_active' => true,
    ]);

    $shipment = Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-100',
        'status' => 'Pending',
        'pickup_location_id' => $pickupLocation->id,
        'dc_location_id' => $dcLocation->id,
        'carrier_id' => $carrier->id,
        'trailer_id' => $trailer->id,
    ]);

    $response = $this->actingAs($admin)->put(route('admin.shipments.update', $shipment), [
        'shipment_number' => $shipment->shipment_number,
        'bol' => $shipment->bol,
        'po_number' => $shipment->po_number,
        'status' => $shipment->status,
        'pickup_location_id' => $pickupLocation->id,
        'dc_location_id' => $dcLocation->id,
        'carrier_id' => null,
        'offered_carrier_ids' => [],
        'trailer_id' => $trailer->id,
        'loaned_from_trailer_id' => null,
        'drop_date' => null,
        'pickup_date' => null,
        'delivery_date' => null,
        'rack_qty' => 0,
        'load_bar_qty' => 0,
        'strap_qty' => 0,
        'trailer' => null,
        'drayage' => null,
        'on_site' => null,
        'shipped' => null,
        'recycling_sent' => null,
        'paperwork_sent' => null,
        'delivery_alert_sent' => null,
        'crossed' => null,
        'seal_number' => null,
        'drivers_id' => null,
    ]);

    $response->assertRedirect(route('admin.shipments.show', $shipment));

    expect($shipment->fresh())
        ->carrier_id->toBeNull()
        ->trailer_id->toBeNull();

    expect($trailer->fresh())
        ->current_location_id->toBe($pickupLocation->id)
        ->status->toBe('available');
});

it('shows unassigned parked trailers on the carrier page', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $carrier = Carrier::factory()->create(['name' => 'Carrier One']);
    $pickupLocation = Location::factory()->pickup()->create(['name' => 'North Yard', 'short_code' => 'NYD']);

    $trailer = Trailer::query()->create([
        'guid' => (string) str()->uuid(),
        'number' => 'TRL-200',
        'carrier_id' => $carrier->id,
        'current_location_id' => $pickupLocation->id,
        'status' => 'available',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.carriers.show', $carrier));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('carrier.id', $carrier->id)
        ->has('activeTrailerAssignments', 1)
        ->where('activeTrailerAssignments.0.trailer_number', $trailer->number)
        ->where('activeTrailerAssignments.0.shipment_number', null)
        ->where('activeTrailerAssignments.0.pickup_location_name', $pickupLocation->name)
        ->where('activeTrailerAssignments.0.pickup_location_short_code', $pickupLocation->short_code)
        ->where('activeTrailerAssignments.0.is_assigned_to_shipment', false)
    );
});

it('provides existing statuses on the shipment edit page', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $pickupLocation = Location::factory()->pickup()->create();
    $dcLocation = Location::factory()->distribution_center()->create();

    $shipment = Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-300',
        'status' => 'Pending',
        'pickup_location_id' => $pickupLocation->id,
        'dc_location_id' => $dcLocation->id,
    ]);

    Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-301',
        'status' => 'Custom Hold',
        'pickup_location_id' => $pickupLocation->id,
        'dc_location_id' => $dcLocation->id,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.shipments.edit', $shipment));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Shipments/Edit')
        ->where('shipment.id', $shipment->guid)
        ->has('statuses', 2)
        ->where('statuses.0', 'Custom Hold')
        ->where('statuses.1', 'Pending')
    );
});
