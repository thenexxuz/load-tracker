<?php

use App\Mail\NotificationEmail;
use App\Models\AppSetting;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Shipment;
use App\Models\Trailer;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::create(['name' => 'administrator']);
    Role::create(['name' => 'supervisor']);
    Role::create(['name' => 'carrier']);
});

it('imports shipment changes from google sheets', function (): void {
    $workbookContents = buildGoogleSheetsWorkbook([
        'Sheet 1' => [
            ['Shipment Number', 'Status', 'PO Number', 'Origin', 'Destination', 'Pickup Date', 'Delivery Date', 'Sum of Pallets', 'Carrier', 'Trailer Number', 'Seal Number', 'Drivers ID'],
            ['LOAD-100', 'In Transit', 'PO-999', 'ING', 'AMS', '2026-03-26 08:30', '2026-03-27 10:00', '5', 'Carrier Beta', 'TRL-900', 'SEAL-123', 'DRV-9'],
        ],
    ]);

    Http::fake([
        'docs.google.com/spreadsheets/*' => Http::response($workbookContents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]),
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

    $response = $this->actingAs($admin)
        ->from(route('admin.shipments.index'))
        ->post(route('admin.shipments.google-sheets-import'), [
            'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/test-sheet-id/edit#gid=0',
        ]);

    $response->assertRedirect(route('admin.shipments.index'));
    $response->assertSessionHas('success', fn (string $message) => str_contains($message, '1 shipment(s) updated from Google Sheets.'));

    $createdTrailer = Trailer::query()->where('number', 'TRL-900')->where('carrier_id', $newCarrier->id)->first();

    expect($createdTrailer)->not->toBeNull();

    expect($shipment->fresh())
        ->status->toBe('In Transit')
        ->po_number->toBe('PO-999')
        ->pickup_location_id->toBe($newPickup->id)
        ->dc_location_id->toBe($newDc->id)
        ->carrier_id->toBe($newCarrier->id)
        ->trailer_id->toBe($createdTrailer->id)
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

it('sends one supervisor notification per changed shipment during google sheets import', function (): void {
    $workbookContents = buildGoogleSheetsWorkbook([
        'Sheet 1' => [
            ['Shipment Number', 'Status', 'PO Number', 'Origin', 'Destination'],
            ['LOAD-910', 'In Transit', 'PO-910-NEW', 'ING', 'AMS'],
            ['LOAD-911', 'Booked', 'PO-911-NEW', 'ING', 'AMS'],
        ],
    ]);

    Http::fake([
        'docs.google.com/spreadsheets/*' => Http::response($workbookContents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $supervisorA = User::factory()->create();
    $supervisorA->assignRole('supervisor');
    $supervisorB = User::factory()->create();
    $supervisorB->assignRole('supervisor');

    $pickup = Location::factory()->pickup()->create(['short_code' => 'ING', 'name' => 'Ingrasys']);
    $dc = Location::factory()->distribution_center()->create(['short_code' => 'AMS', 'name' => 'AMS DC']);

    $shipmentOne = Shipment::query()->create([
        'shipment_number' => 'LOAD-910',
        'status' => 'Pending',
        'po_number' => 'PO-910-OLD',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
    ]);

    $shipmentTwo = Shipment::query()->create([
        'shipment_number' => 'LOAD-911',
        'status' => 'Pending',
        'po_number' => 'PO-911-OLD',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
    ]);

    $response = $this->actingAs($admin)
        ->from(route('admin.shipments.index'))
        ->post(route('admin.shipments.google-sheets-import'), [
            'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/test-sheet-id/edit#gid=0',
        ]);

    $response->assertRedirect(route('admin.shipments.index'));
    $response->assertSessionHas('success', fn (string $message) => str_contains($message, '2 shipment(s) updated from Google Sheets.'));

    $notifications = Notification::query()
        ->where('type', 'google_sheets_import')
        ->where('notifiable_type', Shipment::class)
        ->orderBy('created_at')
        ->get();

    expect($notifications)->toHaveCount(2);

    expect($notifications->pluck('notifiable_id')->all())
        ->toContain($shipmentOne->id)
        ->toContain($shipmentTwo->id);

    foreach ($notifications as $notification) {
        expect($notification->users()->pluck('users.id')->all())
            ->toContain($supervisorA->id)
            ->toContain($supervisorB->id);
    }
});

it('emails google sheets notifications only to users who enabled notification emails', function (): void {
    Mail::fake();

    $workbookContents = buildGoogleSheetsWorkbook([
        'Sheet 1' => [
            ['Shipment Number', 'Status', 'PO Number', 'Origin', 'Destination'],
            ['LOAD-930', 'In Transit', 'PO-930-NEW', 'ING', 'AMS'],
        ],
    ]);

    Http::fake([
        'docs.google.com/spreadsheets/*' => Http::response($workbookContents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $optedInSupervisor = User::factory()->create([
        'notification_email_enabled' => true,
    ]);
    $optedInSupervisor->assignRole('supervisor');

    $optedOutSupervisor = User::factory()->create([
        'notification_email_enabled' => false,
    ]);
    $optedOutSupervisor->assignRole('supervisor');

    $pickup = Location::factory()->pickup()->create(['short_code' => 'ING', 'name' => 'Ingrasys']);
    $dc = Location::factory()->distribution_center()->create(['short_code' => 'AMS', 'name' => 'AMS DC']);

    Shipment::query()->create([
        'shipment_number' => 'LOAD-930',
        'status' => 'Pending',
        'po_number' => 'PO-930-OLD',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
    ]);

    $response = $this->actingAs($admin)
        ->from(route('admin.shipments.index'))
        ->post(route('admin.shipments.google-sheets-import'), [
            'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/test-sheet-id/edit#gid=0',
        ]);

    $response->assertRedirect(route('admin.shipments.index'));

    Mail::assertSent(NotificationEmail::class, function (NotificationEmail $mail) use ($optedInSupervisor): bool {
        return $mail->hasTo($optedInSupervisor->email);
    });

    Mail::assertNotSent(NotificationEmail::class, function (NotificationEmail $mail) use ($optedOutSupervisor): bool {
        return $mail->hasTo($optedOutSupervisor->email);
    });
});

it('notifies only assigned carrier users when google sheets changes shipment dates', function (): void {
    $workbookContents = buildGoogleSheetsWorkbook([
        'Sheet 1' => [
            ['Shipment Number', 'Pickup Date', 'Delivery Date', 'Status'],
            ['LOAD-920', '2026-03-26 08:30', '2026-03-27 10:00', 'In Transit'],
        ],
    ]);

    Http::fake([
        'docs.google.com/spreadsheets/*' => Http::response($workbookContents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $carrier = Carrier::factory()->create(['name' => 'Carrier Match', 'short_code' => 'CM']);
    $otherCarrier = Carrier::factory()->create(['name' => 'Carrier Other', 'short_code' => 'CO']);

    $matchingCarrierUser = User::factory()->create();
    $matchingCarrierUser->assignRole('carrier');
    $matchingCarrierUser->forceFill(['carrier_id' => $carrier->id])->save();

    $otherCarrierUser = User::factory()->create();
    $otherCarrierUser->assignRole('carrier');
    $otherCarrierUser->forceFill(['carrier_id' => $otherCarrier->id])->save();

    $pickup = Location::factory()->pickup()->create(['short_code' => 'ING', 'name' => 'Ingrasys']);
    $dc = Location::factory()->distribution_center()->create(['short_code' => 'AMS', 'name' => 'AMS DC']);

    $shipment = Shipment::query()->create([
        'shipment_number' => 'LOAD-920',
        'status' => 'Pending',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
        'carrier_id' => $carrier->id,
        'pickup_date' => '2026-03-25 08:00:00',
        'delivery_date' => '2026-03-26 08:00:00',
    ]);

    $response = $this->actingAs($admin)
        ->from(route('admin.shipments.index'))
        ->post(route('admin.shipments.google-sheets-import'), [
            'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/test-sheet-id/edit#gid=0',
        ]);

    $response->assertRedirect(route('admin.shipments.index'));

    $notification = Notification::query()
        ->where('type', 'google_sheets_import_carrier_dates')
        ->where('notifiable_type', Shipment::class)
        ->where('notifiable_id', $shipment->id)
        ->latest('id')
        ->first();

    expect($notification)->not->toBeNull();

    expect($notification->users()->pluck('users.id')->all())
        ->toContain($matchingCarrierUser->id)
        ->not->toContain($otherCarrierUser->id);
});

it('uses the app settings google sheets url when request input is not provided', function (): void {
    AppSetting::setValue(
        AppSetting::GOOGLE_SHEET_URL_KEY,
        'https://docs.google.com/spreadsheets/d/test-sheet-id/edit#gid=0'
    );

    $workbookContents = buildGoogleSheetsWorkbook([
        'Sheet 1' => [
            ['Shipment Number', 'Status', 'PO Number', 'Origin', 'Destination', 'Pickup Date', 'Delivery Date', 'Sum of Pallets', 'Carrier', 'Trailer Number', 'Seal Number', 'Drivers ID'],
            ['LOAD-200', 'In Transit', 'PO-888', 'ING', 'AMS', '2026-03-26 08:30', '2026-03-27 10:00', '5', 'Carrier Beta', 'TRL-901', 'SEAL-124', 'DRV-10'],
        ],
    ]);

    Http::fake([
        'docs.google.com/spreadsheets/*' => Http::response($workbookContents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]),
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
        'shipment_number' => 'LOAD-200',
        'status' => 'Pending',
        'po_number' => 'PO-200',
        'pickup_location_id' => $oldPickup->id,
        'dc_location_id' => $oldDc->id,
        'rack_qty' => 1,
        'load_bar_qty' => 1,
        'strap_qty' => 1,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.shipments.google-sheets-import'), []);

    $response->assertRedirect(route('admin.shipments.index'));
    $response->assertSessionHas('success', fn (string $message) => str_contains($message, '1 shipment(s) updated from Google Sheets.'));

    $createdTrailer = Trailer::query()->where('number', 'TRL-901')->where('carrier_id', $newCarrier->id)->first();

    expect($createdTrailer)->not->toBeNull();

    expect($shipment->fresh())
        ->status->toBe('In Transit')
        ->po_number->toBe('PO-888')
        ->pickup_location_id->toBe($newPickup->id)
        ->dc_location_id->toBe($newDc->id)
        ->carrier_id->toBe($newCarrier->id)
        ->trailer_id->toBe($createdTrailer->id)
        ->trailer->toBe('TRL-901')
        ->seal_number->toBe('SEAL-124')
        ->drivers_id->toBe('DRV-10')
        ->rack_qty->toBe(5)
        ->load_bar_qty->toBe(2)
        ->strap_qty->toBe(13);
});

it('resolves a carrier by short_code when the import value is in mixed or lower case', function (): void {
    $workbookContents = buildGoogleSheetsWorkbook([
        'Sheet 1' => [
            ['Shipment Number', 'Status', 'PO Number', 'Origin', 'Destination', 'Pickup Date', 'Delivery Date', 'Sum of Pallets', 'Carrier', 'Trailer Number', 'Seal Number', 'Drivers ID'],
            ['LOAD-300', 'In Transit', 'PO-300', 'ING', 'AMS', '2026-03-26 08:30', '2026-03-27 10:00', '2', 'xpo', 'TRL-300', '', '', ''],
        ],
    ]);

    Http::fake([
        'docs.google.com/spreadsheets/*' => Http::response($workbookContents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $pickup = Location::factory()->pickup()->create(['short_code' => 'ING', 'name' => 'Ingrasys']);
    $dc = Location::factory()->distribution_center()->create(['short_code' => 'AMS', 'name' => 'AMS DC']);
    $carrier = Carrier::factory()->create(['name' => 'XPO Logistics', 'short_code' => 'XPO']);

    $shipment = Shipment::query()->create([
        'shipment_number' => 'LOAD-300',
        'status' => 'Pending',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
        'rack_qty' => 0,
        'load_bar_qty' => 0,
        'strap_qty' => 0,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.shipments.google-sheets-import'), [
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/test-sheet-id/edit#gid=0',
    ]);

    $response->assertRedirect(route('admin.shipments.index'));
    $response->assertSessionHas('success', fn (string $message) => str_contains($message, '1 shipment(s) updated from Google Sheets.'));

    expect($shipment->fresh())
        ->carrier_id->toBe($carrier->id);
});

it('imports rows from all workbook tabs, not just one sheet gid', function (): void {
    $workbookContents = buildGoogleSheetsWorkbook([
        'Tab One' => [
            ['Shipment Number', 'Status', 'PO Number', 'Origin', 'Destination', 'Carrier'],
            ['LOAD-401', 'In Transit', 'PO-401', 'ING', 'AMS', 'Carrier Beta'],
        ],
        'Tab Two' => [
            ['Shipment Number', 'Status', 'PO Number', 'Origin', 'Destination', 'Carrier'],
            ['LOAD-402', 'Booked', 'PO-402', 'ING', 'AMS', 'Carrier Beta'],
        ],
    ]);

    Http::fake([
        'docs.google.com/spreadsheets/*' => Http::response($workbookContents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $oldPickup = Location::factory()->pickup()->create(['short_code' => 'OLDP', 'name' => 'Old Pickup']);
    $oldDc = Location::factory()->distribution_center()->create(['short_code' => 'OLDD', 'name' => 'Old DC']);
    Location::factory()->pickup()->create(['short_code' => 'ING', 'name' => 'Ingrasys']);
    Location::factory()->distribution_center()->create(['short_code' => 'AMS', 'name' => 'AMS DC']);
    $carrier = Carrier::factory()->create(['name' => 'Carrier Beta', 'short_code' => 'BETA']);

    $shipmentOne = Shipment::query()->create([
        'shipment_number' => 'LOAD-401',
        'status' => 'Pending',
        'po_number' => 'PO-OLD-401',
        'pickup_location_id' => $oldPickup->id,
        'dc_location_id' => $oldDc->id,
    ]);

    $shipmentTwo = Shipment::query()->create([
        'shipment_number' => 'LOAD-402',
        'status' => 'Pending',
        'po_number' => 'PO-OLD-402',
        'pickup_location_id' => $oldPickup->id,
        'dc_location_id' => $oldDc->id,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.shipments.google-sheets-import'), [
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/test-sheet-id/edit#gid=123456',
    ]);

    $response->assertRedirect(route('admin.shipments.index'));
    $response->assertSessionHas('success', fn (string $message) => str_contains($message, '2 shipment(s) updated from Google Sheets.'));

    expect($shipmentOne->fresh())
        ->status->toBe('In Transit')
        ->po_number->toBe('PO-401')
        ->carrier_id->toBe($carrier->id);

    expect($shipmentTwo->fresh())
        ->status->toBe('Booked')
        ->po_number->toBe('PO-402')
        ->carrier_id->toBe($carrier->id);
});

it('normalizes checked-in status from google sheets import', function (): void {
    $workbookContents = buildGoogleSheetsWorkbook([
        'Sheet 1' => [
            ['Shipment Number', 'Status'],
            ['LOAD-500', 'Checked-In'],
        ],
    ]);

    Http::fake([
        'docs.google.com/spreadsheets/*' => Http::response($workbookContents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $pickup = Location::factory()->pickup()->create(['short_code' => 'ING', 'name' => 'Ingrasys']);
    $dc = Location::factory()->distribution_center()->create(['short_code' => 'AMS', 'name' => 'AMS DC']);

    $shipment = Shipment::query()->create([
        'shipment_number' => 'LOAD-500',
        'status' => 'Pending',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.shipments.google-sheets-import'), [
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/test-sheet-id/edit#gid=0',
    ]);

    $response->assertRedirect(route('admin.shipments.index'));

    expect($shipment->fresh())
        ->status->toBe('Checked In');
});

it('nulls shipment dates when google sheets date values are blank or TBD', function (): void {
    $workbookContents = buildGoogleSheetsWorkbook([
        'Sheet 1' => [
            ['Shipment Number', 'Drop Date', 'Pickup Date', 'Delivery Date', 'Status'],
            ['LOAD-511', '', 'TBD', '', 'Booked'],
        ],
    ]);

    Http::fake([
        'docs.google.com/spreadsheets/*' => Http::response($workbookContents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $pickup = Location::factory()->pickup()->create(['short_code' => 'ING', 'name' => 'Ingrasys']);
    $dc = Location::factory()->distribution_center()->create(['short_code' => 'AMS', 'name' => 'AMS DC']);

    $shipment = Shipment::query()->create([
        'shipment_number' => 'LOAD-511',
        'status' => 'Pending',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
        'drop_date' => '2026-03-25',
        'pickup_date' => '2026-03-26 08:00:00',
        'delivery_date' => '2026-03-27 08:00:00',
    ]);

    $response = $this->actingAs($admin)->post(route('admin.shipments.google-sheets-import'), [
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/test-sheet-id/edit#gid=0',
    ]);

    $response->assertRedirect(route('admin.shipments.index'));

    expect($shipment->fresh())
        ->drop_date->toBeNull()
        ->pickup_date->toBeNull()
        ->delivery_date->toBeNull();
});

it('maps elp rjs pickup to wiwynn rjs during google sheets import', function (): void {
    $workbookContents = buildGoogleSheetsWorkbook([
        'Sheet 1' => [
            ['Shipment Number', 'Origin', 'Destination', 'Status'],
            ['LOAD-510', 'ELP-RJS', 'AMS', 'Booked'],
        ],
    ]);

    Http::fake([
        'docs.google.com/spreadsheets/*' => Http::response($workbookContents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $mappedPickup = Location::factory()->pickup()->create(['short_code' => 'WIWYNN - RJS', 'name' => 'WIWYNN - RJS']);
    Location::factory()->pickup()->create(['short_code' => 'ELP-RJS', 'name' => 'Legacy ELP']);
    $dc = Location::factory()->distribution_center()->create(['short_code' => 'AMS', 'name' => 'AMS DC']);

    $shipment = Shipment::query()->create([
        'shipment_number' => 'LOAD-510',
        'status' => 'Pending',
        'pickup_location_id' => $dc->id,
        'dc_location_id' => $dc->id,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.shipments.google-sheets-import'), [
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/test-sheet-id/edit#gid=0',
    ]);

    $response->assertRedirect(route('admin.shipments.index'));

    expect($shipment->fresh())
        ->pickup_location_id->toBe($mappedPickup->id)
        ->dc_location_id->toBe($dc->id)
        ->status->toBe('Booked');
});

it('treats trailer, load bars, and straps carets as carry-forward values from the prior row', function (): void {
    $workbookContents = buildGoogleSheetsWorkbook([
        'Sheet 1' => [
            ['Shipment Number', 'Status', 'Origin', 'Destination', 'Carrier', 'Trailer Number', 'Load Bars', 'Straps'],
            ['LOAD-700', 'Booked', 'ING', 'AMS', 'Carrier Beta', 'TRL-700', '4', '12'],
            ['LOAD-701', 'Booked', 'ING', 'AMS', '', '^', '^', '^'],
        ],
    ]);

    Http::fake([
        'docs.google.com/spreadsheets/*' => Http::response($workbookContents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $pickup = Location::factory()->pickup()->create(['short_code' => 'ING', 'name' => 'Ingrasys']);
    $dc = Location::factory()->distribution_center()->create(['short_code' => 'AMS', 'name' => 'AMS DC']);
    $carrier = Carrier::factory()->create(['name' => 'Carrier Beta', 'short_code' => 'BETA']);

    $shipmentOne = Shipment::query()->create([
        'shipment_number' => 'LOAD-700',
        'status' => 'Pending',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
        'rack_qty' => 1,
        'load_bar_qty' => 1,
        'strap_qty' => 1,
    ]);

    $shipmentTwo = Shipment::query()->create([
        'shipment_number' => 'LOAD-701',
        'status' => 'Pending',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
        'rack_qty' => 2,
        'load_bar_qty' => 0,
        'strap_qty' => 0,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.shipments.google-sheets-import'), [
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/test-sheet-id/edit#gid=0',
    ]);

    $response->assertRedirect(route('admin.shipments.index'));
    $response->assertSessionHas('success', fn (string $message) => str_contains($message, '2 shipment(s) updated from Google Sheets.'));

    $trailer = Trailer::query()->where('number', 'TRL-700')->where('carrier_id', $carrier->id)->first();

    expect($trailer)->not->toBeNull();

    $shipmentOne->refresh();
    $shipmentTwo->refresh();

    expect($shipmentOne)
        ->carrier_id->toBe($carrier->id)
        ->trailer_id->toBe($trailer?->id)
        ->trailer->toBe('TRL-700')
        ->load_bar_qty->toBe(4)
        ->strap_qty->toBe(12);

    expect($shipmentTwo)
        ->carrier_id->toBe($carrier->id)
        ->trailer_id->toBe($trailer?->id)
        ->trailer->toBe('TRL-700')
        ->load_bar_qty->toBe(4)
        ->strap_qty->toBe(12)
        ->consolidation_number->toBe($shipmentOne->consolidation_number);

    expect($shipmentOne->consolidation_number)->not->toBeNull();
});

it('rejects a trailer caret on the first shipment row of a worksheet', function (): void {
    $workbookContents = buildGoogleSheetsWorkbook([
        'Sheet 1' => [
            ['Shipment Number', 'Status', 'Origin', 'Destination', 'Trailer Number'],
            ['LOAD-710', 'Booked', 'ING', 'AMS', '^'],
        ],
    ]);

    Http::fake([
        'docs.google.com/spreadsheets/*' => Http::response($workbookContents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $pickup = Location::factory()->pickup()->create(['short_code' => 'ING', 'name' => 'Ingrasys']);
    $dc = Location::factory()->distribution_center()->create(['short_code' => 'AMS', 'name' => 'AMS DC']);

    Shipment::query()->create([
        'shipment_number' => 'LOAD-710',
        'status' => 'Pending',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
    ]);

    $response = $this->actingAs($admin)
        ->from(route('admin.shipments.index'))
        ->post(route('admin.shipments.google-sheets-import'), [
            'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/test-sheet-id/edit#gid=0',
        ]);

    $response->assertRedirect(route('admin.shipments.index'));
    $response->assertSessionHasErrors('google_sheet_url');

    expect(session('errors')->first('google_sheet_url'))
        ->toContain('Trailer "^" requires a shipment row immediately above it on the same sheet.');
});

it('rejects trailer caret consolidation when the row lane does not match the shipment above it', function (): void {
    $workbookContents = buildGoogleSheetsWorkbook([
        'Sheet 1' => [
            ['Shipment Number', 'Status', 'Origin', 'Destination', 'Carrier', 'Trailer Number'],
            ['LOAD-720', 'Booked', 'ING', 'AMS', 'Carrier Beta', 'TRL-720'],
            ['LOAD-721', 'Booked', 'DAL', 'HOU', '', '^'],
        ],
    ]);

    Http::fake([
        'docs.google.com/spreadsheets/*' => Http::response($workbookContents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $pickupOne = Location::factory()->pickup()->create(['short_code' => 'ING', 'name' => 'Ingrasys']);
    $dcOne = Location::factory()->distribution_center()->create(['short_code' => 'AMS', 'name' => 'AMS DC']);
    $pickupTwo = Location::factory()->pickup()->create(['short_code' => 'DAL', 'name' => 'Dallas']);
    $dcTwo = Location::factory()->distribution_center()->create(['short_code' => 'HOU', 'name' => 'Houston']);
    $carrier = Carrier::factory()->create(['name' => 'Carrier Beta', 'short_code' => 'BETA']);

    $shipmentOne = Shipment::query()->create([
        'shipment_number' => 'LOAD-720',
        'status' => 'Pending',
        'pickup_location_id' => $pickupOne->id,
        'dc_location_id' => $dcOne->id,
    ]);

    $shipmentTwo = Shipment::query()->create([
        'shipment_number' => 'LOAD-721',
        'status' => 'Pending',
        'pickup_location_id' => $pickupTwo->id,
        'dc_location_id' => $dcTwo->id,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.shipments.google-sheets-import'), [
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/test-sheet-id/edit#gid=0',
    ]);

    $response->assertRedirect(route('admin.shipments.index'));
    $response->assertSessionHas('success', fn (string $message) => str_contains($message, '1 shipment(s) updated from Google Sheets.') && str_contains($message, '1 row(s) were skipped.'));

    $shipmentOne->refresh();
    $shipmentTwo->refresh();

    expect($shipmentOne)
        ->carrier_id->toBe($carrier->id)
        ->trailer->toBe('TRL-720');

    expect($shipmentTwo)
        ->carrier_id->toBeNull()
        ->trailer_id->toBeNull()
        ->trailer->toBeNull()
        ->consolidation_number->toBeNull();
});

/**
 * @param  array<string, array<int, array<int, string>>>  $sheets
 */
function buildGoogleSheetsWorkbook(array $sheets): string
{
    $spreadsheet = new Spreadsheet;
    $sheetIndex = 0;

    foreach ($sheets as $title => $rows) {
        $worksheet = $sheetIndex === 0
            ? $spreadsheet->getActiveSheet()
            : $spreadsheet->createSheet();

        $worksheet->setTitle(substr($title, 0, 31));

        foreach ($rows as $rowIndex => $columns) {
            foreach ($columns as $columnIndex => $value) {
                $cell = Coordinate::stringFromColumnIndex($columnIndex + 1).($rowIndex + 1);
                $worksheet->setCellValue($cell, $value);
            }
        }

        $sheetIndex++;
    }

    $writer = new Xlsx($spreadsheet);

    ob_start();
    $writer->save('php://output');

    return (string) ob_get_clean();
}
