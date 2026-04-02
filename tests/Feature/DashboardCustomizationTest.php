<?php

use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('administrator', 'web');
});

it('uses pickup locations as default monitored locations for admins', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $pickupA = Location::factory()->pickup()->create(['short_code' => 'PU-A']);
    $pickupB = Location::factory()->pickup()->create(['short_code' => 'PU-B']);
    Location::factory()->distribution_center()->create(['short_code' => 'DC-A']);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('dashboardPreferences.sections.booked_shipments', true)
            ->where('dashboardPreferences.sections.deliveries_chart', true)
            ->where('dashboardPreferences.sections.monitored_locations', true)
            ->where('dashboardPreferences.sections.active_shipments_by_carrier', true)
            ->where('dashboardPreferences.sections.shipment_offers_by_user', true)
            ->where('dashboardPreferences.monitored_location_ids', fn ($locationIds): bool => collect($locationIds)->contains($pickupA->guid)
                && collect($locationIds)->contains($pickupB->guid)
            )
        );
});

it('saves dashboard preferences and uses inbound locations as dc shipment monitors', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $pickup = Location::factory()->pickup()->create(['short_code' => 'PU-1']);
    $otherPickup = Location::factory()->pickup()->create(['short_code' => 'PU-2']);
    $dc = Location::factory()->distribution_center()->create(['short_code' => 'DC-1']);

    Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'DASH-001',
        'status' => 'Pending',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
        'rack_qty' => 1,
        'load_bar_qty' => 1,
        'strap_qty' => 1,
    ]);

    Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'DASH-002',
        'status' => 'Booked',
        'pickup_location_id' => $otherPickup->id,
        'dc_location_id' => $dc->id,
        'rack_qty' => 1,
        'load_bar_qty' => 1,
        'strap_qty' => 1,
    ]);

    $updateResponse = $this->actingAs($admin)->patch(route('dashboard-preferences.update'), [
        'sections' => [
            'booked_shipments' => true,
            'deliveries_chart' => false,
            'monitored_locations' => true,
            'active_shipments_by_carrier' => true,
            'shipment_offers_by_user' => false,
        ],
        'monitored_location_ids' => [$dc->guid],
    ]);

    $updateResponse->assertRedirect(route('dashboard-preferences.edit'));

    expect($admin->fresh()?->dashboard_preferences)
        ->toBeArray()
        ->and($admin->fresh()?->dashboard_preferences['sections']['deliveries_chart'] ?? null)->toBeFalse()
        ->and($admin->fresh()?->dashboard_preferences['sections']['shipment_offers_by_user'] ?? null)->toBeFalse()
        ->and($admin->fresh()?->dashboard_preferences['monitored_location_ids'] ?? [])->toBe([$dc->guid]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('dashboardPreferences.monitored_location_ids.0', $dc->guid)
            ->where('dashboardPreferences.sections.deliveries_chart', false)
            ->where('dashboardPreferences.sections.shipment_offers_by_user', false)
            ->where('monitoredLocationShipmentSummary.0.monitor_type', 'dc')
            ->where('monitoredLocationShipmentSummary.0.shipment_count', 2)
        );
});

it('allows non-admin users to manage only their own dashboard preferences', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $pickup = Location::factory()->pickup()->create();

    $this->actingAs($user)
        ->patch(route('dashboard-preferences.update'), [
            'sections' => [
                'booked_shipments' => false,
                'deliveries_chart' => true,
                'monitored_locations' => false,
                'active_shipments_by_carrier' => true,
                'shipment_offers_by_user' => false,
            ],
            'monitored_location_ids' => [$pickup->guid],
        ])
        ->assertRedirect(route('dashboard-preferences.edit'));

    expect($user->fresh()?->dashboard_preferences)
        ->toBeArray()
        ->and($user->fresh()?->dashboard_preferences['sections']['booked_shipments'] ?? null)->toBeFalse()
        ->and($user->fresh()?->dashboard_preferences['sections']['monitored_locations'] ?? null)->toBeFalse()
        ->and($user->fresh()?->dashboard_preferences['monitored_location_ids'] ?? [])->toBe([$pickup->guid]);

    expect($otherUser->fresh()?->dashboard_preferences)->toBeNull();
});
