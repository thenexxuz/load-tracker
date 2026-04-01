<?php

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Rate;

it('imports rates from csv file', function () {
    $location = Location::factory()->create(['short_code' => 'ABC']);

    $carrier1 = Carrier::factory()->create(['wt_code' => 'WT1', 'short_code' => 'C1']);
    $carrier2 = Carrier::factory()->create(['wt_code' => 'WT2', 'short_code' => 'C2']);

    $csv = implode("\n", [
        'TYPE,NAME,ORIGIN,DESTINATION,WT1,WT2',
        'per_mile,NYC Lane,ABC,"New York,NY",2.50,3.50',
        'flat,LA Flat,ABC,"Los Angeles,CA",500.00,',
    ]);

    $path = sys_get_temp_dir().'/rates_import_test.csv';
    file_put_contents($path, $csv);

    $this->artisan('rates:import', ['file' => $path, '--force' => true])->assertSuccessful();

    expect(Rate::where('pickup_location_id', $location->id)->count())->toBe(3);

    $this->assertDatabaseHas('rates', [
        'carrier_id' => $carrier1->id,
        'pickup_location_id' => $location->id,
        'destination_city' => 'New York',
        'destination_state' => 'NY',
        'destination_country' => 'US',
        'rate' => 2.5,
        'type' => 'per_mile',
        'name' => 'NYC Lane',
    ]);

    $this->assertDatabaseHas('rates', [
        'carrier_id' => $carrier2->id,
        'pickup_location_id' => $location->id,
        'destination_city' => 'New York',
        'destination_state' => 'NY',
        'destination_country' => 'US',
        'rate' => 3.5,
        'type' => 'per_mile',
        'name' => 'NYC Lane',
    ]);

    $this->assertDatabaseHas('rates', [
        'carrier_id' => $carrier1->id,
        'pickup_location_id' => $location->id,
        'destination_city' => 'Los Angeles',
        'destination_state' => 'CA',
        'destination_country' => 'US',
        'rate' => 500.0,
        'type' => 'flat',
        'name' => 'LA Flat',
    ]);
});

it('fails when csv file is missing or invalid header', function () {
    $this->artisan('rates:import', ['file' => '/does/not/exist.csv', '--force' => true])
        ->assertFailed();

    $tmpPath = sys_get_temp_dir().'/rates_import_bad_header.csv';
    file_put_contents($tmpPath, "TYPE,SOMETHING,ELSE,ANOTHER\n");

    $this->artisan('rates:import', ['file' => $tmpPath, '--force' => true])
        ->assertFailed();
});

it('maps elp rjs origin to wiwynn rjs when importing rates', function () {
    $mappedPickup = Location::factory()->create(['short_code' => 'WIWYNN - RJS']);
    $legacyPickup = Location::factory()->create(['short_code' => 'ELP-RJS']);

    $carrier = Carrier::factory()->create(['wt_code' => 'WT3', 'short_code' => 'C3']);

    $csv = implode("\n", [
        'TYPE,NAME,ORIGIN,DESTINATION,WT3',
        'per_mile,ELP Lane,ELP-RJS,"El Paso,TX",2.75',
    ]);

    $path = sys_get_temp_dir().'/rates_import_elp_alias.csv';
    file_put_contents($path, $csv);

    $this->artisan('rates:import', ['file' => $path, '--force' => true])->assertSuccessful();

    $this->assertDatabaseHas('rates', [
        'carrier_id' => $carrier->id,
        'pickup_location_id' => $mappedPickup->id,
        'destination_city' => 'El Paso',
        'destination_state' => 'TX',
        'destination_country' => 'US',
        'rate' => 2.75,
        'name' => 'ELP Lane',
    ]);

    expect(Rate::query()->where('pickup_location_id', $legacyPickup->id)->count())->toBe(0);
});
