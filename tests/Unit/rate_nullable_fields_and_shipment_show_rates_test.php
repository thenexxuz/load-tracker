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

it('keeps shipment show rates using viewer-specific carrier visibility rules', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $controller = file_get_contents($projectRoot.'/app/Http/Controllers/ShipmentController.php');
    $page = file_get_contents($projectRoot.'/resources/js/pages/Admin/Shipments/Show.vue');

    expect($controller)
        ->toContain("\$viewerIsCarrier = (bool) \$request->user()?->hasRole('carrier');")
        ->toContain('$viewerCarrierId = $request->user()?->carrier_id;')
        ->toContain("->where('pickup_location_id', \$shipment->pickup_location_id)")
        ->toContain("->orWhereNull('pickup_location_id');")
        ->toContain('if ($viewerIsCarrier && $viewerCarrierId) {')
        ->toContain('->where(function ($query) use ($viewerCarrierId) {')
        ->toContain("\$query->where('carrier_id', \$viewerCarrierId)")
        ->toContain("->orWhereNull('carrier_id');")
        ->toContain('} elseif ($viewerIsCarrier) {')
        ->toContain("\$ratesQuery->whereNull('carrier_id');")
        ->toContain("->where('carrier_id', \$shipment->carrier_id)")
        ->toContain("->where('pickup_location_id', \$shipment->dc_location_id)")
        ->toContain('->when($viewerIsCarrier && $viewerCarrierId, function ($query) use ($viewerCarrierId) {')
        ->toContain('->where(function ($carrierQuery) use ($viewerCarrierId) {')
        ->toContain("\$carrierQuery->where('carrier_id', \$viewerCarrierId)")
        ->toContain('->when($viewerIsCarrier && ! $viewerCarrierId, function ($query) {')
        ->toContain('->when(! $viewerIsCarrier && $shipment->carrier_id, function ($query) use ($shipment) {')
        ->toContain('->sort(function (Rate $leftRate, Rate $rightRate): int {')
        ->toContain('$leftHasCarrier = $leftRate->carrier_id !== null;')
        ->toContain('return $leftHasCarrier <=> $rightHasCarrier;')
        ->toContain('return $leftRate->rate <=> $rightRate->rate;')
        ->toContain("'name' => \$rate->name")
        ->toContain("'destination_city' => \$rate->destination_city")
        ->toContain("'destination_state' => \$rate->destination_state")
        ->toContain("'destination_country' => \$rate->destination_country")
        ->toContain("'destination_distance_miles' => \$this->rateDestinationDistanceMilesFromDc(\$shipment, \$dcLocation, \$rate)")
        ->toContain('shouldIncludeRegularRateForShipment($shipment, $dcLocation, $rate, 200.0)');

    expect($page)
        ->toContain('name: string | null')
        ->toContain('destination_city: string | null')
        ->toContain('destination_state: string | null')
        ->toContain('destination_country: string | null')
        ->toContain('destination_distance_miles: number | null')
        ->toContain('Name')
        ->toContain('Destination')
        ->toContain('selectedRateRadiusMiles = ref(60)')
        ->toContain('v-model.number="selectedRateRadiusMiles"')
        ->toContain('const isRateWithinSelectedRadius = (rate: typeof rates[number]): boolean => {')
        ->toContain('const displayedRates = computed(() => rates.filter((rate) => isRateWithinSelectedRadius(rate)))')
        ->toContain('rate.destination_distance_miles <= selectedRateRadiusMiles.value')
        ->toContain('watch(selectedRateRadiusMiles, () => {')
        ->toContain('includedRateIds.value = includedRateIds.value.filter((rateId) => {')
        ->toContain('return isRateWithinSelectedRadius(rate)')
        ->toContain('hasAssignedCarrier ? rates.map((rate) => rate.id) : []')
        ->toContain("{{ hasAssignedCarrier ? 'Total Rate Cost:' : 'Possible Total Rate Cost:' }}")
        ->toContain('toggleRateIncludedInTotal')
        ->toContain("{{ rate.name ?? 'Unnamed Rate' }}")
        ->toContain('{{ formatRateDestination(rate) }}');
});
