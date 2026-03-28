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

    $createShipment = function (string $shipmentNumber, string $status, Location $pickupLocation, ?int $carrierId = null) use ($dcLocation): Shipment {
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
            ->where('pickupLocationShipmentSummary', [
                [
                    'id' => $pickupAustin->id,
                    'name' => 'Austin Yard',
                    'short_code' => 'AUS',
                    'shipment_count' => 0,
                    'status_breakdown' => [],
                ],
                [
                    'id' => $pickupDallas->id,
                    'name' => 'Dallas Yard',
                    'short_code' => 'DAL',
                    'shipment_count' => 2,
                    'status_breakdown' => [
                        ['status' => 'Booked', 'count' => 1],
                        ['status' => 'Pending', 'count' => 1],
                    ],
                ],
                [
                    'id' => $pickupElPaso->id,
                    'name' => 'El Paso Yard',
                    'short_code' => 'ELP',
                    'shipment_count' => 2,
                    'status_breakdown' => [
                        ['status' => 'In Transit', 'count' => 1],
                        ['status' => 'Pending', 'count' => 1],
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
