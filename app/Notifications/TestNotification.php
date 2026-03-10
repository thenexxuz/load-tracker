<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TestNotification extends Notification
{
    use Queueable;

    public $title;
    public $message;
    public $link;

    public function __construct($title, $message, $link = null)
    {
        $this->title = $title;
        $this->message = $message;
        $this->link = $link;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];  // store in DB + broadcast real-time
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'link' => $this->link,
        ];
    }

    public function broadcastOn()
    {
        return new \Illuminate\Broadcasting\Channel('user.' . $this->notifiable->id);
    }
    public function broadcastAs()
    {
        return 'NewNotification';
    }
}