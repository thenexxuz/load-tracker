<?php

use App\Models\AutomatedItem;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Shipment;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('administrator', 'web');
    Role::findOrCreate('carrier', 'web');
    Role::findOrCreate('supervisor', 'web');
    Role::findOrCreate('truckload', 'web');
});

it('allows administrator to create an automated item', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $this->actingAs($admin)
        ->post(route('admin.automated-items.store'), [
            'name' => 'Shipment status monitor',
            'monitorable_type' => 'shipment',
            'monitored_fields' => ['status', 'carrier_id'],
            'role_name' => 'truckload',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.automated-items.index'))
        ->assertSessionHas('success', 'Automated item created successfully.');

    $item = AutomatedItem::query()->latest('id')->first();

    expect($item)->not->toBeNull()
        ->and($item->name)->toBe('Shipment status monitor')
        ->and($item->monitorable_type)->toBe('App\\Models\\Shipment')
        ->and($item->monitored_fields)->toBe(['status', 'carrier_id'])
        ->and($item->role_name)->toBe('truckload')
        ->and($item->is_active)->toBeTrue();
});

it('restricts automated item routes to administrator and supervisor roles', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $supervisor = User::factory()->create();
    $supervisor->assignRole('supervisor');

    $truckload = User::factory()->create();
    $truckload->assignRole('truckload');

    $this->actingAs($admin)
        ->post(route('admin.automated-items.store'), [])
        ->assertSessionHasErrors(['name', 'monitorable_type', 'monitored_fields', 'role_name']);

    $this->actingAs($supervisor)
        ->post(route('admin.automated-items.store'), [])
        ->assertSessionHasErrors(['name', 'monitorable_type', 'monitored_fields', 'role_name']);

    $this->actingAs($truckload)
        ->post(route('admin.automated-items.store'), [])
        ->assertForbidden();
});

it('sends a notification to the configured role when a monitored carrier field changes', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $recipient = User::factory()->create(['notification_email_enabled' => false]);
    $recipient->assignRole('truckload');

    AutomatedItem::create([
        'name' => 'Shipment status monitor',
        'monitorable_type' => Carrier::class,
        'monitored_fields' => ['name'],
        'role_name' => 'truckload',
        'is_active' => true,
    ]);

    $carrier = Carrier::factory()->create([
        'name' => 'Carrier One',
    ]);

    $this->actingAs($admin);
    $carrier->update(['name' => 'Carrier Two']);

    expect(Notification::query()->count())->toBe(1)
        ->and($recipient->notifications()->count())->toBe(1)
        ->and($recipient->notifications()->first()?->data['subject'] ?? null)
        ->toContain('Automated Item Triggered');
});

it('does not send a notification when only unmonitored carrier fields change', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $recipient = User::factory()->create(['notification_email_enabled' => false]);
    $recipient->assignRole('truckload');

    AutomatedItem::create([
        'name' => 'Shipment status monitor',
        'monitorable_type' => Carrier::class,
        'monitored_fields' => ['name'],
        'role_name' => 'truckload',
        'is_active' => true,
    ]);

    $carrier = Carrier::factory()->create([
        'name' => 'Carrier One',
        'wt_code' => 'WT-001',
    ]);

    $this->actingAs($admin);
    $carrier->update(['wt_code' => 'WT-999']);

    expect(Notification::query()->count())->toBe(0)
        ->and($recipient->notifications()->count())->toBe(0);
});

it('notifies only the assigned carrier users for shipment automated items targeting the carrier role', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $assignedCarrier = Carrier::factory()->create(['name' => 'Assigned Carrier']);
    $otherCarrier = Carrier::factory()->create(['name' => 'Other Carrier']);

    $assignedCarrierUser = User::factory()->create([
        'carrier_id' => $assignedCarrier->id,
        'notification_email_enabled' => false,
    ]);
    $assignedCarrierUser->assignRole('carrier');

    $otherCarrierUser = User::factory()->create([
        'carrier_id' => $otherCarrier->id,
        'notification_email_enabled' => false,
    ]);
    $otherCarrierUser->assignRole('carrier');

    AutomatedItem::create([
        'name' => 'Carrier shipment status monitor',
        'monitorable_type' => Shipment::class,
        'monitored_fields' => ['status'],
        'role_name' => 'carrier',
        'is_active' => true,
    ]);

    $pickup = Location::factory()->pickup()->create();
    $dc = Location::factory()->distribution_center()->create();

    $shipment = Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-CARRIER-001',
        'status' => 'Pending',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
        'carrier_id' => $assignedCarrier->id,
    ]);

    $this->actingAs($admin);
    $shipment->update(['status' => 'Delivered']);

    expect(Notification::query()->count())->toBe(1)
        ->and($assignedCarrierUser->notifications()->count())->toBe(1)
        ->and($otherCarrierUser->notifications()->count())->toBe(0)
        ->and($assignedCarrierUser->notifications()->first()?->data['message'] ?? null)
        ->toContain('SHIP-CARRIER-001');
});

