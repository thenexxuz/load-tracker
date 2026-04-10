<?php

use App\Mail\NotificationEmail;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    // Setup
});

it('authenticated user can access notifications index', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('notifications.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
        );
});

it('shows user notifications with pagination', function (): void {
    $user = User::factory()->create();

    // Create 20 notifications for the user
    $notifications = Notification::factory()
        ->count(20)
        ->create()
        ->each(fn ($notification) => $user->notifications()->attach($notification->id));

    $response = $this->actingAs($user)->get(route('notifications.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->has('notifications.data', 15)
            ->where('notifications.total', 20)
            ->where('notifications.per_page', 15)
        );
});

it('notifications show correct structure with subject and timestamps', function (): void {
    $user = User::factory()->create();
    $notification = Notification::factory()->create([
        'data' => [
            'subject' => 'Test Notification',
            'message' => 'This is a test message',
        ],
    ]);
    $user->notifications()->attach($notification->id);

    $response = $this->actingAs($user)->get(route('notifications.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('notifications.data.0', fn (Assert $notif) => $notif
            ->where('id', $notification->id)
            ->where('data.subject', 'Test Notification')
            ->where('data.message', 'This is a test message')
            ->where('read_at', null)
            ->has('created_at')
        )
    );
});

it('shows unread notifications with null read_at', function (): void {
    $user = User::factory()->create();
    $notification = Notification::factory()->create([
        'data' => [
            'subject' => 'Unread Notification',
            'message' => 'This is unread',
        ],
    ]);
    $user->notifications()->attach($notification->id, ['read_at' => null]);

    $response = $this->actingAs($user)->get(route('notifications.index', ['show_read' => 1]));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('notifications.data.0.read_at', null)
    );
});

it('shows read notifications with read_at timestamp', function (): void {
    $user = User::factory()->create();
    $notification = Notification::factory()->create();
    $readAt = now();
    $user->notifications()->attach($notification->id, ['read_at' => $readAt]);

    $response = $this->actingAs($user)->get(route('notifications.index', ['show_read' => 1]));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('notifications.data.0.id', $notification->id)
        ->where('notifications.data.0.read_at', $readAt->format('Y-m-d H:i:s'))
    );
});

it('authenticated user can view a notification', function (): void {
    $user = User::factory()->create();
    $notification = Notification::factory()->create([
        'data' => [
            'subject' => 'Test Subject',
            'message' => 'Test Message',
        ],
    ]);
    $user->notifications()->attach($notification->id);

    $response = $this->actingAs($user)->get(route('notifications.show', $notification->id));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Show')
            ->where('notification.id', $notification->id)
            ->where('notification.subject', 'Test Subject')
            ->where('notification.message', 'Test Message')
        );
});

it('viewing a notification marks it as read', function (): void {
    $user = User::factory()->create();
    $notification = Notification::factory()->create([
        'data' => [
            'subject' => 'Test Subject',
            'message' => 'Test Message',
        ],
    ]);
    $user->notifications()->attach($notification->id, ['read_at' => null]);

    // Initially not read
    expect($user->notifications()->first()->pivot->read_at)->toBeNull();

    $this->actingAs($user)->get(route('notifications.show', $notification->id));

    // After viewing, should be marked as read
    $user->refresh();
    expect($user->notifications()->first()->pivot->read_at)->not()->toBeNull();
});

it('prevents unauthorized user from viewing another user notification', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $notification = Notification::factory()->create();
    $user1->notifications()->attach($notification->id);

    $response = $this->actingAs($user2)->get(route('notifications.show', $notification->id));

    $response->assertForbidden();
});

it('unauthenticated user cannot access notifications index', function (): void {
    $response = $this->get(route('notifications.index'));

    $response->assertRedirect(route('login'));
});

it('unauthenticated user cannot view notification', function (): void {
    $notification = Notification::factory()->create();

    $response = $this->get(route('notifications.show', $notification->id));

    $response->assertRedirect(route('login'));
});

it('user only sees their own notifications in index', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $notification1 = Notification::factory()->create([
        'data' => ['subject' => 'User 1 Notification'],
    ]);
    $notification2 = Notification::factory()->create([
        'data' => ['subject' => 'User 2 Notification'],
    ]);

    $user1->notifications()->attach($notification1->id);
    $user2->notifications()->attach($notification2->id);

    $response = $this->actingAs($user1)->get(route('notifications.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('notifications.data', 1)
        ->where('notifications.data.0.id', $notification1->id)
        ->where('notifications.data.0.data.subject', 'User 1 Notification')
    );
});

