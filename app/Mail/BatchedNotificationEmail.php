<?php

namespace App\Mail;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class BatchedNotificationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $subjectLine;

    /**
     * @var Collection<int, Notification>
     */
    public Collection $notifications;

    /**
     * @var array<int, string>
     */
    public array $trackingPixelUrls;

    /**
     * @param  Collection<int, Notification>  $notifications
     */
    public function __construct(Collection $notifications, public User $user)
    {
        $this->notifications = $notifications->values();

        $count = $this->notifications->count();

        $this->subjectLine = $count === 1
            ? (string) ($this->notifications->first()?->data['subject'] ?? 'Import Notification')
            : "You have {$count} import notifications";

        $this->trackingPixelUrls = $this->notifications
            ->map(function (Notification $notification): string {
                return URL::temporarySignedRoute(
                    'notifications.email-open',
                    now()->addDays(30),
                    [
                        'notification' => $notification->id,
                        'user' => $this->user->id,
                    ]
                );
            })
            ->all();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.batched-notification',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
