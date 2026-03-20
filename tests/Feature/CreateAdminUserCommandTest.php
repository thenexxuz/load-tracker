<?php

use App\Models\User;
use App\Notifications\AdminUserCreated;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

it('creates an administrator user with all required details', function () {
    Notification::fake();
    Role::create(['name' => 'administrator']);

    $this->artisan('users:create-admin')
        ->expectsQuestion('Enter the email address for the new administrator', 'admin@example.com')
        ->expectsQuestion('Enter the name for the new administrator', 'John Admin')
        ->assertSuccessful();

    $this->assertDatabaseHas('users', [
        'email' => 'admin@example.com',
        'name' => 'John Admin',
    ]);

    $user = User::whereEmail('admin@example.com')->first();
    expect($user->hasRole('administrator'))->toBeTrue();

    Notification::assertSentTo($user, AdminUserCreated::class);
});

it('generates a random 10-character alphanumeric password', function () {
    Notification::fake();
    Role::create(['name' => 'administrator']);

    $this->artisan('users:create-admin')
        ->expectsQuestion('Enter the email address for the new administrator', 'test@example.com')
        ->expectsQuestion('Enter the name for the new administrator', 'Test User')
        ->assertSuccessful();

    $user = User::whereEmail('test@example.com')->first();

    expect($user->password)->not()->toBeEmpty();
    expect($user->password)->not()->toBe('random-password');

    Notification::assertSentTo($user, AdminUserCreated::class, function ($notification) {
        expect($notification->temporaryPassword)->toHaveLength(10);
        expect($notification->temporaryPassword)->toMatch('/^[a-zA-Z0-9]+$/');

        return true;
    });
});

it('fails when user with email already exists', function () {
    Role::create(['name' => 'administrator']);
    User::factory()->create(['email' => 'existing@example.com']);

    $this->artisan('users:create-admin')
        ->expectsQuestion('Enter the email address for the new administrator', 'existing@example.com')
        ->expectsOutput("A user with email 'existing@example.com' already exists.")
        ->assertFailed();

    expect(User::where('email', 'existing@example.com')->count())->toBe(1);
});

it('sends notification with temporary password', function () {
    Notification::fake();
    Role::create(['name' => 'administrator']);

    $this->artisan('users:create-admin')
        ->expectsQuestion('Enter the email address for the new administrator', 'newadmin@example.com')
        ->expectsQuestion('Enter the name for the new administrator', 'New Admin User')
        ->assertSuccessful();

    $user = User::whereEmail('newadmin@example.com')->first();

    Notification::assertSentTo($user, AdminUserCreated::class, function ($notification) {
        expect($notification->temporaryPassword)->toHaveLength(10);

        return true;
    });
});
