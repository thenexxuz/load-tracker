<?php

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('administrator', 'web');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('administrators see active carrier shipment summary only for carriers with active shipments', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $pickup = Location::factory()->pickup()->create();
    $dc = Location::factory()->distribution_center()->create();

    $carrierAlpha = Carrier::factory()->create(['name' => 'Alpha Freight', 'short_code' => 'ALPHA']);
    $carrierBeta = Carrier::factory()->create(['name' => 'Beta Carriers', 'short_code' => 'BETA']);
    $carrierGamma = Carrier::factory()->create(['name' => 'Gamma Logistics', 'short_code' => 'GAMMA']);

    $makeShipment = fn (string $number, string $status, Carrier $carrier) => Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => $number,
        'status' => $status,
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
        'carrier_id' => $carrier->id,
    ]);

    // Alpha: 2 active (Booked + Pending)
    $makeShipment('ALPHA-001', 'Booked', $carrierAlpha);
    $makeShipment('ALPHA-002', 'Pending', $carrierAlpha);

    // Beta: 1 active (In Transit), 1 delivered (excluded from count)
    $makeShipment('BETA-001', 'In Transit', $carrierBeta);
    $makeShipment('BETA-002', 'Delivered', $carrierBeta);

    // Gamma: only Delivered + Cancelled → zero active, must not appear
    $makeShipment('GAMMA-001', 'Delivered', $carrierGamma);
    $makeShipment('GAMMA-002', 'Cancelled', $carrierGamma);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('carrierActiveShipmentSummary', 2)
            ->where('carrierActiveShipmentSummary.0.id', $carrierAlpha->id)
            ->where('carrierActiveShipmentSummary.0.name', 'Alpha Freight')
            ->where('carrierActiveShipmentSummary.0.short_code', 'ALPHA')
            ->where('carrierActiveShipmentSummary.0.active_shipment_count', 2)
            ->has('carrierActiveShipmentSummary.0.status_breakdown', 2)
            ->where('carrierActiveShipmentSummary.0.status_breakdown.0.status', 'Booked')
            ->where('carrierActiveShipmentSummary.0.status_breakdown.0.count', 1)
            ->where('carrierActiveShipmentSummary.0.status_breakdown.1.status', 'Pending')
            ->where('carrierActiveShipmentSummary.0.status_breakdown.1.count', 1)
            ->where('carrierActiveShipmentSummary.1.id', $carrierBeta->id)
            ->where('carrierActiveShipmentSummary.1.name', 'Beta Carriers')
            ->where('carrierActiveShipmentSummary.1.short_code', 'BETA')
            ->where('carrierActiveShipmentSummary.1.active_shipment_count', 1)
            ->has('carrierActiveShipmentSummary.1.status_breakdown', 1)
            ->where('carrierActiveShipmentSummary.1.status_breakdown.0.status', 'In Transit')
            ->where('carrierActiveShipmentSummary.1.status_breakdown.0.count', 1)
        );
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('isAdminOrSupervisor', false)
        );
});

