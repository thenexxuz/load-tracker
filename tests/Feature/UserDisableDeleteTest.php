<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::create(['name' => 'administrator']);
});

it('can disable an active user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $user = User::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->patch(route('admin.users.disable', $user->id))
        ->assertSessionHas('success');

    expect($user->fresh()->is_active)->toBeFalse();
});

it('can enable a disabled user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $user = User::factory()->create(['is_active' => false]);

    $this->actingAs($admin)
        ->patch(route('admin.users.enable', $user->id))
        ->assertSessionHas('success');

    expect($user->fresh()->is_active)->toBeTrue();
});

it('can soft-delete a user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $user = User::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.users.delete', $user->id))
        ->assertSessionHas('success');

    expect($user->fresh()->trashed())->toBeTrue();
});

it('can restore a soft-deleted user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $user = User::factory()->create();
    $user->delete();

    $this->actingAs($admin)
        ->patch(route('admin.users.restore', $user->id))
        ->assertSessionHas('success');

    expect($user->fresh()->trashed())->toBeFalse();
});

it('prevents a user from disabling themselves', function () {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $this->actingAs($admin)
        ->patch(route('admin.users.disable', $admin->id))
        ->assertSessionHas('error');

    expect($admin->fresh()->is_active)->toBeTrue();
});

it('prevents a user from deleting themselves', function () {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $this->actingAs($admin)
        ->delete(route('admin.users.delete', $admin->id))
        ->assertSessionHas('error');

    expect($admin->fresh()->trashed())->toBeFalse();
});

it('includes user status in index view', function () {
    $admin = User::factory()->create();
    $admin->assignRole('administrator');

    $activeUser = User::factory()->create(['is_active' => true]);
    $disabledUser = User::factory()->create(['is_active' => false]);
    $deletedUser = User::factory()->create();
    $deletedUser->delete();

    $response = $this->actingAs($admin)
        ->get(route('admin.users.index'));

    $response->assertOk();

    // Assert the Inertia props contain the non-deleted users with correct statuses
    // Deleted users are excluded from the list by the model
    $response->assertInertia(fn ($page) =>
        $page->has('users', 3) // admin + activeUser + disabledUser
    );
});