it('handles multiple notifications with different read statuses', function (): void {
    $user = User::factory()->create();

    $notification1 = Notification::factory()->create([
        'data' => ['subject' => 'Notification 1'],
    ]);
    $notification2 = Notification::factory()->create([
        'data' => ['subject' => 'Notification 2'],
    ]);
    $notification3 = Notification::factory()->create([
        'data' => ['subject' => 'Notification 3'],
    ]);

    $user->notifications()->attach($notification1->id, ['read_at' => now()]);
    $user->notifications()->attach($notification2->id, ['read_at' => null]);
    $user->notifications()->attach($notification3->id, ['read_at' => now()->subDay()]);

    $response = $this->actingAs($user)->get(route('notifications.index', ['show_read' => 1]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('notifications.data', 3)
    );
});

it('marks notification as read when tracking pixel is loaded', function (): void {
    $user = User::factory()->create();
    $notification = Notification::factory()->create([
        'data' => [
            'subject' => 'Pixel Test',
            'message' => 'Pixel body',
        ],
    ]);

    $user->notifications()->attach($notification->id, ['read_at' => null]);

    $signedUrl = URL::temporarySignedRoute(
        'notifications.email-open',
        now()->addMinutes(10),
        [
            'notification' => $notification->id,
            'user' => $user->id,
        ]
    );

    $response = $this->get($signedUrl);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/gif');

    $user->refresh();
    expect($user->notifications()->where('notification_id', $notification->id)->first()->pivot->read_at)
        ->not()->toBeNull();
});

it('emails notification only to users opted in for email notifications', function (): void {
    Mail::fake();

    $optedInUser = User::factory()->create([
        'notification_email_enabled' => true,
    ]);
    $optedOutUser = User::factory()->create([
        'notification_email_enabled' => false,
    ]);

    $notification = Notification::factory()->create([
        'data' => [
            'subject' => 'Email Test',
            'message' => 'Email body',
        ],
    ]);

    $notification->users()->attach([$optedInUser->id, $optedOutUser->id]);

    Mail::to($optedInUser->email)->send(new NotificationEmail($notification, $optedInUser));

    Mail::assertSent(NotificationEmail::class, function (NotificationEmail $mail) use ($optedInUser): bool {
        return $mail->hasTo($optedInUser->email);
    });

    Mail::assertNotSent(NotificationEmail::class, function (NotificationEmail $mail) use ($optedOutUser): bool {
        return $mail->hasTo($optedOutUser->email);
    });
});

it('bulk marks multiple unread notifications as read', function (): void {
    $user = User::factory()->create();

    $notifications = Notification::factory()
        ->count(3)
        ->create()
        ->each(fn ($notification) => $user->notifications()->attach($notification->id, ['read_at' => null]));

    $notificationIds = $notifications->pluck('id')->toArray();

    // Verify they're initially unread
    expect($user->notifications()->wherePivotNull('read_at')->count())->toBe(3);

    $response = $this->actingAs($user)->postJson(route('notifications.bulk-update'), [
        'notification_ids' => $notificationIds,
        'action' => 'mark_read',
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure(['message']);

    // Verify they're now read
    $user->refresh();
    expect($user->notifications()->wherePivotNotNull('read_at')->count())->toBe(3);
});

it('bulk marks multiple read notifications as unread', function (): void {
    $user = User::factory()->create();

    $notifications = Notification::factory()
        ->count(3)
        ->create()
        ->each(fn ($notification) => $user->notifications()->attach($notification->id, ['read_at' => now()]));

    $notificationIds = $notifications->pluck('id')->toArray();

    // Verify they're initially read
    expect($user->notifications()->wherePivotNotNull('read_at')->count())->toBe(3);

    $response = $this->actingAs($user)->postJson(route('notifications.bulk-update'), [
        'notification_ids' => $notificationIds,
        'action' => 'mark_unread',
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure(['message']);

    // Verify they're now unread
    $user->refresh();
    expect($user->notifications()->wherePivotNull('read_at')->count())->toBe(3);
});

it('bulk update prevents unauthorized access to other users notifications', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $notification = Notification::factory()->create();
    $user1->notifications()->attach($notification->id, ['read_at' => null]);

    $response = $this->actingAs($user2)->postJson(route('notifications.bulk-update'), [
        'notification_ids' => [$notification->id],
        'action' => 'mark_read',
    ]);

    $response->assertForbidden();
});

it('bulk update validates required action parameter', function (): void {
    $user = User::factory()->create();
    $notification = Notification::factory()->create();
    $user->notifications()->attach($notification->id);

    $response = $this->actingAs($user)->postJson(route('notifications.bulk-update'), [
        'notification_ids' => [$notification->id],
        'action' => 'invalid_action',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('action');
});

it('bulk update requires at least one notification id', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('notifications.bulk-update'), [
        'notification_ids' => [],
        'action' => 'mark_read',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('notification_ids');
});

it('unauthenticated user cannot perform bulk update', function (): void {
    $response = $this->postJson(route('notifications.bulk-update'), [
        'notification_ids' => ['test'],
        'action' => 'mark_read',
    ]);

    $response->assertUnauthorized();
});