test('administrators see pickup location and offer activity summaries', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-28 12:00:00'));

    $admin = User::factory()->create(['name' => 'Admin User']);
    $admin->assignRole('administrator');

    $offerUser = User::factory()->create(['name' => 'Alex Dispatcher']);
    $secondOfferUser = User::factory()->create(['name' => 'Bailey Dispatcher']);

    $pickupAustin = Location::factory()->pickup()->create([
        'name' => 'Austin Yard',
        'short_code' => 'AUS',
    ]);
    $pickupDallas = Location::factory()->pickup()->create([
        'name' => 'Dallas Yard',
        'short_code' => 'DAL',
    ]);
    $pickupElPaso = Location::factory()->pickup()->create([
        'name' => 'El Paso Yard',
        'short_code' => 'ELP',
    ]);
    $dcLocation = Location::factory()->distribution_center()->create();

    $assignedCarrier = Carrier::factory()->create();
    $offeredCarrierA = Carrier::factory()->create();
    $offeredCarrierB = Carrier::factory()->create();

    $createShipment = function (string $shipmentNumber, string $status, Location $pickupLocation, ?string $carrierId = null) use ($dcLocation): Shipment {
        return Shipment::query()->create([
            'guid' => (string) str()->uuid(),
            'shipment_number' => $shipmentNumber,
            'status' => $status,
            'pickup_location_id' => $pickupLocation->id,
            'dc_location_id' => $dcLocation->id,
            'carrier_id' => $carrierId,
        ]);
    };

    $pendingDallasShipment = $createShipment('SHIP-100', 'Pending', $pickupDallas);
    $bookedDallasShipment = $createShipment('SHIP-101', 'Booked', $pickupDallas, $assignedCarrier->id);
    $deliveredDallasShipment = $createShipment('SHIP-102', 'Delivered', $pickupDallas, $assignedCarrier->id);
    $inTransitElPasoShipment = $createShipment('SHIP-103', 'In Transit', $pickupElPaso, $assignedCarrier->id);
    $outsideWeekShipment = $createShipment('SHIP-104', 'Pending', $pickupElPaso);

    $lastWeekStart = CarbonImmutable::now()
        ->subWeek()
        ->startOfWeek(CarbonInterface::SUNDAY);
    $lastWeekEnd = $lastWeekStart->endOfWeek(CarbonInterface::SATURDAY);

    $pendingDallasShipment->offeredCarriers()->attach($offeredCarrierA->id, [
        'offered_by_user_id' => $offerUser->id,
        'created_at' => $lastWeekStart->addDay(),
        'updated_at' => $lastWeekStart->addDay(),
    ]);
    $pendingDallasShipment->offeredCarriers()->attach($offeredCarrierB->id, [
        'offered_by_user_id' => $offerUser->id,
        'created_at' => $lastWeekStart->addDays(2),
        'updated_at' => $lastWeekStart->addDays(2),
    ]);
    $bookedDallasShipment->offeredCarriers()->attach($offeredCarrierA->id, [
        'offered_by_user_id' => $offerUser->id,
        'created_at' => $lastWeekStart->addDays(4),
        'updated_at' => $lastWeekStart->addDays(4),
    ]);
    $inTransitElPasoShipment->offeredCarriers()->attach($offeredCarrierA->id, [
        'offered_by_user_id' => $secondOfferUser->id,
        'created_at' => $lastWeekStart->addDays(5),
        'updated_at' => $lastWeekStart->addDays(5),
    ]);
    $outsideWeekShipment->offeredCarriers()->attach($offeredCarrierA->id, [
        'offered_by_user_id' => $secondOfferUser->id,
        'created_at' => $lastWeekStart->subDay(),
        'updated_at' => $lastWeekStart->subDay(),
    ]);
    $deliveredDallasShipment->update([
        'delivery_date' => $lastWeekStart->addDays(3),
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('isAdminOrSupervisor', true)
            ->where('bookedCount', 1)
            ->where('monitoredLocationShipmentSummary', [
                [
                    'id' => $pickupAustin->id,
                    'name' => 'Austin Yard',
                    'short_code' => 'AUS',
                    'monitor_type' => 'pickup',
                    'shipment_count' => 0,
                    'unassigned_shipment_count' => 0,
                    'unassigned_shipment_index_url' => '/admin/shipments?excluded_pickup_locations%5B0%5D=DAL&excluded_pickup_locations%5B1%5D=ELP&excluded_statuses%5B0%5D=Delivered&only_unassigned=1',
                    'shipment_index_url' => '/admin/shipments?excluded_pickup_locations%5B0%5D=DAL&excluded_pickup_locations%5B1%5D=ELP&excluded_statuses%5B0%5D=Delivered',
                    'status_breakdown' => [],
                ],
                [
                    'id' => $pickupDallas->id,
                    'name' => 'Dallas Yard',
                    'short_code' => 'DAL',
                    'monitor_type' => 'pickup',
                    'shipment_count' => 2,
                    'unassigned_shipment_count' => 1,
                    'unassigned_shipment_index_url' => '/admin/shipments?excluded_pickup_locations%5B0%5D=AUS&excluded_pickup_locations%5B1%5D=ELP&excluded_statuses%5B0%5D=Delivered&only_unassigned=1',
                    'shipment_index_url' => '/admin/shipments?excluded_pickup_locations%5B0%5D=AUS&excluded_pickup_locations%5B1%5D=ELP&excluded_statuses%5B0%5D=Delivered',
                    'status_breakdown' => [
                        [
                            'status' => 'Booked',
                            'count' => 1,
                            'shipment_index_url' => '/admin/shipments?excluded_pickup_locations%5B0%5D=AUS&excluded_pickup_locations%5B1%5D=ELP&excluded_statuses%5B0%5D=Delivered&excluded_statuses%5B1%5D=In%20Transit&excluded_statuses%5B2%5D=Pending',
                        ],
                        [
                            'status' => 'Pending',
                            'count' => 1,
                            'shipment_index_url' => '/admin/shipments?excluded_pickup_locations%5B0%5D=AUS&excluded_pickup_locations%5B1%5D=ELP&excluded_statuses%5B0%5D=Booked&excluded_statuses%5B1%5D=Delivered&excluded_statuses%5B2%5D=In%20Transit',
                        ],
                    ],
                ],
                [
                    'id' => $pickupElPaso->id,
                    'name' => 'El Paso Yard',
                    'short_code' => 'ELP',
                    'monitor_type' => 'pickup',
                    'shipment_count' => 2,
                    'unassigned_shipment_count' => 1,
                    'unassigned_shipment_index_url' => '/admin/shipments?excluded_pickup_locations%5B0%5D=AUS&excluded_pickup_locations%5B1%5D=DAL&excluded_statuses%5B0%5D=Delivered&only_unassigned=1',
                    'shipment_index_url' => '/admin/shipments?excluded_pickup_locations%5B0%5D=AUS&excluded_pickup_locations%5B1%5D=DAL&excluded_statuses%5B0%5D=Delivered',
                    'status_breakdown' => [
                        [
                            'status' => 'In Transit',
                            'count' => 1,
                            'shipment_index_url' => '/admin/shipments?excluded_pickup_locations%5B0%5D=AUS&excluded_pickup_locations%5B1%5D=DAL&excluded_statuses%5B0%5D=Booked&excluded_statuses%5B1%5D=Delivered&excluded_statuses%5B2%5D=Pending',
                        ],
                        [
                            'status' => 'Pending',
                            'count' => 1,
                            'shipment_index_url' => '/admin/shipments?excluded_pickup_locations%5B0%5D=AUS&excluded_pickup_locations%5B1%5D=DAL&excluded_statuses%5B0%5D=Booked&excluded_statuses%5B1%5D=Delivered&excluded_statuses%5B2%5D=In%20Transit',
                        ],
                    ],
                ],
            ])
            ->where('offerActivitySummary.week.start', $lastWeekStart->toDateString())
            ->where('offerActivitySummary.week.end', $lastWeekEnd->toDateString())
            ->where('offerActivitySummary.week.label', 'Mar 15 - Mar 21, 2026')
            ->where('offerActivitySummary.users', [
                [
                    'id' => $offerUser->id,
                    'name' => 'Alex Dispatcher',
                    'offered_shipments_count' => 2,
                    'assigned_shipments_count' => 1,
                ],
                [
                    'id' => $secondOfferUser->id,
                    'name' => 'Bailey Dispatcher',
                    'offered_shipments_count' => 1,
                    'assigned_shipments_count' => 1,
                ],
            ])
        );
});
