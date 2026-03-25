<?php

it('keeps the nullable rate lane migration for carrier pickup and destination fields', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $migration = file_get_contents($projectRoot.'/database/migrations/2026_03_25_040853_alter_rates_nullable_lane_fields.php');

    expect($migration)
        ->toContain("\$table->unsignedBigInteger('carrier_id')->nullable()->change();")
        ->toContain("\$table->unsignedBigInteger('pickup_location_id')->nullable()->change();")
        ->toContain("\$table->string('destination_city')->nullable()->change();")
        ->toContain("\$table->string('destination_state', 2)->nullable()->change();")
        ->toContain("\$table->string('destination_country', 2)->nullable()->change();")
        ->toContain("\$table->foreign('carrier_id')->references('id')->on('carriers')->nullOnDelete();")
        ->toContain("\$table->foreign('pickup_location_id')->references('id')->on('locations')->nullOnDelete();");
});

it('keeps shipment show rates including shared carrier-less fallback rates', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $controller = file_get_contents($projectRoot.'/app/Http/Controllers/ShipmentController.php');
    $page = file_get_contents($projectRoot.'/resources/js/pages/Admin/Shipments/Show.vue');

    expect($controller)
        ->toContain("->where('pickup_location_id', \$shipment->pickup_location_id)")
        ->toContain("->orWhereNull('pickup_location_id');")
        ->toContain("->where('carrier_id', \$shipment->carrier_id)")
        ->toContain("->orWhereNull('carrier_id');")
        ->toContain("->where('pickup_location_id', \$shipment->dc_location_id)")
        ->toContain('->when($shipment->carrier_id, function ($query) use ($shipment) {')
        ->toContain("'name' => \$rate->name");

    expect($page)
        ->toContain('name: string | null')
        ->toContain('Name')
        ->toContain("{{ rate.name ?? 'Unnamed Rate' }}");
});
