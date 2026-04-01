<?php

it('keeps shipment index scoped for carrier users to assigned and offered shipments', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $controller = file_get_contents($projectRoot.'/app/Http/Controllers/ShipmentController.php');

    expect($controller)
        ->toContain('if ($request->user()?->hasRole(\'carrier\')) {')
        ->toContain('$carrierId = $request->user()?->carrier_id;')
        ->toContain('$carrierQuery->where(\'carrier_id\', $carrierId)')
        ->toContain('->orWhereHas(\'offeredCarriers\', function ($offerQuery) use ($carrierId) {')
        ->toContain('$offerQuery->where(\'carriers.id\', $carrierId);');
});

it('keeps shipment offers editable from the shipment show page and revokes offers on assignment', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $controller = file_get_contents($projectRoot.'/app/Http/Controllers/ShipmentController.php');
    $showPage = file_get_contents($projectRoot.'/resources/js/pages/Admin/Shipments/Show.vue');
    $shipmentModel = file_get_contents($projectRoot.'/app/Models/Shipment.php');
    $carrierModel = file_get_contents($projectRoot.'/app/Models/Carrier.php');
    $migration = file_get_contents($projectRoot.'/database/migrations/2026_03_25_075536_create_carrier_shipment_offers_table.php');
    $attributionMigration = file_get_contents($projectRoot.'/database/migrations/2026_03_26_010000_add_offered_by_user_id_to_carrier_shipment_offers_table.php');
    $routes = file_get_contents($projectRoot.'/routes/web.php');

    expect($shipmentModel)
        ->toContain('public function offeredCarriers(): BelongsToMany')
        ->toContain("return \$this->belongsToMany(Carrier::class, 'carrier_shipment_offers')")
        ->toContain("->withPivot('offered_by_user_id')");

    expect($carrierModel)
        ->toContain('public function offeredShipments(): BelongsToMany')
        ->toContain("return \$this->belongsToMany(Shipment::class, 'carrier_shipment_offers')")
        ->toContain("->withPivot('offered_by_user_id')");

    expect($migration)
        ->toContain("Schema::create('carrier_shipment_offers', function (Blueprint \$table) {")
        ->toContain("\$table->foreignId('shipment_id')->constrained()->cascadeOnDelete();")
        ->toContain("\$table->foreignId('carrier_id')->constrained()->cascadeOnDelete();")
        ->toContain("\$table->unique(['shipment_id', 'carrier_id']);");

    expect($attributionMigration)
        ->toContain("Schema::table('carrier_shipment_offers', function (Blueprint \$table) {")
        ->toContain("\$table->foreignId('offered_by_user_id')")
        ->toContain("->constrained('users')")
        ->toContain('->nullOnDelete();');

    expect($controller)
        ->toContain('public function show(Request $request, Shipment $shipment)')
        ->toContain("'offeredCarriers:id,name,short_code',")
        ->toContain('$offerUserNames = User::query()')
        ->toContain("'offered_by_user' => \$carrier->pivot->offered_by_user_id")
        ->toContain("'availableCarriers' => \$availableCarriers,")
        ->toContain("'offeredCarriers' => \$offeredCarriers,")
        ->toContain('public function updateOffers(Request $request, Shipment $shipment)')
        ->toContain("abort_unless(\$request->user()?->hasRole(['administrator', 'supervisor']), 403);")
        ->toContain("\$shipmentData['offered_carrier_ids'] = \$shipment->offeredCarriers->pluck('id')->all();")
        ->toContain("'offered_carrier_ids' => 'nullable|array',")
        ->toContain("'offered_carrier_ids.*' => 'uuid|exists:carriers,id',")
        ->toContain('$shipment->offeredCarriers()->sync([]);')
        ->toContain('private function buildOfferSyncPayload(Shipment $shipment, array $offeredCarrierIds, ?int $offeredByUserId): array')
        ->toContain("'offered_by_user_id' => \$existingOfferUserIds->get(\$carrierId, \$offeredByUserId),")
        ->toContain('$this->buildOfferSyncPayload($shipment, $offeredCarrierIds->all(), $request->user()?->id)');

    expect($routes)
        ->toContain("Route::patch('shipments/{shipment}/offers', [ShipmentController::class, 'updateOffers'])")
        ->toContain("->name('shipments.update-offers');");

    expect($showPage)
        ->toContain('availableCarriers: Array<{')
        ->toContain('offeredCarriers: Array<{')
        ->toContain('offered_by_user: {')
        ->toContain('const offerForm = useForm({')
        ->toContain('offered_carrier_ids: offeredCarriers.map((carrier) => carrier.id),')
        ->toContain("offerForm.patch(route('admin.shipments.update-offers', shipment.id), {")
        ->toContain('Offer Shipment To Carriers')
        ->toContain('v-model="offerForm.offered_carrier_ids"')
        ->toContain("Offered by {{ carrier.offered_by_user.name ?? 'Unknown User' }}")
        ->toContain('Carrier users assigned to the selected carriers will see this shipment on their Shipment Index.');
});
