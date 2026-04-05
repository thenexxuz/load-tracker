<?php

use App\Models\Carrier;
use App\Models\ScheduledItem;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('administrator', 'web');
});

test('scheduled item can be stored with a time value', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $carrier = Carrier::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.scheduled-items.store'), [
            'name' => 'Morning Carrier Email',
            'schedule_type' => 'daily',
            'schedule_time' => '08:30',
            'apply_to_all' => false,
            'schedulable_type' => 'carrier',
            'schedulable_id' => $carrier->id,
        ])
        ->assertRedirect(route('admin.scheduled-items.index'))
        ->assertSessionHas('success', 'Scheduled item created successfully.');

    $item = ScheduledItem::query()->latest('id')->first();

    expect($item)->not->toBeNull()
        ->and($item->schedule_time)->toBe('08:30')
        ->and($item->schedule_type)->toBe('daily');
});

test('scheduled item can be updated with a time value', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $carrier = Carrier::factory()->create();

    $item = ScheduledItem::create([
        'name' => 'Original Name',
        'schedule_type' => 'daily',
        'schedule_time' => '07:00',
        'apply_to_all' => false,
        'schedulable_type' => 'App\\Models\\Carrier',
        'schedulable_id' => $carrier->id,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.scheduled-items.update', $item), [
            'name' => 'Updated Name',
            'schedule_type' => 'daily',
            'schedule_time' => '09:15',
            'apply_to_all' => false,
            'schedulable_type' => 'carrier',
            'schedulable_id' => $carrier->id,
        ])
        ->assertRedirect(route('admin.scheduled-items.index'))
        ->assertSessionHas('success', 'Scheduled item updated successfully.');

    $item->refresh();

    expect($item->name)->toBe('Updated Name')
        ->and($item->schedule_time)->toBe('09:15');
});
