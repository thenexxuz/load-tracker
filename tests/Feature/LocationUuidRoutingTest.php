<?php

use App\Models\Location;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('administrator', 'web');
});

test('locations index exposes guid as the public id', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $location = Location::factory()->pickup()->create([
        'guid' => (string) str()->uuid(),
        'short_code' => 'ATX',
        'name' => 'Austin Pickup',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.locations.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Locations/Index')
            ->where('locations.data.0.id', $location->guid)
            ->where('locations.data.0.short_code', 'ATX')
            ->where('locations.data.0.name', 'Austin Pickup')
        );
});

test('location show and edit pages resolve locations by guid', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $location = Location::factory()->pickup()->create([
        'guid' => (string) str()->uuid(),
        'short_code' => 'DAL',
        'name' => 'Dallas Pickup',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.locations.show', $location->guid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Locations/Show')
            ->where('location.id', $location->guid)
            ->where('location.short_code', 'DAL')
        );

    $this->actingAs($admin)
        ->get(route('admin.locations.edit', $location->guid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Locations/Edit')
            ->where('location.id', $location->guid)
            ->where('location.short_code', 'DAL')
        );
});
