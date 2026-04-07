<?php

namespace App\Actions;

use App\Mail\NotificationEmail;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendNotification
{
    /**
     * Send notification to a single user.
     */
    public static function toUser(User $user, string $subject, string $message): void
    {
        $notification = Notification::create([
            'id' => (string) Str::uuid(),
            'type' => 'manual',
            'data' => [
                'subject' => $subject,
                'message' => $message,
            ],
            'read_at' => null,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
        ]);

        $user->notifications()->attach($notification->id);
        self::sendEmailsToOptedInUsers($notification, collect([$user]));
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
        if ($users->isEmpty()) {
            return;
        }

        $notification = Notification::create([
            'id' => (string) Str::uuid(),
            'type' => 'manual',
            'data' => [
                'subject' => $subject,
                'message' => $message,
            ],
            'read_at' => null,
            'notifiable_type' => User::class,
            'notifiable_id' => $users->first()->id,
        ]);

        $userIds = $users->pluck('id')->toArray();
        $notification->users()->attach($userIds);
        self::sendEmailsToOptedInUsers($notification, $users);
    }

    /**
     * Send notification to all users in the system.
     */
    public static function toAllUsers(string $subject, string $message): void
    {
        $users = User::all();
        self::toMultipleUsers($users, $subject, $message);
    }

    private static function sendEmailsToOptedInUsers(Notification $notification, Collection $users): void
    {
        $users
            ->filter(fn (User $user): bool => (bool) $user->notification_email_enabled)
            ->each(function (User $user) use ($notification): void {
                Mail::to($user->email)->send(new NotificationEmail($notification, $user));
            });
    }
}
