<?php

namespace App\Actions;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

class SendNotification
{
    /**
     * Send notification to a single user.
     */
    public static function toUser(User $user, string $subject, string $message): void
    {
        $notification = Notification::create([
            'id' => str()->uuid(),
            'data' => [
                'subject' => $subject,
                'message' => $message,
            ],
        ]);

        $user->notifications()->attach($notification->id);
    }

    /**
     * Send notification to all users with a specific role.
     */
    public static function toRole(string $roleName, string $subject, string $message): void
    {
        $users = User::role($roleName)->get();
        self::toMultipleUsers($users, $subject, $message);
    }

    /**
     * Send notification to multiple users.
     */
    public static function toMultipleUsers(Collection $users, string $subject, string $message): void
    {
        $notification = Notification::create([
            'id' => str()->uuid(),
            'data' => [
                'subject' => $subject,
                'message' => $message,
            ],
        ]);

        $userIds = $users->pluck('id')->toArray();
        $notification->users()->attach($userIds);
    }

    /**
     * Send notification to all users in the system.
     */
    public static function toAllUsers(string $subject, string $message): void
    {
        $users = User::all();
        self::toMultipleUsers($users, $subject, $message);
    }
}