it('notifies original and new carriers differently when shipment carrier assignment changes', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $originalCarrier = Carrier::factory()->create(['name' => 'Original Carrier']);
    $newCarrier = Carrier::factory()->create(['name' => 'New Carrier']);

    $originalCarrierUser = User::factory()->create([
        'carrier_id' => $originalCarrier->id,
        'notification_email_enabled' => false,
    ]);
    $originalCarrierUser->assignRole('carrier');

    $newCarrierUser = User::factory()->create([
        'carrier_id' => $newCarrier->id,
        'notification_email_enabled' => false,
    ]);
    $newCarrierUser->assignRole('carrier');

    AutomatedItem::create([
        'name' => 'Carrier assignment monitor',
        'monitorable_type' => Shipment::class,
        'monitored_fields' => ['carrier_id'],
        'role_name' => 'carrier',
        'is_active' => true,
    ]);

    $pickup = Location::factory()->pickup()->create();
    $dc = Location::factory()->distribution_center()->create();

    $shipment = Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-REASSIGN-001',
        'status' => 'Pending',
        'pickup_location_id' => $pickup->id,
        'dc_location_id' => $dc->id,
        'carrier_id' => $originalCarrier->id,
    ]);

    $this->actingAs($admin);
    $shipment->update(['carrier_id' => $newCarrier->id]);

    $originalNotification = $originalCarrierUser->notifications()->first();
    $newNotification = $newCarrierUser->notifications()->first();

    expect(Notification::query()->count())->toBe(2)
        ->and($originalCarrierUser->notifications()->count())->toBe(1)
        ->and($newCarrierUser->notifications()->count())->toBe(1)
        ->and($originalNotification?->data['subject'] ?? null)->toBe('Stand Down: Shipment SHIP-REASSIGN-001')
        ->and($originalNotification?->data['message'] ?? null)->toContain('stand down')
        ->and($newNotification?->data['subject'] ?? null)->toBe('New Shipment Assigned: SHIP-REASSIGN-001')
        ->and($newNotification?->data['message'] ?? null)->toContain('New Carrier');
});

it('formats dates as CST and resolves foreign keys to model names in automated shipment notifications', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $recipient = User::factory()->create(['notification_email_enabled' => false]);
    $recipient->assignRole('truckload');

    $oldCarrier = Carrier::factory()->create([
        'short_code' => 'OLD-TR',
        'name' => 'Old Carrier',
    ]);

    $newCarrier = Carrier::factory()->create([
        'short_code' => 'NEW-TR',
        'name' => 'New Carrier',
    ]);

    $oldPickup = Location::factory()->pickup()->create([
        'short_code' => 'PK-OLD',
        'name' => 'Old Pickup',
    ]);

    $newPickup = Location::factory()->pickup()->create([
        'short_code' => 'PK-NEW',
        'name' => 'New Pickup',
    ]);

    $dc = Location::factory()->distribution_center()->create();

    AutomatedItem::create([
        'name' => 'Shipment format monitor',
        'monitorable_type' => Shipment::class,
        'monitored_fields' => ['pickup_date', 'pickup_location_id', 'carrier_id'],
        'role_name' => 'truckload',
        'is_active' => true,
    ]);

    $shipment = Shipment::query()->create([
        'guid' => (string) str()->uuid(),
        'shipment_number' => 'SHIP-FMT-001',
        'status' => 'Pending',
        'pickup_location_id' => $oldPickup->id,
        'dc_location_id' => $dc->id,
        'carrier_id' => $oldCarrier->id,
        'pickup_date' => '2026-04-10 12:00:00',
    ]);

    $this->actingAs($admin);
    $shipment->update([
        'pickup_date' => '2026-04-11 13:30:00',
        'pickup_location_id' => $newPickup->id,
        'carrier_id' => $newCarrier->id,
    ]);

    $message = (string) ($recipient->notifications()->first()?->data['message'] ?? '');

    expect($recipient->notifications()->count())->toBe(1)
        ->and($message)->toContain('CST')
        ->and($message)->toContain('New Carrier')
        ->and($message)->toContain('PK-NEW - New Pickup')
        ->and($message)->not->toContain((string) $newCarrier->id)
        ->and($message)->not->toContain((string) $newPickup->id);
});
