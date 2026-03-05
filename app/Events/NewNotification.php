<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;

    public function __construct($notification)
    {
        $this->notification = $notification;
            \Log::info('NewNotification event created for user_id: ' . $this->notification->user_id);
            \Log::info('Notification data: ' . json_encode($this->notification->data));
            \Log::info('Notification object: ' . json_encode($this->notification, JSON_PRETTY_PRINT));
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->notification->user_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'title' => $this->notification->data['title'] ?? 'New Notification',
            'message' => $this->notification->data['message'],
            'link' => $this->notification->data['link'] ?? null,
            'created' => $this->notification->created_at->toDateTimeString(),
        ];
    }
}
