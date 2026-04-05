<?php

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Template;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('administrator', 'web');
});

test('template store accepts numeric string model id for carrier', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $carrier = Carrier::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.templates.store'), [
            'name' => 'Template String Model Id '.str()->random(8),
            'model_type' => 'carrier',
            'model_id' => (string) $carrier->id,
            'subject' => 'Test subject',
            'message' => '<p>Test message</p>',
        ])
        ->assertRedirect(route('admin.templates.index'))
        ->assertSessionHas('success', 'Template created successfully.');

    $template = Template::query()->latest('id')->first();

    expect($template)->not->toBeNull();
    expect($template?->model_type)->toBe('App\\Models\\Carrier');
});

test('template store accepts location uuid model id', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $location = Location::factory()->pickup()->create();

    $this->actingAs($admin)
        ->post(route('admin.templates.store'), [
            'name' => 'Location Template '.str()->random(8),
            'model_type' => 'location',
            'model_id' => $location->id,
            'subject' => 'Test subject',
            'message' => '<p>Test message</p>',
        ])
        ->assertRedirect(route('admin.templates.index'))
        ->assertSessionHas('success', 'Template created successfully.');

    $template = Template::query()->latest('id')->first();

    expect($template)->not->toBeNull();
    expect($template?->model_type)->toBe('App\\Models\\Location');
});

test('template update stores scheduled item type with null model id', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $template = Template::create([
        'name' => 'Scheduled Template '.str()->random(8),
        'model_type' => 'App\\Models\\ScheduledItem',
        'model_id' => null,
        'subject' => 'Original subject',
        'message' => '<p>Original message</p>',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.templates.update', $template), [
            'name' => $template->name,
            'model_type' => 'scheduled_item',
            'model_id' => null,
            'subject' => '{{carrier_name}} SCHEDULE {{today}}',
            'message' => '<p>Updated message</p>',
        ])
        ->assertRedirect(route('admin.templates.show', $template->id))
        ->assertSessionHas('success', 'Template updated successfully.');

    $template->refresh();

    expect($template->model_type)->toBe('App\\Models\\ScheduledItem')
        ->and($template->model_id)->toBeNull()
        ->and($template->subject)->toBe('{{carrier_name}} SCHEDULE {{today}}');
});
