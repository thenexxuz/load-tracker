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
