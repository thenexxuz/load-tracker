<?php

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('administrator', 'web');
});

test('update offers accepts string carrier ids and stores offer attribution', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $pickup = Location::factory()->pickup()->create();
    $dc = Location::factory()->distribution_center()->create();
    $carrier = Carrier::factory()->create();

    $shipment = Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-OFFER-STR-001',
        'status' => 'Pending',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.shipments.update-offers', $shipment->guid), [
            'offered_carrier_ids' => [(string) $carrier->id],
        ])
        ->assertRedirect(route('admin.shipments.show', $shipment->guid))
        ->assertSessionHas('success', 'Shipment offers updated successfully.');

    $pivot = DB::table('carrier_shipment_offers')
        ->where('shipment_id', $shipment->id)
        ->where('carrier_id', $carrier->id)
        ->first();

    expect($pivot)->not->toBeNull();
    expect($pivot?->offered_by_user_id)->toBe($admin->id);
});
