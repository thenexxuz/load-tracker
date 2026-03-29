<?php

use App\Models\AppSetting;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\Trailer;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::create(['name' => 'administrator']);
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

    $response = $this->actingAs($admin)->post(route('admin.shipments.google-sheets-import'), [
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
