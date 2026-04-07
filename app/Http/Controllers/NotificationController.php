<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(): Response
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $showRead = request()->boolean('show_read');

        $notificationsQuery = $user->notifications();

        if (! $showRead) {
            $notificationsQuery->wherePivotNull('read_at');
        }

        $notifications = $notificationsQuery
            ->paginate(15)
            ->withQueryString()
            ->through(function ($notification) {
                return [
                    'id' => $notification->id,
                    'created_at' => $notification->created_at,
                    'read_at' => $notification->pivot->read_at,
                    'data' => [
                        'subject' => $notification->data['subject'] ?? '',
                        'message' => $notification->data['message'] ?? '',
                    ],
                ];
            });

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'filters' => [
                'show_read' => $showRead,
            ],
        ]);
    }

    public function show(Notification $notification): Response
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        // Check if the user has access to this notification
        if (! $user->notifications()->where('notification_id', $notification->id)->exists()) {
            throw new AuthorizationException('You do not have access to this notification.');
        }

        // Mark as read for this user
        $notification->markAsReadForUser($user);

        // Fetch the fresh read_at timestamp
        $userNotification = $user->notifications()
            ->where('notification_id', $notification->id)
            ->first();

        return Inertia::render('Notifications/Show', [
            'notification' => [
                'id' => $notification->id,
                'subject' => $notification->data['subject'] ?? '',
                'message' => $notification->data['message'] ?? '',
                'created_at' => $notification->created_at,
                'read_at' => $userNotification?->pivot?->read_at,
            ],
        ]);
    }

    public function emailOpen(Notification $notification, User $user): HttpResponse
    {
        if ($notification->users()->where('user_id', $user->id)->exists()) {
            $notification->markAsReadForUser($user);
        }

        $pixel = base64_decode('R0lGODlhAQABAPAAAAAAAAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');

        return response($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
