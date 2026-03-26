<?php

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::create(['name' => 'administrator']);
});

it('imports shipment changes from google sheets', function (): void {
    Http::fake([
        'docs.google.com/spreadsheets/*' => Http::response(implode("\n", [
            'Shipment Number,Status,PO Number,Origin,Destination,Pickup Date,Delivery Date,Sum of Pallets,Carrier,Trailer,Seal Number,Drivers ID',
            'LOAD-100,In Transit,PO-999,ING,AMS,2026-03-26 08:30,2026-03-27 10:00,5,Carrier Beta,TRL-900,SEAL-123,DRV-9',
        ]), 200, ['Content-Type' => 'text/csv; charset=utf-8']),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $oldPickup = Location::factory()->pickup()->create(['short_code' => 'OLDP', 'name' => 'Old Pickup']);
    $oldDc = Location::factory()->distribution_center()->create(['short_code' => 'OLDD', 'name' => 'Old DC']);
    $newPickup = Location::factory()->pickup()->create(['short_code' => 'ING', 'name' => 'Ingrasys']);
    $newDc = Location::factory()->distribution_center()->create(['short_code' => 'AMS', 'name' => 'AMS DC']);
    Carrier::factory()->create(['name' => 'Carrier Alpha', 'short_code' => 'ALPHA']);
    $newCarrier = Carrier::factory()->create(['name' => 'Carrier Beta', 'short_code' => 'BETA']);

    $shipment = Shipment::query()->create([
        'shipment_number' => 'LOAD-100',
        'status' => 'Pending',
        'po_number' => 'PO-100',
        'pickup_location_id' => $oldPickup->id,
        'dc_location_id' => $oldDc->id,
        'rack_qty' => 1,
        'load_bar_qty' => 1,
        'strap_qty' => 1,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.shipments.google-sheets-import'), [
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/test-sheet-id/edit#gid=0',
    ]);

    $response->assertRedirect(route('admin.shipments.index'));
    $response->assertSessionHas('success', fn (string $message) => str_contains($message, '1 shipment(s) updated from Google Sheets.'));

    expect($shipment->fresh())
        ->status->toBe('In Transit')
        ->po_number->toBe('PO-999')
        ->pickup_location_id->toBe($newPickup->id)
        ->dc_location_id->toBe($newDc->id)
        ->carrier_id->toBe($newCarrier->id)
        ->trailer->toBe('TRL-900')
        ->seal_number->toBe('SEAL-123')
        ->drivers_id->toBe('DRV-9')
        ->rack_qty->toBe(5)
        ->load_bar_qty->toBe(2)
        ->strap_qty->toBe(13);

    expect($shipment->notes()->where('content', 'like', 'Google Sheets import updated this shipment:%')->exists())->toBeTrue();
});

it('rejects private google sheets that redirect to sign in', function (): void {
    Http::fake([
        'docs.google.com/spreadsheets/*' => Http::response('<html><body>ServiceLogin</body></html>', 200, ['Content-Type' => 'text/html; charset=utf-8']),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $response = $this->actingAs($admin)->from(route('admin.shipments.index'))->post(route('admin.shipments.google-sheets-import'), [
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/private-sheet/edit#gid=0',
    ]);

    $response->assertRedirect(route('admin.shipments.index'));
    $response->assertSessionHasErrors('google_sheet_url');
});
